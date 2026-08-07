<?php

namespace App\Services;

use App\Actions\CreateTicketFromInboundEmailAction;
use App\Models\Ticket;
use App\Jobs\ProcessInboundEmailJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;
use Webklex\PHPIMAP\Client as ImapClient;

class ImapService
{
    public const UID_CACHE_KEY = 'imap_last_processed_uid';

    public function __construct(
        protected CreateTicketFromInboundEmailAction $createTicketAction,
        protected InboundEmailValidationService $validator
    ) {}

    /**
     * Get the last processed IMAP message UID from storage.
     */
    public function getLastProcessedUid(): int
    {
        return (int) Cache::get(self::UID_CACHE_KEY, 0);
    }

    /**
     * Set the last processed IMAP message UID in storage.
     */
    public function setLastProcessedUid(int $uid): void
    {
        Cache::forever(self::UID_CACHE_KEY, $uid);
    }

    /**
     * Reset the stored last processed IMAP message UID.
     */
    public function resetLastProcessedUid(): void
    {
        Cache::forget(self::UID_CACHE_KEY);
    }

    /**
     * Fetch new emails from IMAP inbox using UID-based sequence range.
     * Dispatches ProcessInboundEmailJob to queue and marks emails as Seen.
     *
     * @param ImapClient|null $client Optional client instance for dependency injection / testing
     * @return int Number of processed/queued emails
     */
    public function fetchUnreadEmails(?ImapClient $client = null, bool $onlyUnseen = true, int $limit = 20, bool $syncJobs = false): int
    {
        @set_time_limit(180);
        @ini_set('max_execution_time', '180');
        @ini_set('memory_limit', '512M');

        $lastUid = $this->getLastProcessedUid();
        Log::info('Starting IMAP email fetch process', [
            'only_unseen' => $onlyUnseen,
            'limit'       => $limit,
            'last_uid'    => $lastUid,
        ]);

        try {
            $client = $client ?? Client::account('default');

            if (!$client->isConnected()) {
                $client->connect();
            }

            /** @var \Webklex\PHPIMAP\Folder $folder */
            $folder = $client->getFolder('INBOX');

            /** @var \Webklex\PHPIMAP\Support\MessageCollection $messages */
            $messages = [];
            if ($lastUid > 0) {
                try {
                    // Ultra-fast ID query: check UIDs first without downloading message bodies
                    $uidList = $folder->query()->where("CUSTOM UID " . ($lastUid + 1) . ":*")->pluck('uid')->toArray();
                    $newUids = array_filter(array_map('intval', (array) $uidList), fn($u) => $u > $lastUid);

                    if (empty($newUids)) {
                        // Zero new emails exist — finish instantly in < 0.1s
                        $execTimeMs = round((microtime(true) - $startTime) * 1000, 2);
                        Log::info("IMAP fetch completed (0 new emails): {$execTimeMs}ms, lastUid: {$lastUid}");
                        return [
                            'count'             => 0,
                            'last_uid'          => $lastUid,
                            'execution_ms'      => $execTimeMs,
                            'new_tickets_count' => 0,
                        ];
                    }

                    // Fetch ONLY the newly arrived message UIDs
                    $targetUids = array_slice(array_values($newUids), 0, $limit);
                    $messages   = $folder->query()->whereIn('UID', $targetUids)->setFetchOrder('asc')->get();
                } catch (\Throwable $e) {
                    Log::warning('IMAP fast UID search warning, falling back to unseen query: ' . $e->getMessage());
                    $messages = $folder->query()->unseen()->setFetchOrder('asc')->limit($limit)->get();
                }
            } elseif ($onlyUnseen) {
                $messages = $folder->query()->unseen()->setFetchOrder('asc')->limit($limit)->get();
            } else {
                $messages = $folder->query()->all()->setFetchOrder('asc')->limit($limit)->get();
            }

            $count = 0;
            $maxUid = $lastUid;

            foreach ($messages as $message) {
                try {
                    $uid = 0;
                    try {
                        $uid = (int) $message->getUid();
                    } catch (\Throwable $uidEx) {
                        // ignore UID extraction error
                    }

                    if ($lastUid > 0 && $uid > 0 && $uid <= $lastUid) {
                        continue;
                    }

                    $parsed = $this->parseEmailMessage($message);
                    $senderEmail = $parsed['sender_email'] ?? '';

                    if (empty($senderEmail)) {
                        Log::warning('Skipping IMAP email: Unable to extract valid sender email address.', [
                            'subject' => $parsed['subject'] ?? null,
                            'uid'     => $uid,
                        ]);
                        $message->setFlag('Seen');
                        if ($uid > $maxUid) {
                            $maxUid = $uid;
                        }
                        continue;
                    }

                    // 1. Validate if email is a genuine customer support query
                    $validation = $this->validator->validateInboundEmail($parsed, $message);
                    if (!$validation['is_valid']) {
                        Log::info('Skipping IMAP email: ' . $validation['reason'], [
                            'sender_email' => $senderEmail,
                            'subject'      => $parsed['subject'] ?? null,
                            'reason'       => $validation['reason'],
                            'uid'          => $uid,
                        ]);
                        $message->setFlag('Seen');
                        if ($uid > $maxUid) {
                            $maxUid = $uid;
                        }
                        continue;
                    }

                    // 2. Prevent duplicate ticket creation using Message-ID
                    if ($this->isDuplicateMessage($parsed['message_id'] ?? null)) {
                        Log::info('Skipping IMAP email: Duplicate Message-ID already processed.', [
                            'message_id'   => $parsed['message_id'],
                            'sender_email' => $senderEmail,
                            'subject'      => $parsed['subject'] ?? null,
                            'uid'          => $uid,
                        ]);
                        $message->setFlag('Seen');
                        if ($uid > $maxUid) {
                            $maxUid = $uid;
                        }
                        continue;
                    }

                    // 3. Dispatch ProcessInboundEmailJob
                    if ($syncJobs) {
                        ProcessInboundEmailJob::dispatchSync($parsed);
                    } else {
                        ProcessInboundEmailJob::dispatch($parsed);
                    }
                    $message->setFlag('Seen');

                    if ($uid > $maxUid) {
                        $maxUid = $uid;
                    }

                    $count++;
                    Log::info('Dispatched inbound email processing job', [
                        'message_id'   => $parsed['message_id'] ?? null,
                        'sender_email' => $senderEmail,
                        'subject'      => $parsed['subject'] ?? null,
                        'uid'          => $uid,
                    ]);
                } catch (\Throwable $msgEx) {
                    report($msgEx);
                    Log::error('Failed to dispatch individual IMAP email job: ' . $msgEx->getMessage(), [
                        'exception' => $msgEx,
                    ]);
                }
            }

            if ($maxUid > $lastUid) {
                $this->setLastProcessedUid($maxUid);
            } elseif ($lastUid === 0) {
                try {
                    $latestMsg = $folder->query()->all()->setFetchOrder('desc')->limit(1)->get()->first();
                    if ($latestMsg) {
                        $latestUid = (int) $latestMsg->getUid();
                        if ($latestUid > 0) {
                            $this->setLastProcessedUid($latestUid);
                            $maxUid = $latestUid;
                        }
                    }
                } catch (\Throwable $uidInitEx) {
                    // Ignore initialization error
                }
            }

            Log::info("IMAP email fetch process completed. Processed/queued {$count} emails. New last_uid: {$maxUid}");

            return $count;
        } catch (\Throwable $e) {
            report($e);
            Log::error('Failed to execute IMAP email fetch: ' . $e->getMessage(), [
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
