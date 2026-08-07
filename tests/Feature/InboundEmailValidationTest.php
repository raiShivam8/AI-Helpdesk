<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\InboundEmailValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundEmailValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation detects self-sent support emails as invalid.
     */
    public function test_validation_rejects_support_email(): void
    {
        $validator = new InboundEmailValidationService();

        $supportEmail = config('mail.support_email', 'srai80147@gmail.com');

        $result = $validator->validateInboundEmail([
            'sender_email' => $supportEmail,
            'sender_name'  => 'Support Team',
            'subject'      => 'Re: Help ticket',
            'body'         => 'Replying to user ticket...',
        ]);

        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString('support email address', $result['reason']);
    }

    /**
     * Test validation accepts customer emails.
     */
    public function test_validation_accepts_genuine_customer_query(): void
    {
        $validator = new InboundEmailValidationService();

        $result = $validator->validateInboundEmail([
            'sender_email' => 'customer.test@example.com',
            'sender_name'  => 'Customer Test',
            'subject'      => 'Unable to access my purchased course',
            'body'         => 'Hi support team, I purchased the course yesterday but cannot view the videos on my dashboard. Please help!',
        ]);

        $this->assertTrue($result['is_valid']);
        $this->assertNull($result['reason']);
    }

    /**
     * Test cleanup Artisan command removes self-sent support email tickets from database.
     */
    public function test_cleanup_command_removes_invalid_tickets(): void
    {
        // 1. Create genuine ticket
        $validTicket = Ticket::factory()->create([
            'sender_email' => 'real.customer@example.com',
            'subject'      => 'How do I reset my password?',
        ]);

        // 2. Create invalid ticket from support email
        $invalidTicket1 = Ticket::factory()->create([
            'sender_email' => config('mail.support_email', 'srai80147@gmail.com'),
            'subject'      => 'Re: Support inquiry',
        ]);

        // Run cleanup
        $this->artisan('tickets:cleanup-invalid --force')
            ->assertExitCode(0);

        // Genuine ticket exists
        $this->assertDatabaseHas('tickets', ['id' => $validTicket->id]);

        // Invalid ticket deleted
        $this->assertDatabaseMissing('tickets', ['id' => $invalidTicket1->id]);
    }
}
