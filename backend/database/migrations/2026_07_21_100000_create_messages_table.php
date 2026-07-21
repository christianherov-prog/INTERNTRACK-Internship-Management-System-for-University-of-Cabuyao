<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internship-scoped direct messages between participants linked on internships:
 * student_id, faculty_id, supervisor_id, coordinator_id.
 *
 * Flat messages table (not a separate conversations table) — matches existing
 * conventions (notifications, audit_logs) and keeps threads derived as
 * (internship_id + peer pair).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['internship_id', 'sender_id', 'recipient_id']);
            $table->index(['recipient_id', 'read_at']);
            $table->index(['internship_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
