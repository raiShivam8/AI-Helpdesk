<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\SenderType;
use App\Models\AppNotification;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationAndTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_transfer_ticket_to_another_agent_with_reason(): void
    {
        $agent1 = User::factory()->create(['role' => Role::Agent]);
        $agent2 = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create(['assigned_agent_id' => $agent1->id]);

        $response = $this->actingAs($agent1)->patch(route('tickets.assign', $ticket), [
            'assigned_agent_id' => $agent2->id,
            'transfer_reason'   => 'Needs specialized escalation support',
        ]);

        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertDatabaseHas('tickets', [
            'id'                => $ticket->id,
            'assigned_agent_id' => $agent2->id,
        ]);

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id'       => $ticket->id,
            'sender_type'     => SenderType::System->value,
            'transfer_reason' => 'Needs specialized escalation support',
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $agent2->id,
            'type'    => 'ticket_transfer',
        ]);
    }

    public function test_user_can_reply_with_file_attachment(): void
    {
        Storage::fake('public');

        $agent = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->actingAs($agent)->post(route('tickets.replies.store', $ticket), [
            'body'       => 'Here is the requested document.',
            'attachment' => $file,
        ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $reply = TicketReply::where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($reply->attachment_path);
        $this->assertEquals('document.pdf', $reply->attachment_name);
        Storage::disk('public')->assertExists($reply->attachment_path);
    }

    public function test_user_can_fetch_and_mark_notifications_read(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        $notification = AppNotification::create([
            'user_id' => $user->id,
            'title'   => 'Test Notification',
            'message' => 'Hello World',
            'type'    => 'general',
        ]);

        $response = $this->actingAs($user)->getJson(route('notifications.index'));
        $response->assertOk()
            ->assertJsonFragment(['title' => 'Test Notification'])
            ->assertJsonPath('unread_count', 1);

        $readResponse = $this->actingAs($user)->postJson(route('notifications.read', $notification));
        $readResponse->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_only_admin_can_see_transfer_reason(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $agent = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create(['assigned_agent_id' => $agent->id]);

        TicketReply::create([
            'ticket_id'       => $ticket->id,
            'user_id'         => $agent->id,
            'body'            => 'Ticket transferred to Agent by Admin.',
            'sender_type'     => SenderType::System,
            'transfer_reason' => 'Secret Admin Only Transfer Reason 12345',
        ]);

        // Admin can see the transfer reason
        $adminResp = $this->actingAs($admin)->get(route('tickets.show', $ticket));
        $adminResp->assertSee('Secret Admin Only Transfer Reason 12345');

        // Agent cannot see the secret transfer reason
        $agentResp = $this->actingAs($agent)->get(route('tickets.show', $ticket));
        $agentResp->assertDontSee('Secret Admin Only Transfer Reason 12345');
    }
}
