<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ensure flat-message role/attachment columns exist (idempotent for older DBs).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'sender_role')) {
                $table->string('sender_role', 40)->nullable()->after('sender_id');
            }
            if (!Schema::hasColumn('messages', 'recipient_id')) {
                $table->foreignId('recipient_id')->nullable()->after('sender_role')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('messages', 'recipient_role')) {
                $table->string('recipient_role', 40)->nullable()->after('recipient_id');
            }
            if (!Schema::hasColumn('messages', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('body');
            }
            if (!Schema::hasColumn('messages', 'attachment_original_name')) {
                $table->string('attachment_original_name')->nullable()->after('attachment_path');
            }
            if (!Schema::hasColumn('messages', 'attachment_mime')) {
                $table->string('attachment_mime', 120)->nullable()->after('attachment_original_name');
            }
            if (!Schema::hasColumn('messages', 'attachment_size')) {
                $table->unsignedInteger('attachment_size')->nullable()->after('attachment_mime');
            }
            if (!Schema::hasColumn('messages', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('attachment_size');
            }
        });
    }

    public function down(): void
    {
        // Keep columns — irreversible safely for merge history.
    }
};
