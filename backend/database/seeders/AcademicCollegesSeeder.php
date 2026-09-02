<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Program;
use Illuminate\Database\Seeder;

/**
 * Academic colleges (departments) and their programs.
 *
 * College of Computing Studies / COED / COE are created in DatabaseSeeder
 * alongside staff accounts. This seeder adds additional colleges so they
 * exist after `db:seed` and after `migrate` on existing databases.
 */
class AcademicCollegesSeeder extends Seeder
{
    public function run(): void
    {
        $chasId = $this->ensureDepartment('College of Health and Allied Sciences', 'CHAS');
        $this->ensureProgram('Bachelor of Science in Nursing', $chasId, 'BSN');

        $casId = $this->ensureDepartment('College of Arts and Sciences', 'CAS');
        $this->ensureProgram('Bachelor of Science in Psychology', $casId, 'BSPSY');

        $cbaaId = $this->ensureDepartment('College of Business, Accountancy and Administration', 'CBAA');
        $this->ensureProgram(
            'Bachelor of Science in Business Administration major in Marketing Management',
            $cbaaId,
            'BSBAMM'
        );
        $this->ensureProgram(
            'Bachelor of Science in Business Administration major in Financial Management',
            $cbaaId,
            'BSBAFM'
        );
        $this->ensureProgram('Bachelor of Science in Accountancy', $cbaaId, 'BSA');
    }

    private function ensureDepartment(string $name, ?string $code = null): int
    {
        $name = trim($name);
        $code = $code ?: strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($name, 0, 10)) ?: 'DEPT');

        $department = Department::firstOrCreate(
            ['name' => $name],
            ['code' => $code, 'is_active' => true]
        );

        return $department->id;
    }

    private function ensureProgram(string $name, int $departmentId, ?string $code = null): int
    {
        $name = trim($name);
        $code = $code ?: strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($name, 0, 10)) ?: 'PROG');

        $program = Program::firstOrCreate(
            ['name' => $name],
            ['department_id' => $departmentId, 'code' => $code, 'is_active' => true]
        );

        return $program->id;
    }
}
