<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        DB::statement('ALTER TABLE notifications MODIFY message TEXT NOT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        DB::statement('ALTER TABLE notifications MODIFY message VARCHAR(255) NOT NULL');
    }
};
