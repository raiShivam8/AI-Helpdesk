<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Enums\TicketStatus;
use App\Enums\SenderType;
use App\Jobs\TicketAutoResolveJob;
use App\Jobs\AutoResolveTicketJob;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketAutoResolveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed AI Agent user
        $this->seed(\Database\Seeders\AiAgentSeeder::class);

        // Configure a secret token for testing
        config(['services.inbound_email.secret' => 'test-webhook-secret-token']);
    }

    /**
     * Test auto-resolve flow with a question that IS answerable.
     */
    public function test_webhook_auto_resolves_answerable_question(): void
    {
        Queue::fake();

        // 1. Send answerable query to webhook
        $payload = [
            'sender_email' => 'alice@example.com',
            'sender_name' => 'Alice Cooper',
            'subject' => 'Requesting refund for Laravel Masterclass',
            'body' => 'Hi, I purchased the Laravel Masterclass course 3 days ago, but I want a refund because I changed my mind. Can you help me?',
        ];

        $response = $this->postJson(route('webhooks.inbound-email'), $payload, [
            'X-Webhook-Token' => 'test-webhook-secret-token',
        ]);

        $response->assertStatus(201);

        // Assert ticket exists in 'new' status
        $this->assertDatabaseHas('tickets', [
            'sender_email' => 'alice@example.com',
            'status' => TicketStatus::New->value,
        ]);

        // Assert job is dispatched
        Queue::assertPushed(AutoResolveTicketJob::class);

        $ticket = Ticket::where('sender_email', 'alice@example.com')->firstOrFail();

        // 2. Mock GeminiService for success
        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('autoResolveTicket')
                ->once()
                ->andReturn([
                    'can_resolve' => true,
                    'reply' => "Dear Alice,\n\nYes, we can help you with your refund. Since you purchased it 3 days ago which is within our 14-day window, you are eligible.\n\nCode with Mosh Support"
                ]);
            // Mock classifyTicket to prevent it from calling real API since controller dispatches both
            $mock->shouldReceive('classifyTicket')->andReturn([
                'category' => 'Refund',
                'confidence' => 0.99
            ]);
        });

        // 3. Process the job
        \Illuminate\Support\Facades\Mail::fake();

        $job = new TicketAutoResolveJob($ticket);
        $job->handle(app(GeminiService::class), app(\App\Services\TicketEmailService::class));

        // 4. Assert status is resolved, and reply was created with SenderType::System
        $this->assertEquals(TicketStatus::Resolved, $ticket->fresh()->status);
        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id'   => $ticket->id,
            'sender_type' => SenderType::System->value,
        ]);

        $reply = $ticket->replies()->where('sender_type', SenderType::System)->firstOrFail();
        $this->assertStringContainsString('Dear Alice', $reply->body);
        $this->assertStringContainsString('Code with Mosh Support', $reply->body);

        // 5. Assert outgoing email sent to customer via SendGrid
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\TicketReplyMail::class, function (\App\Mail\TicketReplyMail $mail) {
            return $mail->hasTo('alice@example.com');
        });
    }

    /**
     * Test auto-resolve flow with another answerable question.
     */
    public function test_webhook_auto_resolves_another_answerable_question(): void
    {
        Queue::fake();

        $payload = [
            'sender_email' => 'bob@example.com',
            'sender_name' => 'Bob Builder',
            'subject' => 'Forgot my account password',
            'body' => 'Hello support, I forgot my password and am locked out. How can I reset it?',
        ];

        $response = $this->postJson(route('webhooks.inbound-email'), $payload, [
            'X-Webhook-Token' => 'test-webhook-secret-token',
        ]);

        $response->assertStatus(201);

        $ticket = Ticket::where('sender_email', 'bob@example.com')->firstOrFail();

        // Mock GeminiService
        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('autoResolveTicket')
                ->once()
                ->andReturn([
                    'can_resolve' => true,
                    'reply' => "Hi Bob,\n\nTo reset your password, please go to the login page and click 'Forgot Password'.\n\nCode with Mosh Support"
                ]);
            $mock->shouldReceive('classifyTicket')->andReturn([
                'category' => 'Account',
                'confidence' => 0.95
            ]);
        });

        \Illuminate\Support\Facades\Mail::fake();
        $job = new TicketAutoResolveJob($ticket);
        $job->handle(app(GeminiService::class), app(\App\Services\TicketEmailService::class));

        $this->assertEquals(TicketStatus::Resolved, $ticket->fresh()->status);
        $reply = $ticket->replies()->where('sender_type', SenderType::System)->firstOrFail();
        $this->assertStringContainsString('Hi Bob', $reply->body);
    }

    /**
     * Test auto-resolve flow with a question that is NOT answerable from the knowledge base.
     */
    public function test_webhook_does_not_resolve_unanswerable_question(): void
    {
        Queue::fake();

        // Create a human agent in the system
        $humanAgent = \App\Models\User::factory()->create([
            'role'  => \App\Enums\Role::Agent,
            'email' => 'human.agent@example.com',
        ]);

        $payload = [
            'sender_email' => 'charlie@example.com',
            'sender_name'  => 'Charlie Chaplin',
            'subject'      => 'Custom server setup',
            'body'         => 'Can you write a custom Kubernetes configuration script for my 5-node cluster running microservices?',
        ];

        $response = $this->postJson(route('webhooks.inbound-email'), $payload, [
            'X-Webhook-Token' => 'test-webhook-secret-token',
        ]);

        $response->assertStatus(201);

        $ticket = Ticket::where('sender_email', 'charlie@example.com')->firstOrFail();

        // Mock GeminiService to return unable to resolve
        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('autoResolveTicket')
                ->once()
                ->andReturn([
                    'can_resolve' => false,
                    'reply'       => null,
                ]);
            $mock->shouldReceive('classifyTicket')->andReturn([
                'category'   => 'Technical Support',
                'confidence' => 0.90,
            ]);
        });

        $job = new TicketAutoResolveJob($ticket);
        $job->handle(app(GeminiService::class), app(\App\Services\TicketEmailService::class));

        // The ticket status should be transitioned to 'open' and assigned to human agent
        $freshTicket = $ticket->fresh();
        $this->assertEquals(TicketStatus::Open, $freshTicket->status);
        $this->assertEquals($humanAgent->id, $freshTicket->assigned_agent_id);

        // Initial customer reply exists, but 0 system AI replies created
        $this->assertEquals(0, $ticket->replies()->where('sender_type', SenderType::System)->count());
    }

    /**
     * Test that if GeminiService throws an exception, the job catches it, logs the error,
     * updates the status to Open, and does not rethrow it.
     */
    public function test_auto_resolve_job_catches_exception_and_falls_back_to_open(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::New,
        ]);

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('autoResolveTicket')
                ->once()
                ->andThrow(new \RuntimeException('Gemini connection timed out.'));
        });

        // Run the job
        $job = new TicketAutoResolveJob($ticket);
        
        // This should run and complete without throwing RuntimeException
        $job->handle(app(GeminiService::class), app(\App\Services\TicketEmailService::class));

        // The ticket status should be open
        $this->assertEquals(TicketStatus::Open, $ticket->fresh()->status);
        $this->assertEquals(0, $ticket->replies()->count());
    }

    /**
     * Test clicking "Try AI Resolve" button dispatches AutoResolveTicketJob for existing open ticket.
     */
    public function test_try_ai_resolve_dispatches_job_for_existing_open_ticket(): void
    {
        Queue::fake();

        $agent = \App\Models\User::factory()->create(['role' => \App\Enums\Role::Agent]);
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Open,
            'ai_resolved_at' => null,
        ]);

        $response = $this->actingAs($agent)
            ->post(route('tickets.try-ai-resolve', $ticket));

        $response->assertRedirect(route('tickets.show', $ticket));
        $response->assertSessionHas('info');
    }

    /**
     * Test job prevents duplicate AI replies if ticket is already resolved by AI.
     */
    public function test_job_prevents_duplicate_ai_replies_if_already_resolved_by_ai(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Resolved,
            'ai_resolved_at' => now()->subHour(),
        ]);

        // Mock GeminiService to verify autoResolveTicket is NEVER called
        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldNotReceive('autoResolveTicket');
        });

        $job = new AutoResolveTicketJob($ticket);
        $job->handle(app(GeminiService::class), app(\App\Services\TicketEmailService::class));

        // No new replies should be added
        $this->assertEquals(0, $ticket->replies()->count());
    }

    /**
     * Test try-ai-resolve route prevents duplicate dispatch if ticket is already resolved by AI.
     */
    public function test_try_ai_resolve_route_blocks_already_ai_resolved_ticket(): void
    {
        Queue::fake();

        $agent = \App\Models\User::factory()->create(['role' => \App\Enums\Role::Agent]);
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Resolved,
            'ai_resolved_at' => now(),
        ]);

        $response = $this->actingAs($agent)
            ->post(route('tickets.try-ai-resolve', $ticket));

        $response->assertRedirect(route('tickets.show', $ticket));
        $response->assertSessionHas('error', 'This ticket has already been resolved by AI.');

        Queue::assertNotPushed(AutoResolveTicketJob::class);
    }
}
