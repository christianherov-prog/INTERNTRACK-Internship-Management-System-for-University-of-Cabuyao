<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('journal_entries', 'hours_declared')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->dropColumn('hours_declared');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('journal_entries', 'hours_declared')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->decimal('hours_declared', 5, 2)->default(8)->after('challenges');
            });
        }
    }
};
