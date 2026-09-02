<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\FacultySectionAssignment;
use App\Models\Program;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Coordinator, faculty, and demo student accounts for CHAS, CAS, and CBAA.
 * Password for all accounts: interntrack123
 */
class AcademicCollegeAccountsSeeder extends Seeder
{
    public function run(): void
    {
        (new AcademicCollegesSeeder())->run();

        $pw = Hash::make('interntrack123');
        $ay = '2025-2026';
        $sem = '2nd Semester';

        $colleges = [
            [
                'code' => 'CHAS',
                'name' => 'College of Health and Allied Sciences',
                'coord' => ['COR-CHAS-001', 'coord.chas@uc.edu.ph', '09175551001'],
                'faculty' => ['FAC-CHAS-001', 'faculty.chas@uc.edu.ph', '09175551002'],
                'programs' => [
                    [
                        'name' => 'Bachelor of Science in Nursing',
                        'code' => 'BSN',
                        'student_number' => '2300603',
                        'section' => '4BSN-A',
                        'email' => 'chas.nursing@uc.edu.ph',
                        'first_name' => 'CHAS',
                        'last_name' => 'Nursing',
                        'sex' => 'Female',
                    ],
                ],
            ],
            [
                'code' => 'CAS',
                'name' => 'College of Arts and Sciences',
                'coord' => ['COR-CAS-001', 'coord.cas@uc.edu.ph', '09175551003'],
                'faculty' => ['FAC-CAS-001', 'faculty.cas@uc.edu.ph', '09175551004'],
                'programs' => [
                    [
                        'name' => 'Bachelor of Science in Psychology',
                        'code' => 'BSPSY',
                        'student_number' => '2300604',
                        'section' => '4PSY-A',
                        'email' => 'cas.psychology@uc.edu.ph',
                        'first_name' => 'CAS',
                        'last_name' => 'Psychology',
                        'sex' => 'Female',
                    ],
                ],
            ],
            [
                'code' => 'CBAA',
                'name' => 'College of Business, Accountancy and Administration',
                'coord' => ['COR-CBAA-001', 'coord.cbaa@uc.edu.ph', '09175551005'],
                'faculty' => ['FAC-CBAA-001', 'faculty.cbaa@uc.edu.ph', '09175551006'],
                'programs' => [
                    [
                        'name' => 'Bachelor of Science in Business Administration major in Marketing Management',
                        'code' => 'BSBAMM',
                        'student_number' => '2300605',
                        'section' => '4MM-A',
                        'email' => 'cbaa.mm@uc.edu.ph',
                        'first_name' => 'CBAA',
                        'last_name' => 'Marketing',
                        'sex' => 'Female',
                    ],
                    [
                        'name' => 'Bachelor of Science in Business Administration major in Financial Management',
                        'code' => 'BSBAFM',
                        'student_number' => '2300606',
                        'section' => '4FM-A',
                        'email' => 'cbaa.fm@uc.edu.ph',
                        'first_name' => 'CBAA',
                        'last_name' => 'Finance',
                        'sex' => 'Male',
                    ],
                    [
                        'name' => 'Bachelor of Science in Accountancy',
                        'code' => 'BSA',
                        'student_number' => '2300607',
                        'section' => '4BSA-A',
                        'email' => 'cbaa.accountancy@uc.edu.ph',
                        'first_name' => 'CBAA',
                        'last_name' => 'Accountancy',
                        'sex' => 'Male',
                    ],
                ],
            ],
        ];

        foreach ($colleges as $college) {
            $department = Department::where('code', $college['code'])->firstOrFail();

            $coord = $this->ensureStaff(
                $pw,
                'coordinator',
                $college['coord'][0],
                $college['coord'][1],
                $college['code'],
                'Coordinator',
                $college['coord'][2],
                $department->id,
                $college['code'].' Coordinator'
            );

            $faculty = $this->ensureStaff(
                $pw,
                'faculty',
                $college['faculty'][0],
                $college['faculty'][1],
                $college['code'],
                'Faculty',
                $college['faculty'][2],
                $department->id,
                $college['code'].' Faculty'
            );

            foreach ($college['programs'] as $programRow) {
                $program = Program::where('code', $programRow['code'])->firstOrFail();

                FacultySectionAssignment::updateOrCreate(
                    [
                        'section' => $programRow['section'],
                        'school_year' => $ay,
                        'semester' => $sem,
                    ],
                    [
                        'faculty_user_id' => $faculty->id,
                        'program' => $program->name,
                        'is_active' => true,
                    ]
                );

                $student = User::withTrashed()->updateOrCreate(
                    ['student_number' => $programRow['student_number']],
                    [
                        'email' => $programRow['email'],
                        'password' => $pw,
                        'role' => 'student',
                        'is_active' => true,
                        'deleted_at' => null,
                    ]
                );
                if ($student->trashed()) {
                    $student->restore();
                }

                $profile = StudentProfile::updateOrCreate(
                    ['user_id' => $student->id],
                    [
                        'student_number' => $programRow['student_number'],
                        'first_name' => $programRow['first_name'],
                        'middle_name' => null,
                        'last_name' => $programRow['last_name'],
                        'email' => $programRow['email'],
                        'contact_number' => '0917555'.substr($programRow['student_number'], -4),
                        'sex' => $programRow['sex'],
                        'program_id' => $program->id,
                        'department_id' => $department->id,
                        'year_level' => 4,
                        'section' => $programRow['section'],
                        'school_year' => $ay,
                        'semester' => $sem,
                        'enrollment_status' => 'Enrolled',
                        'synced_at' => now(),
                    ]
                );

                $internship = $student->internshipsAsStudent()->first();
                $payload = [
                    'status' => 'pending_placement',
                    'school_year' => $ay,
                    'semester' => $sem,
                    'term' => "AY {$ay}, {$sem}",
                    'program' => $program->name,
                    'faculty_id' => $faculty->id,
                    'coordinator_id' => $coord->id,
                    'company_id' => null,
                    'supervisor_id' => null,
                    'target_hours' => config('interntrack.target_hours', 500),
                    'total_hours_rendered' => 0,
                ];
                if ($internship) {
                    $internship->forceFill($payload)->saveQuietly();
                } else {
                    $student->internshipsAsStudent()->create($payload);
                }
            }
        }

        $this->command?->info('CHAS / CAS / CBAA coordinator, faculty, and student accounts seeded (password: interntrack123).');
    }

    private function ensureStaff(
        string $password,
        string $role,
        string $facultyNumber,
        string $email,
        string $firstName,
        string $lastName,
        string $contact,
        int $departmentId,
        string $position
    ): User {
        $user = User::withTrashed()->updateOrCreate(
            ['faculty_number' => $facultyNumber],
            [
                'email' => $email,
                'password' => $password,
                'role' => $role,
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
                'faculty_number' => $facultyNumber,
                'first_name' => $firstName,
                'middle_name' => null,
                'last_name' => $lastName,
                'email' => $email,
                'contact_number' => $contact,
                'department_id' => $departmentId,
                'position' => $position,
                'employment_status' => 'Regular',
                'synced_at' => now(),
            ]
        );

        return $user->fresh('facultyProfile');
    }
}
