<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correct college display name seeded from mock iEnroll / demo data.
 */
return new class extends Migration
{
    private const OLD = 'College of Computing and Information Sciences';
    private const NEW = 'College of Computing Studies';

    public function up(): void
    {
        foreach (['student_profiles', 'faculty_profiles'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'college')) {
                continue;
            }
            DB::table($table)
                ->where('college', self::OLD)
                ->update(['college' => self::NEW]);
        }
    }

    public function down(): void
    {
        foreach (['student_profiles', 'faculty_profiles'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'college')) {
                continue;
            }
            DB::table($table)
                ->where('college', self::NEW)
                ->update(['college' => self::OLD]);
        }
    }
};
