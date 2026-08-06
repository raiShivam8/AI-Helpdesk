<?php

namespace App\Jobs;

use App\Enums\Role;
use App\Enums\SenderType;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\TicketEmailService;
use Database\Seeders\AiAgentSeeder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoResolveTicketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(public Ticket $ticket) {}

    /**
     * Execute the job.
     */
    public function handle(GeminiService $geminiService, TicketEmailService $ticketEmailService): void
    {
        Log::info('Starting AI auto-resolution job execution', [
            'ticket_id' => $this->ticket->id,
            'subject'   => $this->ticket->subject,
            'status'    => $this->ticket->status->value,
        ]);

        // Prevent duplicate AI replies if the ticket has already been resolved by AI
        if ($this->ticket->ai_resolved_at !== null) {
            Log::info('Skipping AI auto-resolution: Ticket has already been resolved by AI.', [
                'ticket_id'      => $this->ticket->id,
                'ai_resolved_at' => $this->ticket->ai_resolved_at,
            ]);
            return;
        }

        // Resolve the AI agent user
        $aiAgent = User::withTrashed()->where('email', AiAgentSeeder::EMAIL)->first();

        try {
            // Update ticket status to processing during analysis
            $this->ticket->update([
                'status'            => TicketStatus::Processing,
                'assigned_agent_id' => $this->ticket->assigned_agent_id ?: $aiAgent?->id,
            ]);

            // Read knowledge base file
            $kbPath = base_path('knowledge-base.md');
            if (!file_exists($kbPath)) {
                Log::warning('Knowledge base file not found during auto-resolution. Keeping ticket open.', [
                    'ticket_id' => $this->ticket->id,
                ]);

                $this->ticket->update([
                    'status'            => TicketStatus::Open,
                    'assigned_agent_id' => $this->getHumanAgentIdFallback(),
                ]);
                return;
            }

            $kbContent = file_get_contents($kbPath);

            // Ask Gemini if it can resolve the ticket based on knowledge base
            $result = $geminiService->autoResolveTicket(
                $this->ticket->subject,
                $this->ticket->body,
                $kbContent,
                $this->ticket->sender_name
            );

            $canResolve = $result['can_resolve'] ?? false;
            $replyText  = $result['reply'] ?? null;

            if ($canResolve && !empty($replyText)) {
                // Create AI reply
                $reply = TicketReply::create([
                    'ticket_id'   => $this->ticket->id,
                    'user_id'     => $aiAgent?->id,
                    'body'        => $replyText,
                    'sender_type' => SenderType::System,
                ]);

                // Mark ticket resolved, save resolved_at and ai_resolved_at
                $now = now();
                $this->ticket->update([
                    'status'            => TicketStatus::Resolved,
                    'assigned_agent_id' => $aiAgent?->id ?: $this->ticket->assigned_agent_id,
                    'ai_resolved_at'    => $now,
                    'resolved_at'       => $now,
                ]);

                // Send reply email to customer
                $ticketEmailService->sendTicketReplyEmail($this->ticket, $reply);

                Log::info('Ticket auto-resolved successfully by AI and email sent to customer', [
                    'ticket_id'   => $this->ticket->id,
                    'reply_id'    => $reply->id,
                    'ai_agent_id' => $aiAgent?->id,
                ]);
            } else {
                // Gemini cannot answer: keep ticket open, do not create AI reply
                $this->ticket->update([
                    'status'            => TicketStatus::Open,
                    'assigned_agent_id' => $this->getHumanAgentIdFallback(),
                ]);

                Log::info('Gemini cannot answer ticket query. Ticket kept open without AI reply.', [
                    'ticket_id' => $this->ticket->id,
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
            Log::error('Exception occurred during ticket AI auto-resolution', [
                'ticket_id' => $this->ticket->id,
                'message'   => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            // Fallback: keep ticket open so it is not stuck in Processing
            try {
                $this->ticket->update([
                    'status'            => TicketStatus::Open,
                    'assigned_agent_id' => $this->getHumanAgentIdFallback(),
                ]);
            } catch (\Throwable $updateException) {
                report($updateException);
                Log::error('Failed to reset ticket status to Open after auto-resolution failure', [
                    'ticket_id' => $this->ticket->id,
                    'message'   => $updateException->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get human agent ID fallback for unassigned/unresolved tickets.
     */
    protected function getHumanAgentIdFallback(): ?int
    {
        if ($this->ticket->assigned_agent_id) {
            $aiAgent = User::withTrashed()->where('email', AiAgentSeeder::EMAIL)->first();
            if ($this->ticket->assigned_agent_id !== $aiAgent?->id) {
                return $this->ticket->assigned_agent_id;
            }
        }

        $humanAgent = User::withTrashed()
            ->where('role', Role::Agent)
            ->where('email', '!=', AiAgentSeeder::EMAIL)
            ->first();

        return $humanAgent?->id;
    }

    /**
     * Handle a permanent job failure.
     */
    public function failed(\Throwable $exception): void
    {
        report($exception);
        Log::error('AI ticket auto-resolution job failed permanently', [
            'ticket_id' => $this->ticket->id,
            'message'   => $exception->getMessage(),
        ]);

        try {
            $this->ticket->update([
                'status'            => TicketStatus::Open,
                'assigned_agent_id' => $this->getHumanAgentIdFallback(),
            ]);
        } catch (\Throwable $e) {
            // Ignore nested save failure
        }
    }
}
