<?php

namespace Tests\Feature;

use App\Enums\SenderType;
use App\Enums\TicketStatus;
use App\Jobs\TicketAutoResolveJob;
use App\Mail\TicketReplyMail;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\TicketEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LaravelMailIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\AiAgentSeeder::class);
    }

    /**
     * Test that an agent posting a reply emails the customer using Laravel Mail.
     */
    public function test_agent_reply_emails_customer_with_conversation_history(): void
    {
        Mail::fake();

        $agent = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'sender_name'  => 'Johnathan Wick',
            'sender_email' => 'johnathan@example.com',
            'subject'      => 'Database sync issue',
        ]);

        // Create an existing previous reply to test conversation history embedding
        TicketReply::create([
            'ticket_id'   => $ticket->id,
            'body'        => 'First initial inquiry from customer.',
            'sender_type' => SenderType::Customer,
        ]);

        $replyBody = 'We have resolved the database sync issue on server #4.';

        // Agent submits reply via web endpoint
        $response = $this->actingAs($agent)->post(route('tickets.replies.store', $ticket), [
            'body' => $replyBody,
        ]);

        $response->assertRedirect(route('tickets.show', $ticket));
        $response->assertSessionHas('success');

        // Assert database record was stored
        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id'   => $agent->id,
            'body'      => $replyBody,
        ]);

        // Assert Mail was sent
        Mail::assertSent(TicketReplyMail::class, function (TicketReplyMail $mail) use ($ticket, $replyBody) {
            $html = $mail->render();

            return $mail->hasTo('johnathan@example.com')
                && $mail->customerFirstName === 'Johnathan'
                && $mail->signature === 'Code With Mosh Support'
                && str_contains($html, 'Hi Johnathan,')
                && str_contains($html, 'Thank you for reaching out to our support team')
                && str_contains($html, $replyBody)
                && str_contains($html, 'Previous Conversation History')
                && str_contains($html, 'First initial inquiry from customer')
                && str_contains($html, 'Code With Mosh Support');
        });
    }

    /**
     * Test that Gemini auto-resolving a ticket emails the customer with SenderType::System.
     */
    public function test_ai_auto_resolved_ticket_emails_customer(): void
    {
        Mail::fake();

        $ticket = Ticket::factory()->create([
            'sender_name'    => 'Sarah Connor',
            'sender_email'   => 'sarah@example.com',
            'subject'        => 'Course access instructions',
            'body'           => 'How do I access the course videos after purchase?',
            'status'         => TicketStatus::Open,
            'ai_resolved_at' => null,
        ]);

        // Mock GeminiService
        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('autoResolveTicket')
                ->withAnyArgs()
                ->andReturn([
                    'can_resolve' => true,
                    'reply'       => 'To access course videos, navigate to Dashboard > Courses and click Start.',
                ]);
        });

        // Run auto-resolve job
        $job = new TicketAutoResolveJob($ticket);
        $job->handle(app(GeminiService::class), app(TicketEmailService::class));

        // Assert status updated to Resolved
        $this->assertEquals(TicketStatus::Resolved, $ticket->fresh()->status);

        // Assert reply created with SenderType::System
        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id'   => $ticket->id,
            'sender_type' => SenderType::System->value,
            'body'        => 'To access course videos, navigate to Dashboard > Courses and click Start.',
        ]);

        // Assert Mail sent to customer
        Mail::assertSent(TicketReplyMail::class, function (TicketReplyMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('sarah@example.com')
                && $mail->customerFirstName === 'Sarah'
                && str_contains($html, 'Hi Sarah,')
                && str_contains($html, 'To access course videos, navigate to Dashboard')
                && str_contains($html, 'Code With Mosh Support');
        });
    }

    /**
     * Test customer first name extraction logic across name variations.
     */
    public function test_customer_first_name_extraction(): void
    {
        $service = new TicketEmailService();

        $t1 = Ticket::factory()->make(['sender_name' => 'Michael Scott', 'sender_email' => 'mscott@example.com']);
        $this->assertEquals('Michael', $service->extractCustomerFirstName($t1));

        $t2 = Ticket::factory()->make(['sender_name' => 'Dwight', 'sender_email' => 'dwight@example.com']);
        $this->assertEquals('Dwight', $service->extractCustomerFirstName($t2));

        $t3 = Ticket::factory()->make(['sender_name' => '', 'sender_email' => 'jim.halpert@example.com']);
        $this->assertEquals('Jim Halpert', $service->extractCustomerFirstName($t3));
    }

    /**
     * Test that email sending failure is logged gracefully and does not prevent DB reply creation.
     */
    public function test_email_failure_logged_gracefully(): void
    {
        Mail::shouldReceive('to->send')->andThrow(new \Exception('SMTP connection failure'));

        $agent = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'sender_email' => 'resilient@example.com',
        ]);

        $response = $this->actingAs($agent)->post(route('tickets.replies.store', $ticket), [
            'body' => 'Testing email resilience when SMTP is down.',
        ]);

        $response->assertRedirect(route('tickets.show', $ticket));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'body'      => 'Testing email resilience when SMTP is down.',
        ]);
    }
}
