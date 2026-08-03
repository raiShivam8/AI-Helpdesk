<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_ticket_replies_table
 *
 * Stores every reply that an authenticated user posts against a ticket.
 * Each reply belongs to one ticket and one user (the replier).
 *
 * Columns:
 *   id              – auto-incrementing primary key
 *   ticket_id       – FK → tickets.id  (cascade delete so replies vanish with their ticket)
 *   user_id         – FK → users.id    (nullOnDelete so replies survive user soft-deletes)
 *   body            – the full reply text
 *   created_at /
 *   updated_at      – Eloquent timestamps
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')
                  ->constrained('tickets')
                  ->cascadeOnDelete();        // Remove replies when a ticket is deleted

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->nullOnDelete();           // Keep replies even if the user is soft-deleted

            $table->text('body');             // Reply message body (no length limit)

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_replies');
    }
};
