<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // MySQL requires modifying the column definition for enum
        DB::statement("ALTER TABLE companies MODIFY COLUMN moa_status ENUM('active','pending','expired','for_renewal','on-process') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE companies MODIFY COLUMN moa_status ENUM('active','pending','expired','for_renewal') NOT NULL DEFAULT 'pending'");
    }
};
