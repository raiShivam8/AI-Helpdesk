<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: add_body_html_to_ticket_replies_table
 *
 * Adds the nullable `body_html` column to the `ticket_replies` table
 * to store the sanitized rich-text HTML version of a reply.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->text('body_html')
                  ->nullable()
                  ->after('body');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->dropColumn('body_html');
        });
    }
};
