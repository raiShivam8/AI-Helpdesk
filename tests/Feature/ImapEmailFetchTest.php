<?php

namespace Tests\Feature;

use App\Enums\SenderType;
use App\Enums\TicketStatus;
use App\Jobs\AutoResolveTicketJob;
use App\Jobs\TicketClassificationJob;
use App\Mail\TicketReplyMail;
use App\Models\Ticket;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\ImapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Webklex\PHPIMAP\Client as ImapClient;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Support\MessageCollection;

class ImapEmailFetchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test IMAP service parses email, finds/creates customer user, creates ticket & initial TicketReply,
     * marks as seen, and dispatches background jobs.
     */
    public function test_imap_service_fetches_unread_emails_and_creates_tickets(): void
    {
        Bus::fake([
            TicketClassificationJob::class,
            AutoResolveTicketJob::class,
        ]);

        $mockMessage = Mockery::mock(Message::class);
        $mockMessage->shouldReceive('getFrom')->andReturn([
            (object) [
                'mail' => 'customer.test@example.com',
                'personal' => 'Jane Smith',
            ],
        ]);
        $mockMessage->shouldReceive('getSubject')->andReturn('Help with billing issue');
        $mockMessage->shouldReceive('hasTextBody')->andReturn(true);
        $mockMessage->shouldReceive('getTextBody')->andReturn('I have a problem with my invoice payment.');
        $mockMessage->shouldReceive('getMessageId')->andReturn('<msg-101@example.com>');
        $mockMessage->shouldReceive('getUid')->byDefault()->andReturn(101);
        $mockMessage->shouldReceive('setFlag')->with('Seen')->once();

        $messageCollection = new MessageCollection([$mockMessage]);

        $mockQuery = Mockery::mock(WhereQuery::class);
        $mockQuery->shouldReceive('setFetchOrder')->andReturnSelf();
        $mockQuery->shouldReceive('unseen')->once()->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();
        $mockQuery->shouldReceive('get')->once()->andReturn($messageCollection);

        $mockFolder = Mockery::mock(Folder::class);
        $mockFolder->shouldReceive('query')->once()->andReturn($mockQuery);

        $mockClient = Mockery::mock(ImapClient::class);
        $mockClient->shouldReceive('isConnected')->andReturn(true);
        $mockClient->shouldReceive('getFolder')->with('INBOX')->once()->andReturn($mockFolder);

        $imapService = app(ImapService::class);
        $processedCount = $imapService->fetchUnreadEmails($mockClient);

        $this->assertEquals(1, $processedCount);

        $this->assertDatabaseHas('tickets', [
            'message_id'   => '<msg-101@example.com>',
            'sender_email' => 'customer.test@example.com',
            'sender_name'  => 'Jane Smith',
            'subject'      => 'Help with billing issue',
            'body'         => 'I have a problem with my invoice payment.',
            'status'       => TicketStatus::New->value,
        ]);

        $ticket = Ticket::firstOrFail();

        // Customer user found/created
        $this->assertDatabaseHas('users', [
            'email' => 'customer.test@example.com',
            'name'  => 'Jane Smith',
        ]);

        // Initial TicketReply created with sender_type = Customer
        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id'   => $ticket->id,
            'body'        => 'I have a problem with my invoice payment.',
            'sender_type' => SenderType::Customer->value,
        ]);

        Bus::assertDispatched(TicketClassificationJob::class, function ($job) use ($ticket) {
            return $job->ticket->id === $ticket->id;
        });

        Bus::assertDispatched(AutoResolveTicketJob::class, function ($job) use ($ticket) {
            return $job->ticket->id === $ticket->id;
        });
    }

    /**
     * Test IMAP service ignores self-sent emails from configured support email.
     */
    public function test_imap_service_ignores_self_sent_emails(): void
    {
        $supportEmail = config('mail.support_email', 'raishivamrai837@gmail.com');

        $mockMessage = Mockery::mock(Message::class);
        $mockMessage->shouldReceive('getFrom')->andReturn([
            (object) [
                'mail' => $supportEmail,
                'personal' => 'Support Agent',
            ],
        ]);
        $mockMessage->shouldReceive('getSubject')->andReturn('Re: Customer inquiry reply');
        $mockMessage->shouldReceive('hasTextBody')->andReturn(true);
        $mockMessage->shouldReceive('getTextBody')->andReturn('Replying to customer...');
        $mockMessage->shouldReceive('getMessageId')->andReturn('<msg-self@example.com>');
        $mockMessage->shouldReceive('getUid')->byDefault()->andReturn(102);
        $mockMessage->shouldReceive('setFlag')->with('Seen')->once();

        $messageCollection = new MessageCollection([$mockMessage]);
        $mockQuery = Mockery::mock(WhereQuery::class);
        $mockQuery->shouldReceive('setFetchOrder')->andReturnSelf();
        $mockQuery->shouldReceive('unseen')->once()->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();
        $mockQuery->shouldReceive('get')->once()->andReturn($messageCollection);
        $mockFolder = Mockery::mock(Folder::class);
        $mockFolder->shouldReceive('query')->once()->andReturn($mockQuery);
        $mockClient = Mockery::mock(ImapClient::class);
        $mockClient->shouldReceive('isConnected')->andReturn(true);
        $mockClient->shouldReceive('getFolder')->with('INBOX')->once()->andReturn($mockFolder);

        $imapService = app(ImapService::class);
        $processedCount = $imapService->fetchUnreadEmails($mockClient);

        $this->assertEquals(0, $processedCount);
        $this->assertDatabaseMissing('tickets', ['sender_email' => $supportEmail]);
    }

    /**
     * Test IMAP service processes customer emails from external services into tickets.
     */
    public function test_imap_service_processes_customer_emails_into_tickets(): void
    {
        $mockMessage = Mockery::mock(Message::class);
        $mockMessage->shouldReceive('getFrom')->andReturn([
            (object) [
                'mail' => 'customer.external@service.com',
                'personal' => 'External Customer',
            ],
        ]);
        $mockMessage->shouldReceive('getSubject')->andReturn('Question about my subscription');
        $mockMessage->shouldReceive('hasTextBody')->andReturn(true);
        $mockMessage->shouldReceive('getTextBody')->andReturn('I have a question about my monthly plan.');
        $mockMessage->shouldReceive('getMessageId')->andReturn('<msg-ext@example.com>');
        $mockMessage->shouldReceive('getUid')->byDefault()->andReturn(103);
        $mockMessage->shouldReceive('setFlag')->with('Seen')->once();

        $messageCollection = new MessageCollection([$mockMessage]);
        $mockQuery = Mockery::mock(WhereQuery::class);
        $mockQuery->shouldReceive('setFetchOrder')->andReturnSelf();
        $mockQuery->shouldReceive('unseen')->once()->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();
        $mockQuery->shouldReceive('get')->once()->andReturn($messageCollection);
        $mockFolder = Mockery::mock(Folder::class);
        $mockFolder->shouldReceive('query')->andReturn($mockQuery);
        $mockClient = Mockery::mock(ImapClient::class);
        $mockClient->shouldReceive('isConnected')->andReturn(true);
        $mockClient->shouldReceive('getFolder')->with('INBOX')->once()->andReturn($mockFolder);

        $imapService = app(ImapService::class);
        $processedCount = $imapService->fetchUnreadEmails($mockClient);

        $this->assertEquals(1, $processedCount);
        $this->assertDatabaseHas('tickets', ['sender_email' => 'customer.external@service.com']);
    }

    /**
     * Test IMAP service prevents duplicate ticket creation using Message-ID.
     */
    public function test_imap_service_prevents_duplicate_tickets_using_message_id(): void
    {
        Ticket::factory()->create([
            'message_id'   => '<unique-msg-id-999@example.com>',
            'sender_email' => 'duplicate.user@example.com',
        ]);

        $mockMessage = Mockery::mock(Message::class);
        $mockMessage->shouldReceive('getFrom')->andReturn([
            (object) [
                'mail' => 'duplicate.user@example.com',
                'personal' => 'Duplicate User',
            ],
        ]);
        $mockMessage->shouldReceive('getSubject')->andReturn('Duplicate inquiry');
        $mockMessage->shouldReceive('hasTextBody')->andReturn(true);
        $mockMessage->shouldReceive('getTextBody')->andReturn('Duplicate body');
        $mockMessage->shouldReceive('getMessageId')->andReturn('<unique-msg-id-999@example.com>');
        $mockMessage->shouldReceive('getUid')->byDefault()->andReturn(104);
        $mockMessage->shouldReceive('setFlag')->with('Seen')->once();

        $messageCollection = new MessageCollection([$mockMessage]);
        $mockQuery = Mockery::mock(WhereQuery::class);
        $mockQuery->shouldReceive('setFetchOrder')->andReturnSelf();
        $mockQuery->shouldReceive('unseen')->once()->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();
        $mockQuery->shouldReceive('get')->once()->andReturn($messageCollection);
        $mockFolder = Mockery::mock(Folder::class);
        $mockFolder->shouldReceive('query')->once()->andReturn($mockQuery);
        $mockClient = Mockery::mock(ImapClient::class);
        $mockClient->shouldReceive('isConnected')->andReturn(true);
        $mockClient->shouldReceive('getFolder')->with('INBOX')->once()->andReturn($mockFolder);

        $imapService = app(ImapService::class);
        $processedCount = $imapService->fetchUnreadEmails($mockClient);

        $this->assertEquals(0, $processedCount);
        $this->assertEquals(1, Ticket::where('message_id', '<unique-msg-id-999@example.com>')->count());
    }

    /**
     * Test tickets:fetch-emails Artisan command execution.
     */
    public function test_fetch_emails_artisan_command_executes_successfully(): void
    {
        $mockService = Mockery::mock(ImapService::class);
        $mockService->shouldReceive('getLastProcessedUid')->andReturn(0);
        $mockService->shouldReceive('fetchUnreadEmails')->once()->andReturn(2);
        $this->app->instance(ImapService::class, $mockService);

        $this->artisan('tickets:fetch-emails')
            ->expectsOutput('Starting IMAP email fetch command (onlyUnseen: true, limit: 20, lastUid: 0)...')
            ->assertExitCode(0);
    }

    /**
     * Test complete end-to-end flow:
     * Customer email parsed via IMAP → Ticket created → Dashboard list → Gemini AI auto-resolves → Outgoing SMTP reply sent.
     */
    public function test_complete_end_to_end_flow_from_email_to_ai_processing_and_smtp_reply(): void
    {
        Mail::fake();
        Queue::fake([
            AutoResolveTicketJob::class,
            TicketClassificationJob::class,
        ]);

        // 1. Simulate incoming IMAP email message
        $mockMessage = Mockery::mock(Message::class);
        $mockMessage->shouldReceive('getFrom')->andReturn([
            (object) [
                'mail' => 'alex.developer@example.com',
                'personal' => 'Alex Developer',
            ],
        ]);
        $mockMessage->shouldReceive('getSubject')->andReturn('Resetting my account password');
        $mockMessage->shouldReceive('hasTextBody')->andReturn(true);
        $mockMessage->shouldReceive('getTextBody')->andReturn('How do I reset my password?');
        $mockMessage->shouldReceive('getMessageId')->andReturn('<e2e-msg-123@example.com>');
        $mockMessage->shouldReceive('getUid')->byDefault()->andReturn(105);
        $mockMessage->shouldReceive('setFlag')->with('Seen')->once();

        $messageCollection = new MessageCollection([$mockMessage]);
        $mockQuery = Mockery::mock(WhereQuery::class);
        $mockQuery->shouldReceive('setFetchOrder')->andReturnSelf();
        $mockQuery->shouldReceive('unseen')->once()->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();
        $mockQuery->shouldReceive('get')->once()->andReturn($messageCollection);

        $mockFolder = Mockery::mock(Folder::class);
        $mockFolder->shouldReceive('query')->once()->andReturn($mockQuery);

        $mockClient = Mockery::mock(ImapClient::class);
        $mockClient->shouldReceive('isConnected')->andReturn(true);
        $mockClient->shouldReceive('getFolder')->with('INBOX')->once()->andReturn($mockFolder);

        // 2. Mock Gemini AI Service auto-resolution
        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('autoResolveTicket')
                ->once()
                ->andReturn([
                    'can_resolve' => true,
                    'reply'       => 'To reset your password, visit Settings > Security and click Reset Password.',
                ]);
        });

        // 3. Process email via ImapService
        $imapService = app(ImapService::class);
        $processedCount = $imapService->fetchUnreadEmails($mockClient);

        $this->assertEquals(1, $processedCount);
        $ticket = Ticket::where('sender_email', 'alex.developer@example.com')->firstOrFail();
        $this->assertEquals('Resetting my account password', $ticket->subject);

        // 4. Verify ticket appears in dashboard list
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Resetting my account password');

        // 5. Directly execute AutoResolveTicketJob
        $job = new AutoResolveTicketJob($ticket);
        $job->handle(app(GeminiService::class), app(\App\Services\TicketEmailService::class));

        // 6. Assert ticket status is Resolved
        $this->assertEquals(TicketStatus::Resolved, $ticket->fresh()->status);

        // 7. Assert AI reply was stored
        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'body'      => 'To reset your password, visit Settings > Security and click Reset Password.',
        ]);

        // 8. Assert Outgoing Mail was sent via SMTP
        Mail::assertSent(TicketReplyMail::class, function (TicketReplyMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('alex.developer@example.com')
                && str_contains($html, 'Hi Alex,')
                && str_contains($html, 'To reset your password, visit Settings');
        });
    }

    /**
     * Test POST /tickets/sync-emails route triggers email fetch and redirects back with feedback.
     */
    public function test_sync_emails_controller_route_executes_successfully(): void
    {
        $mockService = Mockery::mock(ImapService::class);
        $mockService->shouldReceive('fetchUnreadEmails')->once()->andReturn(1);
        $this->app->instance(ImapService::class, $mockService);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('tickets.sync-emails'));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Successfully synced emails! Imported 1 new customer ticket(s).');
    }

    /**
     * Test IMAP service uses UID-based search sequence range and persists last_processed_uid.
     */
    public function test_imap_service_uses_uid_sequence_and_stores_last_processed_uid(): void
    {
        $imapService = app(ImapService::class);
        $imapService->setLastProcessedUid(500);

        $this->assertEquals(500, $imapService->getLastProcessedUid());

        $mockMessage = Mockery::mock(Message::class);
        $mockMessage->shouldReceive('getFrom')->andReturn([
            (object) [
                'mail' => 'uid.user@example.com',
                'personal' => 'UID User',
            ],
        ]);
        $mockMessage->shouldReceive('getSubject')->andReturn('UID range test query');
        $mockMessage->shouldReceive('hasTextBody')->andReturn(true);
        $mockMessage->shouldReceive('getTextBody')->andReturn('Testing UID range query.');
        $mockMessage->shouldReceive('getMessageId')->andReturn('<uid-msg-501@example.com>');
        $mockMessage->shouldReceive('getUid')->andReturn(501);
        $mockMessage->shouldReceive('setFlag')->with('Seen')->once();

        $messageCollection = new MessageCollection([$mockMessage]);

        $mockQuery = Mockery::mock(WhereQuery::class);
        $mockQuery->shouldReceive('where')->with('CUSTOM UID 501:*')->once()->andReturnSelf();
        $mockQuery->shouldReceive('setFetchOrder')->with('asc')->once()->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();
        $mockQuery->shouldReceive('get')->once()->andReturn($messageCollection);

        $mockFolder = Mockery::mock(Folder::class);
        $mockFolder->shouldReceive('query')->once()->andReturn($mockQuery);

        $mockClient = Mockery::mock(ImapClient::class);
        $mockClient->shouldReceive('isConnected')->andReturn(true);
        $mockClient->shouldReceive('getFolder')->with('INBOX')->once()->andReturn($mockFolder);

        $count = $imapService->fetchUnreadEmails($mockClient);

        $this->assertEquals(1, $count);
        $this->assertEquals(501, $imapService->getLastProcessedUid());
        $this->assertDatabaseHas('tickets', [
            'message_id' => '<uid-msg-501@example.com>',
            'sender_email' => 'uid.user@example.com',
        ]);
    }

    /**
     * Test ProcessInboundEmailJob processes email data into ticket and dispatches AI jobs.
     */
    public function test_process_inbound_email_job_creates_ticket_and_dispatches_ai_jobs(): void
    {
        Bus::fake([
            TicketClassificationJob::class,
            AutoResolveTicketJob::class,
        ]);

        $job = new \App\Jobs\ProcessInboundEmailJob([
            'message_id'   => '<job-msg-777@example.com>',
            'sender_email' => 'queued.customer@example.com',
            'sender_name'  => 'Queued Customer',
            'subject'      => 'Need help with queue',
            'body'         => 'Issue description for queue job.',
        ]);

        $ticket = $job->handle(
            app(\App\Services\InboundEmailValidationService::class),
            app(\App\Actions\CreateTicketFromInboundEmailAction::class)
        );

        $this->assertNotNull($ticket);
        $this->assertEquals('<job-msg-777@example.com>', $ticket->message_id);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);

        Bus::assertDispatched(AutoResolveTicketJob::class, function ($j) use ($ticket) {
            return $j->ticket->id === $ticket->id;
        });

        Bus::assertDispatched(TicketClassificationJob::class, function ($j) use ($ticket) {
            return $j->ticket->id === $ticket->id;
        });
    }
}
