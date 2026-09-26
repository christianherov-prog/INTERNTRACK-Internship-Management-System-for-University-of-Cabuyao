<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('journal_entries')) {
            return;
        }

        DB::statement("ALTER TABLE journal_entries MODIFY status ENUM('draft','submitted','approved','needs_revision','rejected','supervisor_note') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::table('journal_entries')->where('status', 'supervisor_note')->update(['status' => 'draft']);
        DB::statement("ALTER TABLE journal_entries MODIFY status ENUM('draft','submitted','approved','needs_revision','rejected') NOT NULL DEFAULT 'draft'");
    }
};
