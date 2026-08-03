<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AiAgentSeeder extends Seeder
{
    /**
     * The well-known email address used to identify the AI agent user.
     * Stored in a constant so other parts of the codebase (e.g. Jobs)
     * can reference it without magic strings.
     */
    public const EMAIL = 'ai@system.local';

    /**
     * Seed the AI agent user.
     *
     * This user is a system account used internally by TicketAutoResolveJob
     * to mark tickets as "being handled by AI". It cannot log in because its
     * password is a random hash that is never disclosed.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => self::EMAIL],
            [
                'name'              => 'AI',
                'password'          => Hash::make(Str::random(64)),
                'role'              => Role::Agent,
                'email_verified_at' => now(),
            ]
        );
    }
}
