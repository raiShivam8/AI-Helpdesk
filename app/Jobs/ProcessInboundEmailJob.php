<?php

namespace App\Jobs;

use App\Actions\CreateTicketFromInboundEmailAction;
use App\Models\Ticket;
use App\Services\InboundEmailValidationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessInboundEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     *
     * @param array $parsedData
     */
    public function __construct(public array $parsedData) {}

    /**
     * Execute the job.
     */
    public function handle(
        InboundEmailValidationService $validator,
        CreateTicketFromInboundEmailAction $createTicketAction
    ): ?Ticket {
        $senderEmail = $this->parsedData['sender_email'] ?? '';
        $messageId   = $this->parsedData['message_id'] ?? null;
        $subject     = $this->parsedData['subject'] ?? '(No Subject)';

        Log::info('Processing queued inbound email job', [
            'sender_email' => $senderEmail,
            'message_id'   => $messageId,
            'subject'      => $subject,
        ]);

        if (empty($senderEmail)) {
            Log::warning('Skipping queued inbound email: Sender email is empty.', [
                'subject' => $subject,
            ]);
            return null;
        }

        // 1. Validate if email is a genuine customer query
        $validation = $validator->validateInboundEmail($this->parsedData);
        if (!$validation['is_valid']) {
            Log::info('Skipping queued inbound email: ' . $validation['reason'], [
                'sender_email' => $senderEmail,
                'subject'      => $subject,
                'reason'       => $validation['reason'],
            ]);
            return null;
        }

        // 2. Prevent duplicate ticket creation using Message-ID
        if (!empty($messageId) && Ticket::where('message_id', $messageId)->exists()) {
            Log::info('Skipping queued inbound email: Duplicate Message-ID already processed.', [
                'message_id'   => $messageId,
                'sender_email' => $senderEmail,
                'subject'      => $subject,
            ]);
            return null;
        }

        // 3. Create Ticket & initial reply, then trigger asynchronous AI processing
        $ticket = $createTicketAction->execute($this->parsedData);

        Log::info('Successfully processed queued inbound email into ticket', [
            'ticket_id'    => $ticket->id,
            'message_id'   => $ticket->message_id,
            'sender_email' => $ticket->sender_email,
            'subject'      => $ticket->subject,
        ]);

        return $ticket;
    }
}
