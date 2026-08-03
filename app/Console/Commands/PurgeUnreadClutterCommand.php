<?php

namespace App\Console\Commands;

use App\Services\ImapService;
use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;

class PurgeUnreadClutterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:purge-unread-clutter {--all-read : Mark all remaining unread inbox emails as read}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all unread inbox emails into tickets if valid customer queries, and mark non-customer clutter as read';

    /**
     * Execute the console command.
     */
    public function handle(ImapService $imapService): int
    {
        @ini_set('memory_limit', '512M');
        $this->info('Starting Gmail unread inbox processing & clutter cleanup...');

        // 1. Process unread emails via ImapService in batches until 0 remain
        $totalProcessed = 0;
        do {
            $processedCount = $imapService->fetchUnreadEmails();
            $totalProcessed += $processedCount;
        } while ($processedCount > 0);

        $this->info("Completed ticket processing. Converted {$totalProcessed} valid customer query email(s) into tickets.");

        // 2. If --all-read flag is set, mark any remaining unread inbox clutter as read (Seen)
        if ($this->option('all-read')) {
            $this->info('Marking any remaining unread inbox clutter as read (Seen)...');

            try {
                $client = Client::account('default');
                if (!$client->isConnected()) {
                    $client->connect();
                }

                $folder = $client->getFolder('INBOX');
                $unseenMessages = $folder->query()->unseen()->limit(50)->get();

                $clearedCount = 0;
                foreach ($unseenMessages as $message) {
                    $message->setFlag('Seen');
                    $clearedCount++;
                }

                $this->info("Marked {$clearedCount} unread email(s) as read.");
            } catch (\Throwable $e) {
                $this->error('Failed to clear unread inbox clutter: ' . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
