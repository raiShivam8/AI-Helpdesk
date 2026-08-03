<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\SenderType;

/**
 * Migration: add_sender_type_to_ticket_replies_table
 *
 * Adds the `sender_type` column to the existing `ticket_replies` table.
 *
 * Design decisions:
 *   - `string` type (not enum) keeps the schema driver-agnostic and lets
 *     PHP own the constraint via SenderType::cases(), consistent with how
 *     `status`, `category`, and `priority` are stored on the tickets table.
 *   - Default of 'agent' covers all existing rows created before this
 *     migration ran — every reply so far was submitted by an authenticated
 *     agent or admin through the portal, so Agent is the correct backfill.
 *   - Placed after `body` so the column order mirrors the logical read order
 *     (who wrote it, what they wrote, from which side of the conversation).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->string('sender_type')
                  ->default(SenderType::Agent->value)  // 'agent'
                  ->after('body');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->dropColumn('sender_type');
        });
    }
};
