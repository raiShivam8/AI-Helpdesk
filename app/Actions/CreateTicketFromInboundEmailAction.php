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
    public function execute(array $parsedData): Ticket
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

            // 2. Create the ticket record
            $ticket = Ticket::create([
                'message_id'   => $parsedData['message_id'] ?? null,
                'sender_email' => $senderEmail,
                'sender_name'  => $senderName,
                'subject'      => !empty(trim($parsedData['subject'] ?? '')) ? trim($parsedData['subject']) : '(No Subject)',
                'body'         => !empty(trim($parsedData['body'] ?? '')) ? trim($parsedData['body']) : '(No content)',
                'status'       => TicketStatus::New,
                'created_at'   => $parsedData['received_date'] ?? now(),
            ]);

            // 3. Create the initial TicketReply with sender_type = Customer
            TicketReply::create([
                'ticket_id'   => $ticket->id,
                'user_id'     => $customer->id,
                'body'        => $ticket->body,
                'sender_type' => SenderType::Customer,
                'created_at'  => $ticket->created_at,
            ]);

            Log::info('Successfully created Ticket and initial Customer TicketReply from inbound email', [
                'ticket_id'    => $ticket->id,
                'customer_id'  => $customer->id,
                'sender_email' => $senderEmail,
                'message_id'   => $ticket->message_id,
            ]);

            return $ticket;
        });

        // 1. Dispatch Auto-Resolve to background queue so ticket creation is instant
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

        // 2. Dispatch Classification to background queue so web response is instant (< 1.5s)
        try {
            Log::info('Dispatching AI classification to background queue', ['ticket_id' => $ticket->id]);
            TicketClassificationJob::dispatch($ticket);
        } catch (\Throwable $classifyEx) {
            report($classifyEx);
        }

        $ticket->refresh();

        return $ticket;
    }
}
