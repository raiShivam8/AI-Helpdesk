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
            User::withTrashed()->updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => 'Admin',
                    'password' => Hash::make($adminPassword),
                    'role' => Role::Admin,
                    'email_verified_at' => now(),
                    'deleted_at' => null,
                ]
            );
        }

        // Default test user/agent
        User::withTrashed()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'role' => Role::Agent,
                'email_verified_at' => now(),
                'deleted_at' => null,
            ]
        );

        // Default Agent user
        User::withTrashed()->updateOrCreate(
            ['email' => 'agent@gmail.com'],
            [
                'name' => 'Agent',
                'password' => Hash::make('password123'),
                'role' => Role::Agent,
                'email_verified_at' => now(),
                'deleted_at' => null,
            ]
        );

        $this->call([
            AiAgentSeeder::class,
            ProductionSyncSeeder::class,
        ]);
    }
}
