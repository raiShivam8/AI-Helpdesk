<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminEmail = config('app.admin_email');
        $adminPassword = config('app.admin_password');

        if ($adminEmail && $adminPassword) {
            User::updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => 'Admin',
                    'password' => Hash::make($adminPassword),
                    'role' => Role::Admin,
                    'email_verified_at' => now(),
                ]
            );
        }

        // Keep a default test user/agent if needed, using firstOrCreate
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'role' => Role::Agent,
                'email_verified_at' => now(),
            ]
        );

        // Add the requested default Agent user
        User::firstOrCreate(
            ['email' => 'agent@gmail.com'],
            [
                'name' => 'Agent',
                'password' => Hash::make('password123'),
                'role' => Role::Agent,
                'email_verified_at' => now(),
            ]
        );

        $this->call([
            AiAgentSeeder::class,
            TicketSeeder::class,
        ]);
    }
}
