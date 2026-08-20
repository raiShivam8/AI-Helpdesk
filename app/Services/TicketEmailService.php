<?php

namespace App\Services;

use App\Mail\TicketReplyMail;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TicketEmailService
{
    /**
     * Extract customer's first name from sender_name or sender_email.
     */
    public function extractCustomerFirstName(Ticket $ticket): string
    {
        $fullName = trim($ticket->sender_name ?? '');

        if (!empty($fullName)) {
            $parts = preg_split('/\s+/', $fullName);
            return $parts[0];
        }

        if (!empty($ticket->sender_email) && str_contains($ticket->sender_email, '@')) {
            $localPart = explode('@', $ticket->sender_email)[0];
            return ucwords(str_replace(['.', '_', '-'], ' ', $localPart));
        }

        return 'Customer';
    }

    /**
     * Send an email response to the customer when a ticket reply is posted or auto-resolved.
     * Uses Laravel Mail transport (Gmail SMTP).
     */
    public function sendTicketReplyEmail(Ticket $ticket, TicketReply $reply): bool
    {
        $customerFirstName = $this->extractCustomerFirstName($ticket);
        $signature = 'Code With Mosh Support';
        $recipientEmail = $ticket->sender_email;

        if (empty($recipientEmail)) {
            Log::warning('Cannot send ticket reply email: Ticket has no customer sender_email.', [
                'ticket_id' => $ticket->id,
                'reply_id'  => $reply->id,
            ]);

            return false;
        }

        // Handle test recipient override for dummy example domain redirection in local development
        $testEmail = config('services.test_recipient_email');
        if (app()->isLocal() && !app()->runningInConsole() && !empty($testEmail)) {
            if (preg_match('/@example\.(com|org|net)$/i', $recipientEmail)) {
                Log::info('Redirecting dummy example domain email to test recipient email', [
                    'ticket_id'      => $ticket->id,
                    'original_email' => $recipientEmail,
                    'target_email'   => $testEmail,
                ]);
                $recipientEmail = $testEmail;
            }
        }

        Log::info('Preparing to send ticket reply email to customer via Laravel Mail SMTP', [
            'ticket_id'      => $ticket->id,
            'reply_id'       => $reply->id,
            'customer_email' => $recipientEmail,
            'first_name'     => $customerFirstName,
        ]);

        try {
            $mailable = new TicketReplyMail($ticket, $reply, $customerFirstName, $signature);
            Mail::to($recipientEmail)->send($mailable);

            Log::info('Successfully sent ticket reply email via Laravel Mail SMTP', [
                'ticket_id'      => $ticket->id,
                'customer_email' => $recipientEmail,
            ]);

            return true;
        } catch (\Throwable $e) {
            report($e);
            Log::error('Failed to send ticket reply email to customer: ' . $e->getMessage(), [
                'ticket_id'      => $ticket->id,
                'reply_id'       => $reply->id,
                'customer_email' => $recipientEmail,
                'exception'      => $e,
            ]);

            return false;
        }
    }
}
