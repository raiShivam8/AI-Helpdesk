<?php

namespace App\Actions;

use App\Enums\Role;
use App\Enums\SenderType;
use App\Enums\TicketStatus;
use App\Jobs\AutoResolveTicketJob;
use App\Jobs\TicketClassificationJob;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateTicketFromInboundEmailAction
{
    /**
     * Create a ticket, find or create the customer user, create the initial customer TicketReply,
     * and run AI classification & auto-resolution immediately (synchronously) so it works
     * even without a persistent queue worker process.
     *
     * @param array $parsedData Standardized array containing:
     *                          - message_id
     *                          - sender_email
     *                          - sender_name
     *                          - subject
     *                          - body
     *                          - received_date (optional)
     * @return Ticket
     */
    public function execute(array $parsedData, bool $syncJobs = true): Ticket
    {
        $ticket = DB::transaction(function () use ($parsedData) {
            $senderEmail = strtolower(trim($parsedData['sender_email']));
            $senderName  = trim($parsedData['sender_name'] ?? '') ?: 'Customer';

            // 1. Find or create the customer user by email (handling soft-deleted records gracefully)
            $customer = User::withTrashed()->where('email', $senderEmail)->first();

            if ($customer) {
                if ($customer->trashed()) {
                    $customer->restore();
                }
            } else {
                $customer = User::firstOrCreate(
                    ['email' => $senderEmail],
                    [
                        'name'     => $senderName,
                        'password' => Hash::make(Str::random(16)),
                        'role'     => Role::Customer,
                    ]
                );

                Log::info('Created new customer user record for inbound email', [
                    'user_id' => $customer->id,
                    'email'   => $senderEmail,
                    'name'    => $senderName,
                ]);
            }

            $rawBody = !empty(trim($parsedData['body'] ?? '')) ? trim($parsedData['body']) : '(No content)';
            $cleanedBody = app(\App\Services\ImapService::class)->cleanEmailBody($rawBody);
            if (empty($cleanedBody)) {
                $cleanedBody = '(No content)';
            }

            // Check if inbound email is a reply to an existing ticket via Subject (e.g. "[Ticket #12]" or "Ticket #12")
            $existingTicket = null;
            $subject = trim($parsedData['subject'] ?? '');

            if (preg_match('/(?:\[Ticket\s*#|Ticket\s*#|#)(\d+)/i', $subject, $matches)) {
                $ticketId = (int) $matches[1];
                $existingTicket = Ticket::find($ticketId);
            }

            $attachments = $parsedData['attachments'] ?? [];
            $firstAttachment = $attachments[0] ?? null;

            $createdReplies = [];

            // If a matching ticket exists, append reply to the existing ticket
            if ($existingTicket) {
                $r1 = $existingTicket->replies()->create([
                    'user_id'                      => $customer->id,
                    'body'                         => $cleanedBody,
                    'body_html'                    => $parsedData['body_html'] ?? null,
                    'sender_type'                  => SenderType::Customer,
                    'attachment_path'              => $firstAttachment['path'] ?? null,
                    'attachment_name'              => $firstAttachment['name'] ?? null,
                    'attachment_mime'              => $firstAttachment['mime'] ?? null,
                    'attachment_processing_status' => ($firstAttachment['path'] ?? null) ? 'pending' : 'none',
                    'created_at'                   => $parsedData['received_date'] ?? now(),
                ]);
                $createdReplies[] = $r1;

                if (count($attachments) > 1) {
                    for ($i = 1; $i < count($attachments); $i++) {
                        $att = $attachments[$i];
                        $rExtra = $existingTicket->replies()->create([
                            'user_id'                      => $customer->id,
                            'body'                         => 'Attachment: ' . ($att['name'] ?? 'File'),
                            'sender_type'                  => SenderType::Customer,
                            'attachment_path'              => $att['path'] ?? null,
                            'attachment_name'              => $att['name'] ?? null,
                            'attachment_mime'              => $att['mime'] ?? null,
                            'attachment_processing_status' => ($att['path'] ?? null) ? 'pending' : 'none',
                            'created_at'                   => $parsedData['received_date'] ?? now(),
                        ]);
                        $createdReplies[] = $rExtra;
                    }
                }

                if ($existingTicket->status === TicketStatus::Resolved || $existingTicket->status === TicketStatus::Closed) {
                    $existingTicket->update(['status' => TicketStatus::Open]);
                }

                Log::info("Appended customer reply with attachments to existing Ticket #{$existingTicket->id}", [
                    'ticket_id' => $existingTicket->id,
                    'customer'  => $senderEmail,
                ]);

                try {
                    $staff = User::whereIn('role', [Role::Admin->value, Role::Agent->value])->get();
                    foreach ($staff as $user) {
                        \App\Models\AppNotification::create([
                            'user_id' => $user->id,
                            'title'   => "New Customer Reply on Ticket #{$existingTicket->id}",
                            'message' => "Customer {$senderName} replied to ticket: {$existingTicket->subject}",
                            'link'    => route('tickets.show', $existingTicket),
                            'type'    => 'ticket_reply',
                        ]);
                    }
                } catch (\Throwable $notifEx) {
                    Log::warning('Failed to dispatch reply AppNotification', ['error' => $notifEx->getMessage()]);
                }

                // Dispatch asynchronous image optimization jobs
                foreach ($createdReplies as $replyItem) {
                    if ($replyItem->isImageAttachment()) {
                        \App\Jobs\OptimizeImageAttachmentJob::dispatch($replyItem->id);
                    } else {
                        $replyItem->update(['attachment_processing_status' => 'none']);
                    }
                }

                return $existingTicket;
            }

            // 2. Create the new ticket record if no existing ticket matched
            $ticket = Ticket::create([
                'message_id'   => $parsedData['message_id'] ?? null,
                'sender_email' => $senderEmail,
                'sender_name'  => $senderName,
                'subject'      => !empty($subject) ? $subject : '(No Subject)',
                'body'         => $cleanedBody,
                'status'       => TicketStatus::New,
                'created_at'   => $parsedData['received_date'] ?? now(),
            ]);

            // 3. Create the initial TicketReply with sender_type = Customer (including email image/attachment if present)
            $r1 = TicketReply::create([
                'ticket_id'                    => $ticket->id,
                'user_id'                      => $customer->id,
                'body'                         => $ticket->body,
                'body_html'                    => $parsedData['body_html'] ?? null,
                'sender_type'                  => SenderType::Customer,
                'attachment_path'              => $firstAttachment['path'] ?? null,
                'attachment_name'              => $firstAttachment['name'] ?? null,
                'attachment_mime'              => $firstAttachment['mime'] ?? null,
                'attachment_processing_status' => ($firstAttachment['path'] ?? null) ? 'pending' : 'none',
                'created_at'                   => $ticket->created_at,
            ]);
            $createdReplies[] = $r1;

            // Save any additional attachments sent by customer as extra reply items
            if (count($attachments) > 1) {
                for ($i = 1; $i < count($attachments); $i++) {
                    $att = $attachments[$i];
                    $rExtra = TicketReply::create([
                        'ticket_id'                    => $ticket->id,
                        'user_id'                      => $customer->id,
                        'body'                         => 'Attachment: ' . ($att['name'] ?? 'File'),
                        'sender_type'                  => SenderType::Customer,
                        'attachment_path'              => $att['path'] ?? null,
                        'attachment_name'              => $att['name'] ?? null,
                        'attachment_mime'              => $att['mime'] ?? null,
                        'attachment_processing_status' => ($att['path'] ?? null) ? 'pending' : 'none',
                        'created_at'                   => $ticket->created_at,
                    ]);
                    $createdReplies[] = $rExtra;
                }
            }

            // Dispatch asynchronous image optimization jobs
            foreach ($createdReplies as $replyItem) {
                if ($replyItem->isImageAttachment()) {
                    \App\Jobs\OptimizeImageAttachmentJob::dispatch($replyItem->id);
                } else {
                    $replyItem->update(['attachment_processing_status' => 'none']);
                }
            }

            Log::info('Successfully created Ticket and initial Customer TicketReply from inbound email', [
                'ticket_id'    => $ticket->id,
                'customer_id'  => $customer->id,
                'sender_email' => $senderEmail,
                'message_id'   => $ticket->message_id,
            ]);

            // Create AppNotification for all admins & agents
            try {
                $staff = User::whereIn('role', [Role::Admin->value, Role::Agent->value])->get();
                foreach ($staff as $user) {
                    \App\Models\AppNotification::create([
                        'user_id' => $user->id,
                        'title'   => "New Ticket #{$ticket->id}: {$ticket->subject}",
                        'message' => "Created by {$ticket->sender_name} ({$senderEmail})",
                        'link'    => route('tickets.show', $ticket),
                        'type'    => 'ticket_created',
                    ]);
                }
            } catch (\Throwable $notifEx) {
                Log::warning('Failed to dispatch new ticket AppNotification', ['error' => $notifEx->getMessage()]);
            }

            return $ticket;
        });

        // Run AI Auto-Resolve and Classification
        if ($syncJobs) {
            try {
                Log::info('Executing AI auto-resolve synchronously', ['ticket_id' => $ticket->id]);
                AutoResolveTicketJob::dispatchSync($ticket);
            } catch (\Throwable $autoEx) {
                report($autoEx);
                Log::warning('Failed synchronous AI auto-resolve job', [
                    'ticket_id' => $ticket->id,
                    'error'     => $autoEx->getMessage(),
                ]);
            }

            try {
                Log::info('Executing AI classification synchronously', ['ticket_id' => $ticket->id]);
                TicketClassificationJob::dispatchSync($ticket);
            } catch (\Throwable $classifyEx) {
                report($classifyEx);
            }
        } else {
            try {
                Log::info('Dispatching AI auto-resolve to background queue', ['ticket_id' => $ticket->id]);
                AutoResolveTicketJob::dispatch($ticket);
            } catch (\Throwable $autoEx) {
                report($autoEx);
                Log::warning('Failed to dispatch AI auto-resolve job', [
                    'ticket_id' => $ticket->id,
                    'error'     => $autoEx->getMessage(),
                ]);
            }

            try {
                Log::info('Dispatching AI classification to background queue', ['ticket_id' => $ticket->id]);
                TicketClassificationJob::dispatch($ticket);
            } catch (\Throwable $classifyEx) {
                report($classifyEx);
            }
        }

        $ticket->refresh();

        return $ticket;
    }
}
