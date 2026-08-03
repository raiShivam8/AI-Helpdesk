<?php

namespace App\Services;

use App\Actions\CreateTicketFromInboundEmailAction;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;
use Webklex\PHPIMAP\Client as ImapClient;

class ImapService
{
    public function __construct(
        protected CreateTicketFromInboundEmailAction $createTicketAction,
        protected InboundEmailValidationService $validator
    ) {}

    /**
     * Fetch unread/unseen emails from IMAP inbox, filter out self-sent/auto-generated/duplicate emails,
     * create helpdesk tickets, mark emails as read, and dispatch background classification & auto-resolution jobs.
     *
     * @param ImapClient|null $client Optional client instance for dependency injection / testing
     * @return int Number of processed emails
     */
    public function fetchUnreadEmails(?ImapClient $client = null, bool $onlyUnseen = true, int $limit = 20): int
    {
        @set_time_limit(180);
        @ini_set('max_execution_time', '180');
        @ini_set('memory_limit', '512M');
        Log::info('Starting IMAP email fetch process', ['only_unseen' => $onlyUnseen, 'limit' => $limit]);

        try {
            $client = $client ?? Client::account('default');

            if (!$client->isConnected()) {
                $client->connect();
            }

            /** @var \Webklex\PHPIMAP\Folder $folder */
            $folder = $client->getFolder('INBOX');

            /** @var \Webklex\PHPIMAP\Support\MessageCollection $messages */
            if ($onlyUnseen) {
                $messages = $folder->query()->unseen()->setFetchOrder('desc')->limit($limit)->get();
                if ($messages->count() === 0) {
                    $messages = $folder->query()->all()->setFetchOrder('desc')->limit($limit)->get();
                }
            } else {
                $messages = $folder->query()->all()->setFetchOrder('desc')->limit($limit)->get();
            }

            $count = 0;

            foreach ($messages as $message) {
                try {
                    $parsed = $this->parseEmailMessage($message);
                    $senderEmail = $parsed['sender_email'] ?? '';

                    if (empty($senderEmail)) {
                        Log::warning('Skipping IMAP email: Unable to extract valid sender email address.', [
                            'subject' => $parsed['subject'] ?? null,
                        ]);
                        $message->setFlag('Seen');
                        continue;
                    }

                    // 1. Validate if email is a genuine customer support query (filters self-sent, bots, marketing, newsletters, system alerts)
                    $validation = $this->validator->validateInboundEmail($parsed, $message);
                    if (!$validation['is_valid']) {
                        Log::info('Skipping IMAP email: ' . $validation['reason'], [
                            'sender_email' => $senderEmail,
                            'subject'      => $parsed['subject'] ?? null,
                            'reason'       => $validation['reason'],
                        ]);
                        $message->setFlag('Seen');
                        continue;
                    }

                    // 2. Prevent duplicate ticket creation using Message-ID
                    if ($this->isDuplicateMessage($parsed['message_id'] ?? null)) {
                        Log::info('Skipping IMAP email: Duplicate Message-ID already processed.', [
                            'message_id'   => $parsed['message_id'],
                            'sender_email' => $senderEmail,
                            'subject'      => $parsed['subject'] ?? null,
                        ]);
                        $message->setFlag('Seen');
                        continue;
                    }

                    // 3. Create ticket & initial reply via Action, then mark email as read ONLY on success
                    $ticket = $this->createTicketFromInbound($parsed);
                    $message->setFlag('Seen');

                    $count++;
                    Log::info('Successfully processed IMAP email into ticket', [
                        'ticket_id'    => $ticket->id,
                        'message_id'   => $ticket->message_id,
                        'sender_email' => $ticket->sender_email,
                        'subject'      => $ticket->subject,
                    ]);
                } catch (\Throwable $msgEx) {
                    report($msgEx);
                    Log::error('Failed to process individual IMAP email: ' . $msgEx->getMessage(), [
                        'exception' => $msgEx,
                    ]);
                    // Note: Do NOT set 'Seen' flag if processing failed so it can be inspected / retried.
                }
            }

            Log::info("IMAP unread email fetch process completed. Processed {$count} emails.");

            return $count;
        } catch (\Throwable $e) {
            report($e);
            Log::error('Failed to execute IMAP unread email fetch: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return 0;
        }
    }

    /**
     * Check if sender email matches configured support email addresses (self-sent email).
     */
    public function isSelfSentEmail(?string $senderEmail): bool
    {
        if (empty($senderEmail)) {
            return false;
        }

        $validation = $this->validator->validateInboundEmail(['sender_email' => $senderEmail]);

        return !$validation['is_valid'] && str_contains($validation['reason'] ?? '', 'support email address');
    }

    /**
     * Check if email is an auto-generated, system notification, or bounce email.
     */
    public function isAutoGeneratedEmail(array $parsedData, $message = null): bool
    {
        $validation = $this->validator->validateInboundEmail($parsedData, $message);

        return !$validation['is_valid'];
    }

    /**
     * Check if a ticket with this Message-ID has already been created.
     */
    public function isDuplicateMessage(?string $messageId): bool
    {
        if (empty($messageId)) {
            return false;
        }

        return Ticket::where('message_id', $messageId)->exists();
    }

    /**
     * Extract Message-ID header from raw IMAP message object.
     */
    public function extractMessageId($message): ?string
    {
        $messageId = null;

        try {
            $messageId = (string) $message->getMessageId();
        } catch (\Throwable $e) {
            try {
                $messageId = (string) ($message->getHeader()?->get('message-id')?->first() ?? '');
            } catch (\Throwable $hEx) {
                // Header fallback ignored
            }
        }

        $messageId = trim((string) $messageId);

        return !empty($messageId) ? $messageId : null;
    }

    /**
     * Parse raw IMAP message object into normalized data array.
     */
    public function parseEmailMessage($message): array
    {
        $senderEmail = null;
        $senderName = null;

        $from = $message->getFrom();
        if (!empty($from) && is_array($from)) {
            $firstFrom = $from[0] ?? null;
            if ($firstFrom) {
                $senderEmail = $firstFrom->mail ?? null;
                $senderName  = $firstFrom->personal ?? null;
            }
        }

        if (empty($senderEmail)) {
            try {
                $rawFrom = (string) ($message->getHeader()?->get('from')?->first() ?? '');
                if (preg_match('/^(.*?)\s*<([^>]+)>$/', trim($rawFrom), $matches)) {
                    $senderName = $senderName ?: trim($matches[1], ' "\'');
                    $senderEmail = trim($matches[2]);
                } elseif (filter_var(trim($rawFrom), FILTER_VALIDATE_EMAIL)) {
                    $senderEmail = trim($rawFrom);
                }
            } catch (\Throwable $e) {
                // Fallback header parsing ignored
            }
        }

        if (empty($senderName)) {
            if ($senderEmail && str_contains($senderEmail, '@')) {
                $localPart = explode('@', $senderEmail)[0];
                $senderName = ucwords(str_replace(['.', '_', '-'], ' ', $localPart));
            } else {
                $senderName = 'Customer';
            }
        }

        $subject = '';
        try {
            $rawSubject = trim((string) $message->getSubject());
            if (!empty($rawSubject)) {
                if (function_exists('mb_decode_mimeheader') && str_contains(strtolower($rawSubject), '=?')) {
                    $subject = trim((string) mb_decode_mimeheader($rawSubject));
                } elseif (function_exists('iconv_mime_decode') && str_contains(strtolower($rawSubject), '=?')) {
                    $subject = trim((string) iconv_mime_decode($rawSubject, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8'));
                } else {
                    $subject = $rawSubject;
                }
            }
        } catch (\Throwable $e) {
            $subject = '(No Subject)';
        }

        if (empty($subject)) {
            $subject = '(No Subject)';
        }

        $body = '';
        try {
            if (method_exists($message, 'hasTextBody') && $message->hasTextBody()) {
                $body = trim((string) $message->getTextBody());
            } elseif (method_exists($message, 'hasHTMLBody') && $message->hasHTMLBody()) {
                $body = trim(strip_tags((string) $message->getHTMLBody()));
            } else {
                $body = trim((string) ($message->getTextBody() ?? ''));
            }
        } catch (\Throwable $e) {
            $body = '(No content)';
        }

        if (empty($body)) {
            $body = '(No content)';
        }

        $receivedDate = null;
        try {
            if (method_exists($message, 'getDate')) {
                $dateVal = $message->getDate();
                if ($dateVal instanceof \DateTimeInterface || $dateVal instanceof \Carbon\CarbonInterface) {
                    $receivedDate = \Illuminate\Support\Carbon::instance($dateVal);
                } elseif (!empty($dateVal)) {
                    $receivedDate = \Illuminate\Support\Carbon::parse($dateVal);
                }
            }
        } catch (\Throwable $e) {
            $receivedDate = now();
        }

        $attachments = [];
        try {
            if (method_exists($message, 'hasAttachments') && $message->hasAttachments()) {
                foreach ($message->getAttachments() as $attachment) {
                    $attachments[] = [
                        'name' => $attachment->getName(),
                        'mime' => $attachment->getMimeType(),
                        'size' => $attachment->getSize(),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Ignore attachment extraction errors
        }

        $messageId = $this->extractMessageId($message);

        return [
            'message_id'    => $messageId,
            'sender_email'  => $senderEmail ?? '',
            'sender_name'   => $senderName,
            'subject'       => $subject,
            'body'          => $body,
            'received_date' => $receivedDate ?? now(),
            'attachments'   => $attachments,
        ];
    }

    /**
     * Delegate ticket creation to CreateTicketFromInboundEmailAction.
     */
    public function createTicketFromInbound(array $parsedData): Ticket
    {
        return $this->createTicketAction->execute($parsedData);
    }
}
