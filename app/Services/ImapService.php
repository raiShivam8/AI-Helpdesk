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

        $startTime = microtime(true);
        $lastUid = $this->getLastProcessedUid();
        Log::info('Starting IMAP email fetch process', [
            'only_unseen' => $onlyUnseen,
            'limit'       => $limit,
            'last_uid'    => $lastUid,
        ]);

        try {
            $client = $client ?? Client::account('default');

            $username = config('imap.accounts.default.username');
            $password = config('imap.accounts.default.password');
            if (empty($username) || empty($password)) {
                Log::error('IMAP fetch aborted: IMAP_USERNAME or IMAP_PASSWORD is not set in environment variables.');
                throw new \RuntimeException('IMAP credentials missing. Please configure MAIL_USERNAME and MAIL_PASSWORD (or IMAP_USERNAME / IMAP_PASSWORD) in your environment variables.');
            }

            if (!$client->isConnected()) {
                $client->connect();
            }

            /** @var \Webklex\PHPIMAP\Folder $folder */
            $folder = $client->getFolder('INBOX');

            /** @var \Webklex\PHPIMAP\Support\MessageCollection $messages */
            $messages = [];
            if ($lastUid > 0) {
                try {
                    // Fast UID query
                    $uidList = $folder->query()->where("CUSTOM UID " . ($lastUid + 1) . ":*")->pluck('uid')->toArray();
                    $newUids = array_filter(array_map('intval', (array) $uidList), fn($u) => $u > $lastUid);

                    if (!empty($newUids)) {
                        $targetUids = array_slice(array_values($newUids), 0, $limit);
                        $messages   = $folder->query()->whereIn('UID', $targetUids)->setFetchOrder('asc')->get();
                    } elseif ($onlyUnseen) {
                        $messages = $folder->query()->unseen()->setFetchOrder('asc')->limit($limit)->get();
                    }
                } catch (\Throwable $e) {
                    Log::warning('IMAP fast UID search warning: ' . $e->getMessage());
                    if ($onlyUnseen) {
                        $messages = $folder->query()->unseen()->setFetchOrder('asc')->limit($limit)->get();
                    }
                }
            } else {
                $messages = $folder->query()->unseen()->setFetchOrder('asc')->limit($limit)->get();
            }

            // Fallback to all() ONLY when explicitly requested ($onlyUnseen = false) and no messages found
            if (!$onlyUnseen && (empty($messages) || (method_exists($messages, 'count') && $messages->count() === 0))) {
                try {
                    $messages = $folder->query()->all()->setFetchOrder('desc')->limit($limit)->get();
                } catch (\Throwable $allEx) {
                    Log::warning('IMAP fallback to all messages failed: ' . $allEx->getMessage());
                }
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

                    // 1. Check if Message-ID already exists in database (prevent duplicate ticket creation)
                    $msgId = $this->extractMessageId($message);
                    if (!empty($msgId) && $this->isDuplicateMessage($msgId)) {
                        Log::info('Skipping IMAP email: Duplicate Message-ID already processed.', [
                            'message_id' => $msgId,
                            'uid'        => $uid,
                        ]);
                        $message->setFlag('Seen');
                        if ($uid > $maxUid) {
                            $maxUid = $uid;
                        }
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
        $bodyHtml = null;
        try {
            if (method_exists($message, 'hasHTMLBody')) {
                try {
                    if ($message->hasHTMLBody()) {
                        $bodyHtml = (string) $message->getHTMLBody();
                    }
                } catch (\Throwable $hEx) {
                    // HTML body extraction ignored if method unmocked
                }
            }

            if (method_exists($message, 'hasTextBody')) {
                try {
                    if ($message->hasTextBody()) {
                        $body = $this->cleanEmailBody((string) $message->getTextBody());
                    }
                } catch (\Throwable $tEx) {
                    // Text body extraction ignored if method unmocked
                }
            }

            if (empty($body) && !empty($bodyHtml)) {
                $body = $this->cleanEmailBody($bodyHtml);
            }

            if (empty($body)) {
                try {
                    $rawFallback = (string) ($message->getTextBody() ?? '');
                    $body = $this->cleanEmailBody($rawFallback);
                } catch (\Throwable $fEx) {
                    // Fallback ignored
                }
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
        $cidMap = [];
        try {
            $rawParts = [];
            
            // 1. Collect parts from getAttachments()
            if (method_exists($message, 'getAttachments')) {
                try {
                    $atts = $message->getAttachments();
                    if (is_iterable($atts)) {
                        foreach ($atts as $att) {
                            $rawParts[] = $att;
                        }
                    }
                } catch (\Throwable $attEx) {
                    Log::warning('IMAP getAttachments iterator warning: ' . $attEx->getMessage());
                }
            }

            // 2. Also collect parts from getParts() (captures inline image parts in Apple Mail, Outlook, Gmail apps)
            if (method_exists($message, 'getParts')) {
                try {
                    $parts = $message->getParts();
                    if (is_iterable($parts)) {
                        foreach ($parts as $part) {
                            $pMime = '';
                            if (method_exists($part, 'getMimeType')) {
                                $pMime = strtolower((string) $part->getMimeType());
                            } elseif (isset($part->mime)) {
                                $pMime = strtolower((string) $part->mime);
                            }
                            
                            // Skip plain text and main HTML body parts
                            if (in_array($pMime, ['text/plain', 'text/html', 'multipart/mixed', 'multipart/alternative', 'multipart/related'], true)) {
                                continue;
                            }

                            $rawParts[] = $part;
                        }
                    }
                } catch (\Throwable $partEx) {
                    Log::warning('IMAP getParts iterator warning: ' . $partEx->getMessage());
                }
            }

            // 3. Deduplicate raw parts by object hash / identifier to prevent duplicate downloads & disk writes
            $dedupedParts = [];
            $seenPartKeys = [];
            foreach ($rawParts as $partItem) {
                $partKey = is_object($partItem) ? spl_object_hash($partItem) : null;
                if ($partKey && in_array($partKey, $seenPartKeys, true)) {
                    continue;
                }
                if ($partKey) {
                    $seenPartKeys[] = $partKey;
                }
                $dedupedParts[] = $partItem;
            }
            $rawParts = $dedupedParts;

            $processedPaths = [];

            foreach ($rawParts as $attachment) {
                try {
                    $name = null;
                    if (method_exists($attachment, 'getName')) {
                        $name = $attachment->getName();
                    } elseif (isset($attachment->name)) {
                        $name = $attachment->name;
                    } elseif (isset($attachment->filename)) {
                        $name = $attachment->filename;
                    }

                    $mime = null;
                    if (method_exists($attachment, 'getMimeType')) {
                        $mime = $attachment->getMimeType();
                    } elseif (isset($attachment->mime)) {
                        $mime = $attachment->mime;
                    }
                    $mime = strtolower(trim($mime ?? ''));
                    if (empty($mime)) {
                        $mime = 'application/octet-stream';
                    }

                    // Deduce missing extension from mime type
                    $ext = strtolower(pathinfo($name ?? '', PATHINFO_EXTENSION));
                    if (empty($ext)) {
                        $ext = match ($mime) {
                            'image/jpeg', 'image/jpg' => 'jpg',
                            'image/png'               => 'png',
                            'image/gif'               => 'gif',
                            'image/webp'              => 'webp',
                            'image/svg+xml'           => 'svg',
                            'image/bmp'               => 'bmp',
                            'application/pdf'         => 'pdf',
                            default                   => '',
                        };
                    }

                    $name = $name ?: 'attachment_' . uniqid();
                    if (!empty($ext) && !str_ends_with(strtolower($name), '.' . $ext)) {
                        $name .= '.' . $ext;
                    }

                    $extension = !empty($ext) ? $ext : (pathinfo($name, PATHINFO_EXTENSION) ?: 'jpg');
                    $safeFilename = \Illuminate\Support\Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'image';
                    $fileNameOnDisk = uniqid() . '_' . $safeFilename . '.' . $extension;
                    $tmpFolder = storage_path('app/public/attachments');

                    if (!file_exists($tmpFolder)) {
                        mkdir($tmpFolder, 0777, true);
                    }

                    $fullSavedPath = $tmpFolder . '/' . $fileNameOnDisk;

                    // 1. Primary: Save directly using Webklex built-in saver (handles base64, 8bit binary & QP)
                    try {
                        if (method_exists($attachment, 'save')) {
                            $attachment->save($tmpFolder, $fileNameOnDisk);
                        }
                    } catch (\Throwable $saveEx) {
                        Log::warning('IMAP attachment save method error: ' . $saveEx->getMessage());
                    }

                    // 2. Fallback: If save() did not create the file, try getContent() or get()
                    if (!file_exists($fullSavedPath) || filesize($fullSavedPath) === 0) {
                        $content = null;
                        try {
                            if (method_exists($attachment, 'getContent')) {
                                $content = $attachment->getContent();
                            }
                        } catch (\Throwable $e) {}

                        if (empty($content) && isset($attachment->content)) {
                            $content = $attachment->content;
                        }

                        if (!empty($content) && is_string($content)) {
                            \Illuminate\Support\Facades\Storage::disk('public')->put('attachments/' . $fileNameOnDisk, $content);
                        }
                    }

                    $storagePath = 'attachments/' . $fileNameOnDisk;

                    if (file_exists($fullSavedPath) && filesize($fullSavedPath) > 0) {
                        $publicUrl = asset('storage/' . $storagePath);

                        $attachments[] = [
                            'name' => $name,
                            'mime' => $mime,
                            'path' => $storagePath,
                            'url'  => $publicUrl,
                        ];

                        // Extract Content-ID for CID inline images
                        $contentId = null;
                        if (method_exists($attachment, 'getContentId')) {
                            try { $contentId = $attachment->getContentId(); } catch (\Throwable $e) {}
                        }
                        if (empty($contentId) && isset($attachment->id)) {
                            $contentId = $attachment->id;
                        }
                        if (empty($contentId) && method_exists($attachment, 'getHeader')) {
                            try {
                                $headerObj = $attachment->getHeader();
                                if ($headerObj) {
                                    $cidHeader = $headerObj->get('content-id')?->first() 
                                              ?? $headerObj->get('content-id')
                                              ?? $headerObj->get('Content-ID');
                                    $contentId = (string) $cidHeader;
                                }
                            } catch (\Throwable $e) {}
                        }

                        if (!empty($contentId)) {
                            $cleanCid = trim((string) $contentId, '<>');
                            $cidMap[$cleanCid] = $publicUrl;
                            $cidNoHost = explode('@', $cleanCid)[0];
                            $cidMap[$cidNoHost] = $publicUrl;
                        }

                        if (!empty($name)) {
                            $cidMap[$name] = $publicUrl;
                            $cidMap[pathinfo($name, PATHINFO_FILENAME)] = $publicUrl;
                        }
                    }
                } catch (\Throwable $attEx) {
                    Log::warning('Failed to save email attachment during IMAP parse: ' . $attEx->getMessage());
                }
            }
        } catch (\Throwable $e) {
            // Ignore attachment extraction errors
        }

        // Replace inline cid: references in HTML body with actual public URLs
        if (!empty($bodyHtml) && !empty($cidMap)) {
            foreach ($cidMap as $cidKey => $url) {
                if (empty($cidKey)) continue;
                $bodyHtml = str_replace([
                    'cid:' . $cidKey,
                    'cid:<' . $cidKey . '>',
                    'cid:' . urlencode($cidKey),
                    'src="cid:' . $cidKey . '"',
                    "src='cid:" . $cidKey . "'",
                ], [
                    $url,
                    $url,
                    $url,
                    'src="' . $url . '"',
                    "src='" . $url . "'",
                ], $bodyHtml);
            }
        }

        // Catch-all fallback for any unmapped cid: images using available extracted attachments
        if (!empty($bodyHtml) && !empty($attachments) && preg_match_all('/src=["\']cid:([^"\'\s>]+)["\']/i', $bodyHtml, $cidMatches, PREG_SET_ORDER)) {
            foreach ($cidMatches as $index => $match) {
                $fallbackAtt = $attachments[$index] ?? $attachments[0] ?? null;
                if ($fallbackAtt && !empty($fallbackAtt['url'])) {
                    $bodyHtml = str_replace($match[0], 'src="' . $fallbackAtt['url'] . '"', $bodyHtml);
                }
            }
        }

        // Convert base64 inline images in body_html to stored public files
        if (!empty($bodyHtml) && preg_match_all('/src=["\']data:image\/([a-zA-Z0-9\+\-]+);base64,([^"\'\s>]+)["\']/i', $bodyHtml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                try {
                    $imgType = strtolower($match[1]);
                    $base64Data = base64_decode($match[2]);
                    if (!empty($base64Data)) {
                        $b64Filename = 'attachments/b64_' . uniqid() . '.' . ($imgType === 'jpeg' ? 'jpg' : $imgType);
                        \Illuminate\Support\Facades\Storage::disk('public')->put($b64Filename, $base64Data);
                        $b64Url = asset('storage/' . $b64Filename);

                        $bodyHtml = str_replace($match[0], 'src="' . $b64Url . '"', $bodyHtml);

                        $attachments[] = [
                            'name' => 'inline_image.' . $imgType,
                            'mime' => 'image/' . $imgType,
                            'path' => $b64Filename,
                            'url'  => $b64Url,
                        ];
                    }
                } catch (\Throwable $b64Ex) {
                    Log::warning('Failed to parse base64 image in email body: ' . $b64Ex->getMessage());
                }
            }
        }

        $messageId = $this->extractMessageId($message);

        return [
            'message_id'    => $messageId,
            'sender_email'  => $senderEmail ?? '',
            'sender_name'   => $senderName,
            'subject'       => $subject,
            'body'          => $body,
            'body_html'     => $bodyHtml,
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

    /**
     * Clean raw HTML or text body by removing <style>, <script>, <head> tags & contents,
     * stripping HTML tags, decoding entities, and eliminating residual CSS blocks.
     */
    public function cleanEmailBody(string $rawBody): string
    {
        if (empty(trim($rawBody))) {
            return '';
        }

        // 1. Remove <style>...</style> blocks and their content completely
        $cleaned = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $rawBody);

        // 2. Remove <script>...</script> blocks and their content
        $cleaned = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $cleaned);

        // 3. Remove <head>...</head> blocks and their content
        $cleaned = preg_replace('/<head\b[^>]*>.*?<\/head>/is', '', $cleaned);

        // 4. Remove HTML comments <!-- ... -->
        $cleaned = preg_replace('/<!--.*?-->/s', '', $cleaned);

        // 5. Convert common block-level HTML breaks to line breaks before stripping tags
        $cleaned = preg_replace('/<br\s*\/?>/i', "\n", $cleaned);
        $cleaned = preg_replace('/<\/p>/i', "\n\n", $cleaned);
        $cleaned = preg_replace('/<\/div>/i', "\n", $cleaned);
        $cleaned = preg_replace('/<\/tr>/i', "\n", $cleaned);
        $cleaned = preg_replace('/<\/li>/i', "\n", $cleaned);

        // 6. Strip all remaining HTML tags
        $cleaned = strip_tags($cleaned);

        // 7. Decode HTML entities
        $cleaned = html_entity_decode($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 8. Remove CSS @rules (like @media, @import, @keyframes) and their entire nested blocks
        $cleaned = preg_replace('/@(?:media|supports|import|charset|keyframes)[^{]+\{(?:[^{}]*\{[^}]*\}|[^{}])*\}/i', '', $cleaned);

        // 9. Remove residual single-line or multi-line CSS rules starting with common selectors
        $cleaned = preg_replace('/(?:\r?\n|^)\s*(?::root|html|body|table|td|th|a|img|p|span|div|tr|ul|li|\.[a-z0-9_-]+|#[a-z0-9_-]+|\*)\b[^{]*\{[^}]*\}/i', '', $cleaned);

        // 10. Remove orphaned braces left by nested queries or CSS blocks
        $cleaned = preg_replace('/^\s*[\{\}]\s*$/m', '', $cleaned);

        // 11. Normalize multiple newlines and trim whitespace
        $cleaned = preg_replace("/\n\s*\n\s*\n+/", "\n\n", $cleaned);

        return trim($cleaned);
    }
}

