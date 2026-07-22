<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user messaging thread preferences + soft-unsend on messages.
 *
 * - message_thread_states: archive / clear are per viewer (not global).
 * - messages.unsent_at: sender can unsend; row kept for audit; API shows placeholder.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('message_thread_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('internship_id')->constrained()->cascadeOnDelete();
            $table->foreignId('peer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('cleared_before')->nullable();
            $table->unsignedBigInteger('cleared_before_message_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'internship_id', 'peer_id'], 'msg_thread_states_unique');
            $table->index(['user_id', 'archived_at']);
        });

        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'unsent_at')) {
                $table->timestamp('unsent_at')->nullable()->after('read_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'unsent_at')) {
                $table->dropColumn('unsent_at');
            }
        });
        Schema::dropIfExists('message_thread_states');
    }
};
