<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Enums\TicketStatus;
use App\Enums\TicketCategory;
use App\Jobs\TicketClassificationJob;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure a secret token for testing
        config(['services.inbound_email.secret' => 'test-webhook-secret-token']);
    }

    /**
     * Test that the webhook dispatches the classification job, and running the job
     * successfully updates the category to General Question.
     */
    public function test_ticket_classification_flow(): void
    {
        // 1. Fake the queue to assert job is pushed
        Queue::fake();

        // Webhook payload as specified
        $payload = [
            'sender_email' => 'customer@example.com',
            'sender_name' => 'John Doe',
            'subject' => 'How does your support system work?',
            'body' => 'I have a general question about your service and features.',
        ];

        // Call the webhook
        $response = $this->postJson(route('webhooks.inbound-email'), $payload, [
            'X-Webhook-Token' => 'test-webhook-secret-token',
        ]);

        $response->assertStatus(201);

        // Verify ticket exists in the database with null category initially
        $this->assertDatabaseHas('tickets', [
            'subject' => 'How does your support system work?',
            'category' => null,
        ]);

        // Verify the job was pushed to the queue
        Queue::assertPushed(TicketClassificationJob::class);

        // 2. Fetch the created ticket and run the job manually with a mocked GeminiService
        $ticket = Ticket::where('subject', 'How does your support system work?')->firstOrFail();

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('classifyTicket')
                ->once()
                ->with('How does your support system work?', 'I have a general question about your service and features.')
                ->andReturn([
                    'category' => 'General Question',
                    'confidence' => 0.98,
                ]);
        });

        // Instantiate the job and execute it
        $job = new TicketClassificationJob($ticket);
        $job->handle(app(GeminiService::class));

        // 3. Verify database updates
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'category' => TicketCategory::GeneralQuestion->value,
            'category_confidence' => 0.98,
        ]);

        $this->assertNotNull($ticket->fresh()->classified_at);
        $this->assertNotNull($ticket->fresh()->ai_summary);
    }
}
