<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single optional attachment per message (images + common office docs).
 * Columns may already exist from 2026_07_21_100000 on fresh installs.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table) {
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
        });
    }

    public function down(): void
    {
        // Keep columns if present — shared with create_messages migration.
    }
};
