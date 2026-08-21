<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\FacultySectionAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Maps UC section codes to faculty supervisors per AY/Sem.
 *
 * Login credential: username = faculty_number (e.g. FAC-1001), password = interntrack123
 *
 * Section format: 1IT-A, 1IT-B, 1IT-C
 *                 2IT-A, 2IT-B, 2IT-C
 *                 3IT-A, 3IT-B, 3IT-C
 *                 4IT-A, 4IT-B, 4IT-C
 */
class FacultySectionAssignmentSeeder extends Seeder
{
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

    public function run(): void
    {
        $pw  = Hash::make('interntrack123');
        $ay  = '2025-2026';
        $sem = '2nd Semester';

        // ─── Faculty accounts ──────────────────────────────────────────────────
        $facultyRows = [
            [
                'faculty_number' => 'FAC-1001',
                'email'          => 'm.bicua@uc.edu.ph',
                'first_name'     => 'Marvin',
                'middle_name'    => 'M.',
                'last_name'      => 'Bicua',
                'sex'            => 'Male',
                'department'     => 'College of Computing Studies',
                'position'       => 'CCS Faculty',
                'sections'       => ['4IT-A', '4IT-B', '4IT-C', '4IT-D'],
            ],
        ];

        foreach ($facultyRows as $row) {
            // Username for faculty/staff = faculty_number
            $user = User::withTrashed()->updateOrCreate(
                ['faculty_number' => $row['faculty_number']],
                [
                    'email'      => $row['email'],
                    'password'   => $pw,
                    'role'       => 'faculty',
                    'is_active'  => true,
                    'deleted_at' => null,
                ]
            );

            if ($user->trashed()) {
                $user->restore();
            }

            FacultyProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'faculty_number'    => $row['faculty_number'],
                    'first_name'        => $row['first_name'],
                    'middle_name'       => $row['middle_name'] ?? 'N/A',
                    'last_name'         => $row['last_name'],
                    'email'             => $row['email'],
                    'sex'               => $row['sex'],
                    'department_id'     => $this->ensureDepartment($row['department']),
                    'position'          => $row['position'],
                    'employment_status' => 'Regular',
                    'synced_at'         => now(),
                ]
            );

            // Assign sections to this faculty for the current AY/Sem
            foreach ($row['sections'] as $section) {
                FacultySectionAssignment::updateOrCreate(
                    [
                        'section'       => $section,
                        'school_year' => $ay,
                        'semester'      => $sem,
                    ],
                    [
                        'faculty_user_id' => $user->id,
                        'is_active'       => true,
                    ]
                );
            }
        }

        $this->command?->info('✅ Faculty section assignments seeded. Login: username=FAC-1001, password=interntrack123');
    }
}
