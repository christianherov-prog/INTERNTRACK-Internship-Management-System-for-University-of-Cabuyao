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
        $pw = Hash::make(config('interntrack.default_password'));
        $ay = '2025-2026';
        $sem = '2nd Semester';

        // ─── Faculty accounts ──────────────────────────────────────────────────
        $facultyRows = [
            [
                'faculty_number' => 'FAC-1001',
                'email' => 'm.bicua@uc.edu.ph',
                'first_name' => 'Marvin',
                'middle_name' => 'M.',
                'last_name' => 'Bicua',
                'sex' => 'Male',
                'department' => 'College of Computing Studies',
                'position' => 'CCS Faculty',
                'sections' => ['4IT-A', '4IT-B'],
            ],
            [
                'faculty_number' => 'FAC-1002',
                'email' => 'a.santos@uc.edu.ph',
                'first_name' => 'Ana',
                'middle_name' => 'L.',
                'last_name' => 'Santos',
                'sex' => 'Female',
                'department' => 'College of Computing Studies',
                'position' => 'CCS Faculty',
                'sections' => ['4IT-C', '4IT-D'],
            ],
        ];

        foreach ($facultyRows as $row) {
            // Username for faculty/staff = faculty_number
            $user = User::withTrashed()->updateOrCreate(
                ['faculty_number' => $row['faculty_number']],
                [
                    'email' => $row['email'],
                    'password' => $pw,
                    'role' => 'faculty',
                    'is_active' => true,
                    'deleted_at' => null,
                ]
            );

            if ($user->trashed()) {
                $user->restore();
            }

            FacultyProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'faculty_number' => $row['faculty_number'],
                    'first_name' => $row['first_name'],
                    'middle_name' => $row['middle_name'] ?? 'N/A',
                    'last_name' => $row['last_name'],
                    'email' => $row['email'],
                    'sex' => $row['sex'],
                    'department_id' => $this->ensureDepartment($row['department']),
                    'position' => $row['position'],
                    'employment_status' => 'Regular',
                    'synced_at' => now(),
                ]
            );

            // Assign sections to this faculty for the current AY/Sem
            foreach ($row['sections'] as $section) {
                FacultySectionAssignment::updateOrCreate(
                    [
                        'section' => $section,
                        'school_year' => $ay,
                        'semester' => $sem,
                    ],
                    [
                        'faculty_user_id' => $user->id,
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->assignCollegeFacultySections($ay, $sem);
        $this->restoreBsitSectionsToPrimaryFaculty($ay, $sem);

        $this->command?->info('✅ Faculty section assignments seeded. Login: FAC-1001 / FAC-1002, password='.config('interntrack.default_password'));
    }

    private function assignCollegeFacultySections(string $ay, string $sem): void
    {
        $maps = [
            // FAC-CCS-001 is the college dummy faculty account — do not steal 4IT sections from FAC-1001.
            'FAC-CCS-001' => [],
            'FAC-COED-001' => [
                ['section' => '4BSED-A', 'program' => 'Bachelor of Secondary Education'],
            ],
            'FAC-COE-001' => [
                ['section' => '4BSCE-A', 'program' => 'Bachelor of Science in Civil Engineering'],
                ['section' => '4BSCPE-A', 'program' => 'Bachelor of Science in Computer Engineering'],
            ],
        ];

        foreach ($maps as $facultyNumber => $rows) {
            $user = User::where('faculty_number', $facultyNumber)->first();
            if (! $user) {
                continue;
            }

            foreach ($rows as $row) {
                FacultySectionAssignment::updateOrCreate(
                    [
                        'section' => $row['section'],
                        'school_year' => $ay,
                        'semester' => $sem,
                    ],
                    [
                        'faculty_user_id' => $user->id,
                        'program' => $row['program'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    /**
     * If a previous seed gave 4IT sections to the dummy FAC-CCS-001 account,
     * return those mappings to FAC-1001 / FAC-1002 without deleting student records.
     */
    private function restoreBsitSectionsToPrimaryFaculty(string $ay, string $sem): void
    {
        $primaryA = User::where('faculty_number', 'FAC-1001')->first();
        $primaryB = User::where('faculty_number', 'FAC-1002')->first();
        $dummy = User::where('faculty_number', 'FAC-CCS-001')->first();
        if (! $primaryA || ! $dummy) {
            return;
        }

        $ownerBySection = [
            '4IT-A' => $primaryA->id,
            '4ITA' => $primaryA->id,
            '4IT-B' => $primaryA->id,
            '4ITB' => $primaryA->id,
            '4IT-C' => $primaryB?->id ?? $primaryA->id,
            '4ITC' => $primaryB?->id ?? $primaryA->id,
            '4IT-D' => $primaryB?->id ?? $primaryA->id,
            '4ITD' => $primaryB?->id ?? $primaryA->id,
        ];

        FacultySectionAssignment::query()
            ->where(function ($q) use ($dummy) {
                $q->where('faculty_user_id', $dummy->id)
                    ->orWhereIn('section', array_keys($ownerBySection));
            })
            ->where('school_year', $ay)
            ->where('semester', $sem)
            ->where(function ($q) {
                $q->whereIn('section', ['4IT-A', '4IT-B', '4IT-C', '4IT-D', '4ITA', '4ITB', '4ITC', '4ITD'])
                    ->orWhere('section', 'like', '4IT%');
            })
            ->get()
            ->each(function (FacultySectionAssignment $assignment) use ($ownerBySection, $primaryA) {
                $ownerId = $ownerBySection[$assignment->section] ?? $primaryA->id;
                if ((int) $assignment->faculty_user_id !== (int) $ownerId) {
                    $assignment->faculty_user_id = $ownerId;
                    $assignment->save();
                }
            });
    }
}
