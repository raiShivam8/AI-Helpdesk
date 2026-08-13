<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_overall_and_specific_agent_dashboard(): void
    {
        $admin = User::factory()->create(['role' => Role::Admin]);
        $agent = User::factory()->create(['role' => Role::Agent]);

        Ticket::factory()->create(['assigned_agent_id' => $agent->id, 'subject' => 'Agent Ticket 1']);
        Ticket::factory()->create(['assigned_agent_id' => null, 'subject' => 'Unassigned Ticket 2']);

        // Overall View
        $response = $this->actingAs($admin)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Company Overall View');

        // Specific Agent View (non-admin agent)
        $agentResponse = $this->actingAs($admin)->get(route('dashboard', ['agent_id' => $agent->id]));
        $agentResponse->assertOk();
        $agentResponse->assertSee('Agent: ' . $agent->name);
        $agentResponse->assertSee('Agent Ticket 1');

        // Selecting Admin (you) shows overall view
        $adminResponse = $this->actingAs($admin)->get(route('dashboard', ['agent_id' => $admin->id]));
        $adminResponse->assertOk();
        $adminResponse->assertSee('Company Overall View');
        $adminResponse->assertSee('Unassigned Ticket 2');
    }
}
