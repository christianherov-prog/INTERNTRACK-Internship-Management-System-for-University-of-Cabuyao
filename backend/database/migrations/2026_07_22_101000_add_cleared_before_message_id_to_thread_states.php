<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clear watermark by message id (avoids same-second datetime races).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('message_thread_states', function (Blueprint $table) {
            if (!Schema::hasColumn('message_thread_states', 'cleared_before_message_id')) {
                $table->unsignedBigInteger('cleared_before_message_id')->nullable()->after('cleared_before');
            }
        });
    }

    public function down(): void
    {
        Schema::table('message_thread_states', function (Blueprint $table) {
            if (Schema::hasColumn('message_thread_states', 'cleared_before_message_id')) {
                $table->dropColumn('cleared_before_message_id');
            }
        });
    }
};
