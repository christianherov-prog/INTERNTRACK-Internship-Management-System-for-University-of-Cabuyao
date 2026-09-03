<?php

use Database\Seeders\AcademicCollegesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Insert CHAS / CAS / CBAA colleges and programs on existing databases
     * without requiring a full re-seed.
     */
    public function up(): void
    {
        if (! Schema::hasTable('departments') || ! Schema::hasTable('programs')) {
            return;
        }

        (new AcademicCollegesSeeder())->run();
    }

    public function down(): void
    {
        if (! Schema::hasTable('programs') || ! Schema::hasTable('departments')) {
            return;
        }

        $programNames = [
            'Bachelor of Science in Nursing',
            'Bachelor of Science in Psychology',
            'Bachelor of Science in Business Administration major in Marketing Management',
            'Bachelor of Science in Business Administration major in Financial Management',
            'Bachelor of Science in Accountancy',
        ];

        \App\Models\Program::whereIn('name', $programNames)->delete();

        \App\Models\Department::whereIn('code', ['CHAS', 'CAS', 'CBAA'])->delete();
    }
};
