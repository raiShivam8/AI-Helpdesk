<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Console\Command;

class PurgeDemoTicketsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:purge-demo {--force : Force execution without confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely purge fake/demo tickets and replies while keeping real IMAP customer tickets intact';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Scanning database for fake/demo ticket records...');

        // Query demo tickets created by factories/seeders (example.com/org/net or seeder demo patterns)
        $demoTicketsQuery = Ticket::where(function ($q) {
            $q->where('sender_email', 'like', '%@example.com')
              ->orWhere('sender_email', 'like', '%@example.org')
              ->orWhere('sender_email', 'like', '%@example.net')
              ->orWhere('sender_email', 'test@example.com')
              ->orWhere('subject', 'like', 'Inquiry about course enrollment%')
              ->orWhere('subject', 'like', 'Database connection timeout%')
              ->orWhere('subject', 'like', 'Refund request for duplicate%')
              ->orWhere('subject', 'like', 'Request for invoice copy%')
              ->orWhere('subject', 'like', 'Video player buffering issues%')
              ->orWhere('subject', 'like', 'Save changes button disabled%')
              ->orWhere('subject', 'like', 'Request for dark mode option%')
              ->orWhere('subject', 'like', 'Change account email address%')
              ->orWhere('subject', 'like', 'Corporate team training discounts%');
        });

        $totalDemoTickets = $demoTicketsQuery->count();

        if ($totalDemoTickets === 0) {
            $this->info('No demo/test tickets found in the database. Production database is clean.');
            return self::SUCCESS;
        }

        $this->warn("Found {$totalDemoTickets} demo/test tickets in the database.");

        if (!$this->option('force') && !$this->confirm('Do you want to proceed with purging these demo tickets?')) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $demoTicketIds = $demoTicketsQuery->pluck('id')->toArray();

        // 1. Delete associated replies
        $deletedReplies = TicketReply::whereIn('ticket_id', $demoTicketIds)->delete();

        // 2. Delete demo tickets
        $deletedTickets = Ticket::whereIn('id', $demoTicketIds)->delete();

        $this->info("Successfully purged {$deletedTickets} demo tickets and {$deletedReplies} associated replies.");
        $this->info("Remaining real tickets in database: " . Ticket::count());

        return self::SUCCESS;
    }
}
