<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundEmailWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure a secret token for testing
        config(['services.inbound_email.secret' => 'test-webhook-secret-token']);

        // Fake the queue to prevent job execution hitting real API
        \Illuminate\Support\Facades\Queue::fake();
    }

    /**
     * Test that a valid inbound email webhook successfully creates a ticket.
     */
    public function test_webhook_creates_ticket_successfully(): void
    {
        $payload = [
            'sender_email' => 'customer@example.com',
            'sender_name' => 'John Doe',
            'subject' => 'Issue with account billing',
            'body' => 'I was charged twice for this month subscription. Please assist.',
        ];

        $response = $this->postJson(route('webhooks.inbound-email'), $payload, [
            'X-Webhook-Token' => 'test-webhook-secret-token',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'ticket' => [
                'id',
                'sender_email',
                'sender_name',
                'subject',
                'body',
                'status',
                'created_at',
                'updated_at',
            ],
        ]);

        $this->assertDatabaseHas('tickets', [
            'sender_email' => 'customer@example.com',
            'sender_name' => 'John Doe',
            'subject' => 'Issue with account billing',
            'body' => 'I was charged twice for this month subscription. Please assist.',
            'status' => TicketStatus::New,
            'category' => null,
        ]);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\TicketClassificationJob::class);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\AutoResolveTicketJob::class);
    }

    /**
     * Test that inbound email webhook validation fails with invalid data.
     */
    public function test_webhook_validation_errors(): void
    {
        // 1. Missing required fields (email, name, subject, body)
        $payload = [];

        $response = $this->postJson(route('webhooks.inbound-email'), $payload, [
            'X-Webhook-Token' => 'test-webhook-secret-token',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sender_email', 'sender_name', 'subject', 'body']);

        // 2. Invalid email format
        $payload = [
            'sender_email' => 'not-an-email',
            'sender_name' => 'John Doe',
            'subject' => 'Billing issue',
            'body' => 'Details here',
        ];

        $response = $this->postJson(route('webhooks.inbound-email'), $payload, [
            'X-Webhook-Token' => 'test-webhook-secret-token',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sender_email']);
    }

    /**
     * Test that the webhook is exempted from CSRF token validation.
     */
    public function test_webhook_csrf_exemption(): void
    {
        $payload = [
            'sender_email' => 'guest@example.com',
            'sender_name' => 'Guest User',
            'subject' => 'CSRF Test',
            'body' => 'Testing if CSRF is disabled.',
        ];

        // Using standard post instead of postJson to ensure CSRF middleware would trigger if not exempted
        $response = $this->post('/api/webhooks/inbound-email', $payload, [
            'X-Webhook-Token' => 'test-webhook-secret-token',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tickets', [
            'sender_email' => 'guest@example.com',
            'subject' => 'CSRF Test',
        ]);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\TicketClassificationJob::class);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\AutoResolveTicketJob::class);
    }

    /**
     * Test that the webhook rejects requests with invalid or missing secret tokens.
     */
    public function test_webhook_rejects_unauthorized_token(): void
    {
        $payload = [
            'sender_email' => 'customer@example.com',
            'sender_name' => 'John Doe',
            'subject' => 'Unauthorized subject',
            'body' => 'Unauthorized body',
        ];

        // 1. Missing header
        $response = $this->postJson(route('webhooks.inbound-email'), $payload);
        $response->assertStatus(401);
        $response->assertJson(['error' => 'Unauthorized. Invalid webhook token.']);

        // 2. Invalid header
        $response2 = $this->postJson(route('webhooks.inbound-email'), $payload, [
            'X-Webhook-Token' => 'wrong-secret',
        ]);
        $response2->assertStatus(401);
        $response2->assertJson(['error' => 'Unauthorized. Invalid webhook token.']);

        // Assert no ticket was created
        $this->assertDatabaseMissing('tickets', [
            'sender_email' => 'customer@example.com',
        ]);
    }
}
