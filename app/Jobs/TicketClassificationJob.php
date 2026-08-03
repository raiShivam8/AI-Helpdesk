<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\GeminiService;
use App\Enums\TicketCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job: TicketClassificationJob
 *
 * Asynchronously classifies an inbound support ticket using the Gemini API.
 * Sets the category, category_confidence, and classified_at fields.
 */
class TicketClassificationJob implements ShouldQueue
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
    public function __construct(
        public Ticket $ticket
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GeminiService $geminiService): void
    {
        Log::info('Starting AI ticket classification', [
            'ticket_id' => $this->ticket->id,
            'subject' => $this->ticket->subject,
        ]);

        // Call Gemini API to classify
        $result = $geminiService->classifyTicket(
            $this->ticket->subject,
            $this->ticket->body
        );

        $categoryStr = $result['category'] ?? null;
        $confidence = $result['confidence'] ?? 0.0;

        // Map classification category string to our Laravel TicketCategory enum cases
        $mappedCategory = match ($categoryStr) {
            'Technical Issue' => TicketCategory::TechnicalSupport,
            'Billing' => TicketCategory::Billing,
            'Account' => TicketCategory::Account,
            'Refund' => TicketCategory::RefundRequest,
            'General Question' => TicketCategory::GeneralQuestion,
            'Spam/Gibberish' => TicketCategory::Spam,
            default => TicketCategory::GeneralInquiry,
        };

        // Form a simple AI summary
        $aiSummary = "This ticket was automatically classified as '{$categoryStr}' with a confidence score of " . round($confidence * 100, 2) . "%.";

        // Save classifications
        $this->ticket->update([
            'category' => $mappedCategory,
            'category_confidence' => $confidence,
            'ai_summary' => $aiSummary,
            'classified_at' => now(),
        ]);

        Log::info('AI ticket classification completed successfully', [
            'ticket_id' => $this->ticket->id,
            'category' => $mappedCategory->value,
            'confidence' => $confidence,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AI ticket classification job failed permanently', [
            'ticket_id' => $this->ticket->id,
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
