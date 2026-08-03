<?php

namespace Tests\Feature;

use App\Models\User;
use App\Enums\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the database seeder creates the default agent users.
     */
    public function test_database_seeder_creates_default_users(): void
    {
        // Assert database is empty of default users first
        $this->assertDatabaseMissing('users', ['email' => 'agent@gmail.com']);

        // Run the seeder
        $this->seed(DatabaseSeeder::class);

        // Assert agent@gmail.com is created with proper role
        $this->assertDatabaseHas('users', [
            'email' => 'agent@gmail.com',
            'role' => Role::Agent->value,
        ]);

        $user = User::where('email', 'agent@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password123', $user->password));
    }
}
