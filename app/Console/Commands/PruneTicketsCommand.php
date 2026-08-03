<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;

class PruneTicketsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:prune {--keep=100 : The number of latest tickets to retain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune older tickets to retain only the specified number of latest tickets';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $keep = (int) $this->option('keep');

        if ($keep <= 0) {
            $this->error('The --keep option must be a positive integer.');
            return self::FAILURE;
        }

        $totalCount = Ticket::count();

        if ($totalCount <= $keep) {
            $this->info("Current ticket count ({$totalCount}) is within the limit of {$keep}. No tickets removed.");
            return self::SUCCESS;
        }

        $keepIds = Ticket::orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->take($keep)
            ->pluck('id');

        $deletedCount = Ticket::whereNotIn('id', $keepIds)->delete();

        $this->info("Successfully pruned {$deletedCount} older ticket(s). Exactly {$keep} tickets remain in the ticket section.");

        return self::SUCCESS;
    }
}
