<?php

namespace App\Console\Commands;

use App\Services\ImapService;
use Illuminate\Console\Command;

class ImapFetchCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imap:fetch {--all : Fetch recent inbox emails regardless of seen/unseen status} {--limit=20 : Maximum number of emails to fetch} {--reset-uid : Reset cached last processed IMAP UID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch customer emails via IMAP using UID sync and queue processing into helpdesk tickets';

    /**
     * Execute the console command.
     */
    public function handle(ImapService $imapService): int
    {
        @ini_set('memory_limit', '512M');
        $startTime = microtime(true);

        if ($this->option('reset-uid')) {
            $imapService->resetLastProcessedUid();
            $this->info('Reset cached last processed IMAP UID.');
        }

        $onlyUnseen = !$this->option('all');
        $limit = (int) $this->option('limit') ?: 20;

        $this->info("Starting IMAP email fetch command (onlyUnseen: " . ($onlyUnseen ? 'true' : 'false') . ", limit: {$limit}, lastUid: {$imapService->getLastProcessedUid()})...");

        $processedCount = $imapService->fetchUnreadEmails(null, $onlyUnseen, $limit);

        $duration = round(microtime(true) - $startTime, 3);

        $this->info("Completed IMAP email fetch in {$duration}s. Processed/queued {$processedCount} email(s).");

        return Command::SUCCESS;
    }
}
