<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketsPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that guest users are redirected to login when trying to access tickets.
     */
    public function test_guests_cannot_access_tickets_list(): void
    {
        $response = $this->get(route('tickets.index'));

        $response->assertRedirect('/login');
    }

    /**
     * Test that authenticated users (both Admin and Agent) can access the tickets list.
     */
    public function test_authenticated_users_can_access_tickets_list(): void
    {
        $user = User::factory()->create([
            'role' => Role::Agent,
        ]);

        $response = $this->actingAs($user)->get(route('tickets.index'));

        $response->assertStatus(200);
        $response->assertViewIs('tickets.index');
    }

    /**
     * Test that the tickets list displays all tickets sorted by newest first.
     */
    public function test_tickets_are_displayed_and_sorted_correctly(): void
    {
        $user = User::factory()->create([
            'role' => Role::Agent,
        ]);

        $agent = User::factory()->create([
            'name' => 'Agent Bobby',
            'role' => Role::Agent,
        ]);

        // Create an older ticket
        $oldTicket = Ticket::factory()->create([
            'subject' => 'Old Ticket Subject',
            'sender_name' => 'Old Sender',
            'sender_email' => 'old@example.com',
            'category' => TicketCategory::GeneralQuestion,
            'assigned_agent_id' => $agent->id,
            'created_at' => now()->subHours(2),
        ]);

        // Create a newer ticket
        $newTicket = Ticket::factory()->create([
            'subject' => 'New Ticket Subject',
            'sender_name' => 'New Sender',
            'sender_email' => 'new@example.com',
            'category' => TicketCategory::RefundRequest,
            'assigned_agent_id' => null,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('tickets.index'));

        $response->assertStatus(200);

        // Assert details are visible
        $response->assertSee('New Ticket Subject');
        $response->assertSee('Old Ticket Subject');
        $response->assertSee('New Sender');
        $response->assertSee('Old Sender');
        $response->assertSee('general question');
        $response->assertSee('refund request');
        $response->assertSee('Agent Bobby');
        $response->assertSee('Unassigned');

        // Verify sorting (New Ticket appears before Old Ticket in HTML output)
        $response->assertSeeInOrder([
            'New Ticket Subject',
            'Old Ticket Subject',
        ]);
    }

    /**
     * Test that a friendly empty state is displayed when there are no tickets.
     */
    public function test_empty_state_rendered_when_no_tickets_exist(): void
    {
        $user = User::factory()->create([
            'role' => Role::Agent,
        ]);

        $response = $this->actingAs($user)->get(route('tickets.index'));

        $response->assertStatus(200);
        $response->assertSee('No tickets in the queue');
        $response->assertSee('All caught up!');
    }

    public function test_tickets_list_can_be_sorted(): void
    {
        $user = User::factory()->create([
            'role' => Role::Agent,
        ]);

        // Create tickets with specific attributes for sorting assertions
        $ticketA = Ticket::factory()->create([
            'subject' => 'Alpha Subject',
            'sender_name' => 'Alice Sender',
            'status' => TicketStatus::Closed,
            'category' => TicketCategory::GeneralQuestion,
            'created_at' => now()->subDay(),
        ]);

        $ticketB = Ticket::factory()->create([
            'subject' => 'Beta Subject',
            'sender_name' => 'Bob Sender',
            'status' => TicketStatus::Open,
            'category' => TicketCategory::RefundRequest,
            'created_at' => now(),
        ]);

        // 1. Sort by ID ascending (A has lower ID, B has higher ID)
        $response = $this->actingAs($user)->get(route('tickets.index', ['sort' => 'id', 'direction' => 'asc']));
        $response->assertStatus(200);
        $response->assertSeeInOrder(['Alpha Subject', 'Beta Subject']);

        // Sort by ID descending
        $response = $this->actingAs($user)->get(route('tickets.index', ['sort' => 'id', 'direction' => 'desc']));
        $response->assertStatus(200);
        $response->assertSeeInOrder(['Beta Subject', 'Alpha Subject']);

        // 2. Sort by Subject ascending
        $response = $this->actingAs($user)->get(route('tickets.index', ['sort' => 'subject', 'direction' => 'asc']));
        $response->assertStatus(200);
        $response->assertSeeInOrder(['Alpha Subject', 'Beta Subject']);

        // Sort by Subject descending
        $response = $this->actingAs($user)->get(route('tickets.index', ['sort' => 'subject', 'direction' => 'desc']));
        $response->assertStatus(200);
        $response->assertSeeInOrder(['Beta Subject', 'Alpha Subject']);

        // 3. Sort by Sender Name ascending
        $response = $this->actingAs($user)->get(route('tickets.index', ['sort' => 'sender_name', 'direction' => 'asc']));
        $response->assertStatus(200);
        $response->assertSeeInOrder(['Alice Sender', 'Bob Sender']);

        // Sort by Sender Name descending
        $response = $this->actingAs($user)->get(route('tickets.index', ['sort' => 'sender_name', 'direction' => 'desc']));
        $response->assertStatus(200);
        $response->assertSeeInOrder(['Bob Sender', 'Alice Sender']);

        // 4. Sort by Category ascending ('general question' before 'refund request')
        $response = $this->actingAs($user)->get(route('tickets.index', ['sort' => 'category', 'direction' => 'asc']));
        $response->assertStatus(200);
        $response->assertSeeInOrder(['Alpha Subject', 'Beta Subject']);

        // Sort by Category descending
        $response = $this->actingAs($user)->get(route('tickets.index', ['sort' => 'category', 'direction' => 'desc']));
        $response->assertStatus(200);
        $response->assertSeeInOrder(['Beta Subject', 'Alpha Subject']);

        // 5. Sort by Status ascending ('closed' before 'open')
        $response = $this->actingAs($user)->get(route('tickets.index', ['sort' => 'status', 'direction' => 'asc']));
        $response->assertStatus(200);
        $response->assertSeeInOrder(['Closed', 'Open']);

        // Sort by Status descending
        $response = $this->actingAs($user)->get(route('tickets.index', ['sort' => 'status', 'direction' => 'desc']));
        $response->assertStatus(200);
        $response->assertSeeInOrder(['Open', 'Closed']);

        // 6. Sort by Created At ascending (A was created a day ago, B is newer)
        $response = $this->actingAs($user)->get(route('tickets.index', ['sort' => 'created_at', 'direction' => 'asc']));
        $response->assertStatus(200);
        $response->assertSeeInOrder(['Alpha Subject', 'Beta Subject']);

        // Sort by Created At descending
        $response = $this->actingAs($user)->get(route('tickets.index', ['sort' => 'created_at', 'direction' => 'desc']));
        $response->assertStatus(200);
        $response->assertSeeInOrder(['Beta Subject', 'Alpha Subject']);

        // 7. Invalid sort parameters default to created_at descending (B then A)
        $response = $this->actingAs($user)->get(route('tickets.index', ['sort' => 'invalid_col', 'direction' => 'invalid_dir']));
        $response->assertStatus(200);
        $response->assertSeeInOrder(['Beta Subject', 'Alpha Subject']);
    }

    public function test_tickets_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        
        Ticket::factory()->create(['subject' => 'Open Ticket', 'status' => TicketStatus::Open]);
        Ticket::factory()->create(['subject' => 'Closed Ticket', 'status' => TicketStatus::Closed]);

        $response = $this->actingAs($user)->get(route('tickets.index', ['status' => TicketStatus::Open->value]));

        $response->assertStatus(200);
        $response->assertSee('Open Ticket');
        $response->assertDontSee('Closed Ticket');
    }

    public function test_tickets_can_be_filtered_by_category(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);

        Ticket::factory()->create(['subject' => 'Billing Ticket', 'category' => TicketCategory::Billing]);
        Ticket::factory()->create(['subject' => 'Bug Ticket', 'category' => TicketCategory::BugReport]);

        $response = $this->actingAs($user)->get(route('tickets.index', ['category' => TicketCategory::Billing->value]));

        $response->assertStatus(200);
        $response->assertSee('Billing Ticket');
        $response->assertDontSee('Bug Ticket');
    }

    public function test_tickets_can_be_filtered_by_agent(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        
        $agentA = User::factory()->create(['name' => 'Agent Amy', 'role' => Role::Agent]);
        $agentB = User::factory()->create(['name' => 'Agent Ben', 'role' => Role::Agent]);

        Ticket::factory()->create(['subject' => 'Amy Ticket', 'assigned_agent_id' => $agentA->id]);
        Ticket::factory()->create(['subject' => 'Ben Ticket', 'assigned_agent_id' => $agentB->id]);

        $response = $this->actingAs($user)->get(route('tickets.index', ['agent' => $agentA->id]));

        $response->assertStatus(200);
        $response->assertSee('Amy Ticket');
        $response->assertDontSee('Ben Ticket');
    }

    public function test_tickets_can_be_filtered_by_unassigned(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        
        $agent = User::factory()->create(['role' => Role::Agent]);

        Ticket::factory()->create(['subject' => 'Assigned Ticket', 'assigned_agent_id' => $agent->id]);
        Ticket::factory()->create(['subject' => 'Unassigned Ticket', 'assigned_agent_id' => null]);

        $response = $this->actingAs($user)->get(route('tickets.index', ['agent' => 'unassigned']));

        $response->assertStatus(200);
        $response->assertSee('Unassigned Ticket');
        $response->assertDontSee('Assigned Ticket');
    }

    public function test_tickets_can_be_filtered_by_multiple_filters(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        $agent = User::factory()->create(['role' => Role::Agent]);

        Ticket::factory()->create([
            'subject' => 'Match Ticket', 
            'status' => TicketStatus::Open, 
            'category' => TicketCategory::Billing,
            'assigned_agent_id' => $agent->id
        ]);
        Ticket::factory()->create([
            'subject' => 'Wrong Status', 
            'status' => TicketStatus::Resolved, 
            'category' => TicketCategory::Billing,
            'assigned_agent_id' => $agent->id
        ]);
        Ticket::factory()->create([
            'subject' => 'Wrong Category', 
            'status' => TicketStatus::Open, 
            'category' => TicketCategory::BugReport,
            'assigned_agent_id' => $agent->id
        ]);
        Ticket::factory()->create([
            'subject' => 'Wrong Agent', 
            'status' => TicketStatus::Open, 
            'category' => TicketCategory::Billing,
            'assigned_agent_id' => null
        ]);

        $response = $this->actingAs($user)->get(route('tickets.index', [
            'status' => TicketStatus::Open->value,
            'category' => TicketCategory::Billing->value,
            'agent' => $agent->id
        ]));

        $response->assertStatus(200);
        $response->assertSee('Match Ticket');
        $response->assertDontSee('Wrong Status');
        $response->assertDontSee('Wrong Category');
        $response->assertDontSee('Wrong Agent');
    }

    public function test_invalid_filter_parameters_redirect_with_errors(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);

        // Test invalid status
        $response = $this->actingAs($user)->get(route('tickets.index', ['status' => 'invalid_status']));
        $response->assertSessionHasErrors(['status']);

        // Test invalid category
        $response = $this->actingAs($user)->get(route('tickets.index', ['category' => 'invalid_category']));
        $response->assertSessionHasErrors(['category']);

        // Test invalid agent ID
        $response = $this->actingAs($user)->get(route('tickets.index', ['agent' => 9999]));
        $response->assertSessionHasErrors(['agent']);
    }

    public function test_empty_filter_parameters_are_ignored(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);

        Ticket::factory()->create(['subject' => 'Ticket A', 'status' => TicketStatus::Open]);
        Ticket::factory()->create(['subject' => 'Ticket B', 'status' => TicketStatus::Closed]);

        $response = $this->actingAs($user)->get(route('tickets.index', [
            'status' => '',
            'category' => '',
            'agent' => ''
        ]));

        $response->assertStatus(200);
        $response->assertSee('Ticket A');
        $response->assertSee('Ticket B');
    }

    public function test_filters_are_preserved_when_sorting(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);

        Ticket::factory()->create([
            'subject' => 'A Ticket', 
            'status' => TicketStatus::Open,
            'created_at' => now()->subDay()
        ]);
        Ticket::factory()->create([
            'subject' => 'B Ticket', 
            'status' => TicketStatus::Open,
            'created_at' => now()
        ]);
        Ticket::factory()->create([
            'subject' => 'C Ticket', 
            'status' => TicketStatus::Closed,
            'created_at' => now()->addDay()
        ]);

        // Request open tickets sorted by created_at asc (A then B)
        $response = $this->actingAs($user)->get(route('tickets.index', [
            'status' => TicketStatus::Open->value,
            'sort' => 'created_at',
            'direction' => 'asc'
        ]));

        $response->assertStatus(200);
        $response->assertSeeInOrder(['A Ticket', 'B Ticket']);
        $response->assertDontSee('C Ticket');

        // Request open tickets sorted by created_at desc (B then A)
        $response = $this->actingAs($user)->get(route('tickets.index', [
            'status' => TicketStatus::Open->value,
            'sort' => 'created_at',
            'direction' => 'desc'
        ]));

        $response->assertStatus(200);
        $response->assertSeeInOrder(['B Ticket', 'A Ticket']);
        $response->assertDontSee('C Ticket');
    }

    public function test_tickets_list_is_paginated(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);

        // Create 20 tickets
        Ticket::factory()->count(20)->sequence(
            fn ($sequence) => [
                'subject' => "Ticket Subject " . ($sequence->index + 1),
                'created_at' => now()->subMinutes(30 - $sequence->index)
            ]
        )->create();

        // Request first page (should see 10 tickets, from Ticket Subject 20 down to Ticket Subject 11 due to desc order)
        $response = $this->actingAs($user)->get(route('tickets.index'));
        $response->assertStatus(200);
        $response->assertSee('Ticket Subject 20');
        $response->assertSee('Ticket Subject 11');
        $response->assertDontSee('Ticket Subject 10'); // On page 2
        $response->assertSee('page=2'); // Pagination links

        // Request second page
        $response = $this->actingAs($user)->get(route('tickets.index', ['page' => 2]));
        $response->assertStatus(200);
        $response->assertDontSee('Ticket Subject 20');
        $response->assertSee('Ticket Subject 10');
        $response->assertSee('Ticket Subject 1');
    }

    public function test_pagination_preserves_sorting_and_filters(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);

        // Create 20 open tickets with Billing category
        Ticket::factory()->count(20)->sequence(
            fn ($sequence) => ['subject' => "Billing Open Ticket " . ($sequence->index + 1)]
        )->create([
            'status' => TicketStatus::Open,
            'category' => TicketCategory::Billing,
            'created_at' => now()->subMinutes(20),
        ]);

        // Create 5 closed tickets with Billing category (should not appear when filtering by open)
        Ticket::factory()->count(5)->create([
            'status' => TicketStatus::Closed,
            'category' => TicketCategory::Billing,
        ]);

        // Request page 1 with status and category filters, sorted by id asc
        $response = $this->actingAs($user)->get(route('tickets.index', [
            'status' => TicketStatus::Open->value,
            'category' => TicketCategory::Billing->value,
            'sort' => 'id',
            'direction' => 'asc',
            'page' => 1
        ]));

        $response->assertStatus(200);
        
        // Pagination link on page 1 for page 2 should contain all queries
        // Verify that the page 2 link is generated with active filters and sorting
        $response->assertSee('status=' . TicketStatus::Open->value);
        $response->assertSee('category=' . TicketCategory::Billing->value);
        $response->assertSee('sort=id');
        $response->assertSee('direction=asc');
        $response->assertSee('page=2');

        // Request page 2 with filters and sorting
        $response = $this->actingAs($user)->get(route('tickets.index', [
            'status' => TicketStatus::Open->value,
            'category' => TicketCategory::Billing->value,
            'sort' => 'id',
            'direction' => 'asc',
            'page' => 2
        ]));

        $response->assertStatus(200);
        
        // Assert we see the remaining tickets (11 to 20, since sorted id asc)
        $response->assertSee('Billing Open Ticket 11');
        $response->assertSee('Billing Open Ticket 20');
        $response->assertDontSee('Billing Open Ticket 10'); // On page 1
    }

    public function test_guests_cannot_access_ticket_details(): void
    {
        $ticket = Ticket::factory()->create();

        $response = $this->get(route('tickets.show', $ticket));

        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_view_ticket_details(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        
        $agent = User::factory()->create([
            'name' => 'Agent Amy',
            'role' => Role::Agent
        ]);

        $ticket = Ticket::factory()->create([
            'sender_name' => 'Sender Steve',
            'sender_email' => 'steve@example.com',
            'subject' => 'Steve Ticket Subject',
            'body' => 'Steve Ticket Body description details.',
            'status' => TicketStatus::Open,
            'category' => TicketCategory::TechnicalSupport,
            'assigned_agent_id' => $agent->id,
        ]);

        $response = $this->actingAs($user)->get(route('tickets.show', $ticket));

        $response->assertStatus(200);
        $response->assertViewIs('tickets.show');

        // Verify all details are present on the page
        $response->assertSee('Ticket #' . $ticket->id);
        $response->assertSee('Steve Ticket Subject');
        $response->assertSee('Sender Steve');
        $response->assertSee('steve@example.com');
        $response->assertSee('technical support'); // Category value
        $response->assertSee('Open'); // Status capitalized
        $response->assertSee('Agent Amy'); // Agent name
        $response->assertSee('Steve Ticket Body description details.'); // Body text
        $response->assertSee($ticket->created_at->format('M j, Y'));
        $response->assertSee($ticket->updated_at->format('M j, Y'));
    }

    public function test_ticket_details_returns_404_when_ticket_does_not_exist(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);

        $response = $this->actingAs($user)->get(route('tickets.show', 999999));

        $response->assertStatus(404);
    }

    public function test_admins_can_assign_ticket_to_agent(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $agent = User::factory()->create(['role' => Role::Agent, 'name' => 'Agent Amy']);
        $ticket = Ticket::factory()->create(['assigned_agent_id' => null]);

        $response = $this->actingAs($admin)->patch(route('tickets.assign', $ticket), [
            'assigned_agent_id' => $agent->id,
        ]);

        $response->assertRedirect(route('tickets.show', $ticket));
        $response->assertSessionHas('success', 'Ticket assignment updated successfully.');
        $this->assertEquals($agent->id, $ticket->fresh()->assigned_agent_id);
    }

    public function test_admins_can_unassign_ticket(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $agent = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create(['assigned_agent_id' => $agent->id]);

        $response = $this->actingAs($admin)->patch(route('tickets.assign', $ticket), [
            'assigned_agent_id' => null,
        ]);

        $response->assertRedirect(route('tickets.show', $ticket));
        $response->assertSessionHas('success', 'Ticket assignment updated successfully.');
        $this->assertNull($ticket->fresh()->assigned_agent_id);
    }

    public function test_agents_cannot_assign_tickets(): void
    {
        $agentUser = User::factory()->create(['role' => Role::Agent]);
        $anotherAgent = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create(['assigned_agent_id' => null]);

        $response = $this->actingAs($agentUser)->patch(route('tickets.assign', $ticket), [
            'assigned_agent_id' => $anotherAgent->id,
        ]);

        $response->assertStatus(403);
        $this->assertNull($ticket->fresh()->assigned_agent_id);
    }

    public function test_assignment_validates_user_must_be_agent(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $anotherAdmin = User::factory()->create(['role' => Role::Admin]);
        $ticket = Ticket::factory()->create(['assigned_agent_id' => null]);

        $response = $this->actingAs($admin)->patch(route('tickets.assign', $ticket), [
            'assigned_agent_id' => $anotherAdmin->id,
        ]);

        $response->assertSessionHasErrors(['assigned_agent_id']);
        $this->assertNull($ticket->fresh()->assigned_agent_id);
    }

    public function test_assignment_does_not_allow_soft_deleted_agents(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $agent = User::factory()->create([
            'role' => Role::Agent,
            'deleted_at' => now(),
        ]);
        $ticket = Ticket::factory()->create(['assigned_agent_id' => null]);

        $response = $this->actingAs($admin)->patch(route('tickets.assign', $ticket), [
            'assigned_agent_id' => $agent->id,
        ]);

        $response->assertSessionHasErrors(['assigned_agent_id']);
        $response->assertSessionHasErrors([
            'assigned_agent_id' => 'The selected agent is invalid, does not exist, or has been deleted.'
        ]);
        $this->assertNull($ticket->fresh()->assigned_agent_id);
    }

    public function test_authorized_users_can_update_ticket_status_and_category(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Open,
            'category' => TicketCategory::GeneralQuestion,
        ]);

        $response = $this->actingAs($user)->patch(route('tickets.update', $ticket), [
            'status' => TicketStatus::Closed->value,
            'category' => TicketCategory::RefundRequest->value,
        ]);

        $response->assertRedirect(route('tickets.show', $ticket));
        $response->assertSessionHas('success', 'Ticket updated successfully.');
        
        $ticket = $ticket->fresh();
        $this->assertEquals(TicketStatus::Closed, $ticket->status);
        $this->assertEquals(TicketCategory::RefundRequest, $ticket->category);
    }

    public function test_update_ticket_validates_status_and_category(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Open,
            'category' => TicketCategory::GeneralQuestion,
        ]);

        // Validate wrong status and wrong category
        $response = $this->actingAs($user)->patch(route('tickets.update', $ticket), [
            'status' => 'invalid_status',
            'category' => 'invalid_category',
        ]);

        $response->assertSessionHasErrors(['status', 'category']);
        
        $ticket = $ticket->fresh();
        $this->assertEquals(TicketStatus::Open, $ticket->status);
        $this->assertEquals(TicketCategory::GeneralQuestion, $ticket->category);
    }

    public function test_guests_cannot_update_tickets(): void
    {
        $ticket = Ticket::factory()->create();

        $response = $this->patch(route('tickets.update', $ticket), [
            'status' => TicketStatus::Closed->value,
        ]);

        $response->assertRedirect('/login');
    }

    public function test_updating_ticket_to_resolved_stamps_resolved_at(): void
    {
        $user = User::factory()->create(['role' => Role::Agent]);
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Open, 'resolved_at' => null]);

        $response = $this->actingAs($user)->patch(route('tickets.update', $ticket), [
            'status' => TicketStatus::Resolved->value,
        ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertEquals(TicketStatus::Resolved, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);

        // Changing back to Open clears resolved_at
        $this->actingAs($user)->patch(route('tickets.update', $ticket), [
            'status' => TicketStatus::Open->value,
        ]);

        $ticket->refresh();
        $this->assertEquals(TicketStatus::Open, $ticket->status);
        $this->assertNull($ticket->resolved_at);
    }
}
