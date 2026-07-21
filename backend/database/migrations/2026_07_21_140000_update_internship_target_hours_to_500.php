<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CCS OJT requirement is 500 hours (uniform for BSIT / BSCS).
 * Updates column default and migrates existing 360-hour targets to 500.
 */
return new class extends Migration {
    public function up(): void
    {
        $target = (int) config('interntrack.target_hours', 500);

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE internships MODIFY target_hours INT NOT NULL DEFAULT {$target}");
        } else {
            // SQLite: recreate default via table change is awkward; update rows + leave schema.
        }

        // Existing rows still on the old default
        DB::table('internships')->where('target_hours', 360)->update(['target_hours' => $target]);
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE internships MODIFY target_hours INT NOT NULL DEFAULT 360');
        }

        DB::table('internships')->where('target_hours', 500)->update(['target_hours' => 360]);
    }
};
