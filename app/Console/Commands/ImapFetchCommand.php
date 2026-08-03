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
    protected $signature = 'imap:fetch {--all : Fetch recent inbox emails regardless of seen/unseen status} {--limit=20 : Maximum number of emails to fetch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch customer emails via IMAP and convert them into helpdesk tickets';

    /**
     * Execute the console command.
     */
    public function handle(ImapService $imapService): int
    {
        @ini_set('memory_limit', '512M');
        $onlyUnseen = !$this->option('all');
        $limit = (int) $this->option('limit') ?: 20;

        $this->info("Starting IMAP email fetch command (onlyUnseen: " . ($onlyUnseen ? 'true' : 'false') . ", limit: {$limit})...");

        $processedCount = $imapService->fetchUnreadEmails(null, $onlyUnseen, $limit);

        $this->info("Completed IMAP email fetch. Processed {$processedCount} email(s).");

        return Command::SUCCESS;
    }
}
