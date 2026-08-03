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
        Schema::create('tickets', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('sender_email');
            $table->string('sender_name');
            $table->string('subject');
            $table->text('body');
            $table->string('status')->default('open');
            $table->string('category')->nullable();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
