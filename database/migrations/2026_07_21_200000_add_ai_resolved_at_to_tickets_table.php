<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Timestamp set when the AI auto-resolve job successfully resolves a ticket.
            // NULL means the ticket was either not yet resolved or resolved by a human agent.
            $table->timestamp('ai_resolved_at')->nullable()->after('classified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('ai_resolved_at');
        });
    }
};
