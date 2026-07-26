<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Duplicate of 2026_07_20_220000_*; keep for older checkouts, skip if already present.
        if (Schema::hasColumn('users', 'notification_preferences')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('avatar_path');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'notification_preferences')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
