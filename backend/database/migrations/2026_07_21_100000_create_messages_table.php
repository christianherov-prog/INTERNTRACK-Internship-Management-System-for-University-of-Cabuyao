<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flat internship-scoped DM messages (used by MessageController / Message model).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('messages')) {
            return;
        }

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained('internships')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('sender_role', 40)->nullable();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('recipient_role', 40)->nullable();
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_mime', 120)->nullable();
            $table->unsignedInteger('attachment_size')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['internship_id', 'created_at']);
            $table->index(['sender_id', 'recipient_id']);
            $table->index(['internship_id', 'sender_role', 'recipient_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
