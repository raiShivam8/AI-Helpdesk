<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class InboundEmailValidationService
{
    /**
     * Determine whether an inbound email represents a customer request.
     *
     * Rules:
     * - Ignore ONLY emails sent from our support email (kundanrai8102002@gmail.com).
     * - Accept all other emails from customers.
     *
     * @param array $parsedData Array containing sender_email, sender_name, subject, body, etc.
     * @param mixed|null $message Optional raw IMAP message object
     * @return array{is_valid: bool, reason: string|null}
     */
    public function validateInboundEmail(array $parsedData, $message = null): array
    {
        $senderEmail = strtolower(trim($parsedData['sender_email'] ?? ''));

        // 1. Ensure sender email is present
        if (empty($senderEmail)) {
            return ['is_valid' => false, 'reason' => 'Sender email address is empty'];
        }

        // 2. Ignore ONLY emails sent from support email itself (self-sent)
        $supportEmails = array_filter(array_map(fn($e) => strtolower(trim((string) $e)), [
            config('imap.accounts.default.username'),
            env('IMAP_USERNAME'),
            config('mail.support_email'),
            env('SUPPORT_EMAIL'),
            'kundanrai8102002@gmail.com',
        ]));

        if (in_array($senderEmail, array_unique($supportEmails), true)) {
            return ['is_valid' => false, 'reason' => 'Sender email matches support email address (self-sent)'];
        }

        // Accept all other customer emails
        return ['is_valid' => true, 'reason' => null];
    }
}
