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
            // Add classification fields
            $table->text('ai_summary')->nullable();
            $table->decimal('category_confidence', 5, 2)->nullable();
            $table->timestamp('classified_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['ai_summary', 'category_confidence', 'classified_at']);
        });
    }
};
