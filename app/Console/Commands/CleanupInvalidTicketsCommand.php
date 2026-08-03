<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\InboundEmailValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupInvalidTicketsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:cleanup-invalid {--force : Force deletion without confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely remove invalid tickets created from support emails, bot alerts, auto-replies, newsletters, and duplicate Message-IDs';

    /**
     * Execute the console command.
     */
    public function handle(InboundEmailValidationService $validator): int
    {
        $this->info('Starting cleanup of invalid tickets...');

        $deletedCount = 0;
        $tickets = Ticket::all();

        foreach ($tickets as $ticket) {
            $parsedData = [
                'sender_email' => $ticket->sender_email,
                'sender_name'  => $ticket->sender_name,
                'subject'      => $ticket->subject,
                'body'         => $ticket->body,
            ];

            $validation = $validator->validateInboundEmail($parsedData);

            $isInvalid = !$validation['is_valid'];
            $reason = $validation['reason'] ?? '';

            // Check for duplicate Message-IDs (keep earliest ticket)
            if (!$isInvalid && !empty($ticket->message_id)) {
                $earlierExists = Ticket::where('message_id', $ticket->message_id)
                    ->where('id', '<', $ticket->id)
                    ->exists();

                if ($earlierExists) {
                    $isInvalid = true;
                    $reason = 'Duplicate Message-ID (earlier ticket already exists)';
                }
            }

            if ($isInvalid) {
                $this->warn("Deleting invalid Ticket #{$ticket->id} [{$ticket->sender_email}] '{$ticket->subject}' - Reason: {$reason}");

                // Delete associated replies
                TicketReply::where('ticket_id', $ticket->id)->delete();

                // Delete ticket
                $ticket->delete();

                Log::info("Cleanup: Deleted invalid ticket #{$ticket->id}", [
                    'ticket_id'    => $ticket->id,
                    'sender_email' => $ticket->sender_email,
                    'subject'      => $ticket->subject,
                    'reason'       => $reason,
                ]);

                $deletedCount++;
            }
        }

        $this->info("Completed cleanup. Removed {$deletedCount} invalid ticket(s).");

        return Command::SUCCESS;
    }
}
