<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that guests (unauthenticated users) are redirected to the login page.
     */
    public function test_guests_cannot_access_users_page(): void
    {
        $response = $this->get('/users');

        $response->assertRedirect('/login');
    }

    /**
     * Test that authenticated admins can access the users page and view user details.
     */
    public function test_admins_can_access_users_page(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin User Spec']);
        $agent = User::factory()->create(['name' => 'Agent User Spec', 'email' => 'agentspec@example.com']);

        $response = $this->actingAs($admin)->get('/users');

        $response->assertOk();
        $response->assertSee('Users');
        $response->assertSee('Admin User Spec');
        $response->assertSee('Agent User Spec');
        $response->assertSee('agentspec@example.com');
    }

    /**
     * Test that authenticated agents receive a 403 Forbidden response.
     */
    public function test_agents_cannot_access_users_page(): void
    {
        $agent = User::factory()->create(); // default role is Agent

        $response = $this->actingAs($agent)->get('/users');

        $response->assertStatus(403);
    }

    /**
     * Test that admins can see the "Users" link in the navigation menu.
     */
    public function test_admins_can_see_users_link_in_navigation(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertSee(route('users.index'));
    }

    /**
     * Test that agents cannot see the "Users" link in the navigation menu.
     */
    public function test_agents_cannot_see_users_link_in_navigation(): void
    {
        $agent = User::factory()->create(); // default role is Agent

        $response = $this->actingAs($agent)->get('/dashboard');

        $response->assertDontSee(route('users.index'));
    }

    /**
     * Test that the user list view renders the empty state when no users are passed.
     */
    public function test_user_list_view_renders_empty_state(): void
    {
        $admin = User::factory()->admin()->create();

        $view = $this->actingAs($admin)->view('users.index', [
            'users' => collect(),
            'errors' => new \Illuminate\Support\ViewErrorBag(),
        ]);

        $view->assertSee('No users found.');
    }

    /**
     * Test that guests cannot create users.
     */
    public function test_guests_cannot_create_user(): void
    {
        $response = $this->post('/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role' => Role::Agent->value,
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that agents cannot create users.
     */
    public function test_agents_cannot_create_user(): void
    {
        $agent = User::factory()->create();

        $response = $this->actingAs($agent)->post('/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role' => Role::Agent->value,
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test that admins can create a user with valid data.
     */
    public function test_admins_can_create_user_with_valid_data(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'New Agent User',
            'email' => 'newagent@example.com',
            'password' => 'password123',
            'role' => Role::Agent->value,
        ]);

        $response->assertRedirect('/users');
        $response->assertSessionHas('success', 'User created successfully.');

        $this->assertDatabaseHas('users', [
            'name' => 'New Agent User',
            'email' => 'newagent@example.com',
            'role' => Role::Agent->value,
        ]);

        $newUser = User::where('email', 'newagent@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password123', $newUser->password));
    }

    /**
     * Test that creating a user fails validation with invalid data.
     */
    public function test_create_user_validation_fails(): void
    {
        $admin = User::factory()->admin()->create();

        // 1. Short name, invalid email, short password, invalid role
        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Ab',
            'email' => 'not-an-email',
            'password' => 'short',
            'role' => 'invalid-role',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'role']);

        // 2. Duplicate email
        $existingUser = User::factory()->create(['email' => 'duplicate@example.com']);
        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Some Name',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'role' => Role::Agent->value,
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    /**
     * Test that guests cannot update a user.
     */
    public function test_guests_cannot_update_user(): void
    {
        $user = User::factory()->create();

        $response = $this->patch("/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'role' => Role::Agent->value,
        ]);

        $response->assertRedirect('/login');
    }

    /**
     * Test that agents cannot update a user.
     */
    public function test_agents_cannot_update_user(): void
    {
        $agent = User::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($agent)->patch("/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'role' => Role::Agent->value,
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test that admins can update a user without changing the password.
     */
    public function test_admins_can_update_user_without_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'role' => \App\Enums\Role::Agent,
            'password' => \Illuminate\Support\Facades\Hash::make('oldpassword'),
        ]);

        $response = $this->actingAs($admin)->patch("/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'role' => Role::Admin->value,
            'password' => '', // leave empty
        ]);

        $response->assertRedirect('/users');
        $response->assertSessionHas('success', 'User updated successfully.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'role' => Role::Admin->value,
        ]);

        $user->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('oldpassword', $user->password));
    }

    /**
     * Test that admins can update a user's password.
     */
    public function test_admins_can_update_user_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'password' => \Illuminate\Support\Facades\Hash::make('oldpassword'),
        ]);

        $response = $this->actingAs($admin)->patch("/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'password' => 'newpassword123',
        ]);

        $response->assertRedirect('/users');
        $user->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpassword123', $user->password));
    }

    /**
     * Test that unique email rule ignores the current user's email during update.
     */
    public function test_update_user_ignores_own_email(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['email' => 'own@example.com']);

        $response = $this->actingAs($admin)->patch("/users/{$user->id}", [
            'name' => 'Same Name',
            'email' => 'own@example.com',
            'role' => Role::Agent->value,
            'password' => '',
        ]);

        $response->assertRedirect('/users');
        $response->assertSessionHasNoErrors();
    }

    /**
     * Test that update user fails validation with duplicate email.
     */
    public function test_update_user_validation_fails_with_duplicate_email(): void
    {
        $admin = User::factory()->admin()->create();
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        $response = $this->actingAs($admin)->patch("/users/{$user1->id}", [
            'name' => 'User One Updated',
            'email' => 'user2@example.com', // duplicate of user2
            'role' => Role::Agent->value,
            'password' => '',
        ]);

        $response->assertSessionHasErrors(['email'], errorBag: 'updateUser');
    }

    /**
     * Test that guests cannot delete a user.
     */
    public function test_guests_cannot_delete_user(): void
    {
        $user = User::factory()->create();

        $response = $this->delete("/users/{$user->id}");

        $response->assertRedirect('/login');
    }

    /**
     * Test that agents cannot delete a user.
     */
    public function test_agents_cannot_delete_user(): void
    {
        $agent = User::factory()->create(); // default role is Agent
        $user = User::factory()->create();

        $response = $this->actingAs($agent)->delete("/users/{$user->id}");

        $response->assertStatus(403);
    }

    /**
     * Test that admins can delete a user (soft delete).
     */
    public function test_admins_can_delete_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->delete("/users/{$user->id}");

        $response->assertRedirect('/users');
        $response->assertSessionHas('success', 'User deleted successfully.');

        // Verify the user is soft-deleted in the database
        $this->assertSoftDeleted('users', [
            'id' => $user->id,
        ]);

        // Verify the user is not returned in active list
        $response2 = $this->actingAs($admin)->get('/users');
        $response2->assertDontSee($user->name);
    }

    /**
     * Test that admins cannot delete the default admin account.
     */
    public function test_admins_cannot_delete_default_admin(): void
    {
        $admin = User::factory()->admin()->create();
        
        // Define default admin email and create a user for it
        $defaultAdminEmail = 'defaultadmin@example.com';
        config(['app.admin_email' => $defaultAdminEmail]);
        $defaultAdmin = User::factory()->admin()->create(['email' => $defaultAdminEmail]);

        $response = $this->actingAs($admin)->delete("/users/{$defaultAdmin->id}");

        $response->assertSessionHas('error', 'The default Admin account cannot be deleted.');
        $this->assertDatabaseHas('users', [
            'id' => $defaultAdmin->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test that admins cannot delete themselves.
     */
    public function test_admins_cannot_delete_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->delete("/users/{$admin->id}");

        $response->assertSessionHas('error', 'You cannot delete your own account.');
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test that deleting an agent user automatically unassigns their tickets.
     */
    public function test_deleting_agent_unassigns_tickets(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->create();

        // Create tickets assigned to this agent and another agent
        $ticket1 = \App\Models\Ticket::factory()->create(['assigned_agent_id' => $agent->id]);
        $ticket2 = \App\Models\Ticket::factory()->create(['assigned_agent_id' => $agent->id]);

        $otherAgent = User::factory()->create();
        $ticket3 = \App\Models\Ticket::factory()->create(['assigned_agent_id' => $otherAgent->id]);

        // Delete the agent
        $response = $this->actingAs($admin)->delete("/users/{$agent->id}");

        $response->assertRedirect('/users');
        $this->assertSoftDeleted('users', ['id' => $agent->id]);

        // Verify the agent's tickets are unassigned
        $ticket1->refresh();
        $ticket2->refresh();
        $this->assertNull($ticket1->assigned_agent_id);
        $this->assertNull($ticket2->assigned_agent_id);

        // Verify the other agent's tickets are NOT affected
        $ticket3->refresh();
        $this->assertEquals($otherAgent->id, $ticket3->assigned_agent_id);
    }
}
