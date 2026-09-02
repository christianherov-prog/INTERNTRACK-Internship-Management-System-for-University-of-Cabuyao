<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Internship;
use App\Models\Program;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Known student accounts for the capstone team & demo walkthroughs.
 *
 * Login credential: username = student_number, password = interntrack123
 *
 * Seeded accounts:
 *   - 2300600: Christian Hero Valinado (BSIT, 4IT-D)
 *   - 2300590: John Taac-Taac (BSIT, 4IT-D) — fresh enrollee / pending placement
 *   - 2300592: Clarence Montealegre (BSIT, 4IT-D) — progressed profile at TechCorp PH
 *
 * Soft-deleted users are restored so re-seed never fails unique constraints.
 */
class StudentAccountsSeeder extends Seeder
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
        $password = Hash::make('interntrack123');

        $techCorp = Company::where('company_name', 'TechCorp PH')->first();
        $coordUser = User::where('role', 'coordinator')->first();
        $facultyUser = User::where('role', 'faculty')->first();
        $supervisorUser = User::where('email', 'patrick.bateman@techcorp.ph')->first()
            ?? User::where('role', 'supervisor')->first();

        $students = [
            [
                'student_number' => '2300600',
                'email'          => 'christian.valinado@uc.edu.ph',
                'profile'        => [
                    'student_number'    => '2300600',
                    'first_name'        => 'Christian Hero',
                    'middle_name'       => 'Aboy',
                    'last_name'         => 'Valinado',
                    'email'             => 'christian.valinado@uc.edu.ph',
                    'contact_number'    => '09123456789',
                    'sex'               => 'Male',
                    'program'           => 'Bachelor of Science in Information Technology',
                    'department'        => 'College of Computing Studies',
                    'year_level'        => 4,
                    'section'           => '4IT-D',
                    'school_year'       => '2025-2026',
                    'semester'          => '2nd Semester',
                    'enrollment_status' => 'Enrolled',
                ],
                'internship'     => null, // fresh / pending_placement
            ],
            [
                'student_number' => '2300590',
                'email'          => 'john.taactaac@uc.edu.ph',
                'profile'        => [
                    'student_number'    => '2300590',
                    'first_name'        => 'John',
                    'middle_name'       => null,
                    'last_name'         => 'Taac-Taac',
                    'email'             => 'john.taactaac@uc.edu.ph',
                    'contact_number'    => '09175550590',
                    'sex'               => 'Male',
                    'program'           => 'Bachelor of Science in Information Technology',
                    'department'        => 'College of Computing Studies',
                    'year_level'        => 4,
                    'section'           => '4IT-D',
                    'school_year'       => '2025-2026',
                    'semester'          => '2nd Semester',
                    'enrollment_status' => 'Enrolled',
                ],
                'internship'     => null, // fresh / pending_placement
            ],
            [
                'student_number' => '2300592',
                'email'          => 'clarence.montealegre@uc.edu.ph',
                'profile'        => [
                    'student_number'    => '2300592',
                    'first_name'        => 'Clarence',
                    'middle_name'       => null,
                    'last_name'         => 'Montealegre',
                    'email'             => 'clarence.montealegre@uc.edu.ph',
                    'contact_number'    => '09175550592',
                    'sex'               => 'Male',
                    'program'           => 'Bachelor of Science in Information Technology',
                    'department'        => 'College of Computing Studies',
                    'year_level'        => 4,
                    'section'           => '4IT-D',
                    'school_year'       => '2025-2026',
                    'semester'          => '2nd Semester',
                    'enrollment_status' => 'Enrolled',
                ],
                'internship'     => [
                    'status'               => 'ongoing',
                    'school_year'          => '2025-2026',
                    'semester'             => '2nd Semester',
                    'term'                 => 'AY 2025-2026, 2nd Semester',
                    'program'              => 'Bachelor of Science in Information Technology',
                    'target_hours'         => 500,
                    'total_hours_rendered' => 280,
                    'start_date'           => now()->subMonths(2)->toDateString(),
                ],
            ],
            [
                'student_number' => '2300601',
                'email'          => 'coed.student@uc.edu.ph',
                'profile'        => [
                    'student_number'    => '2300601',
                    'first_name'        => 'COED',
                    'middle_name'       => null,
                    'last_name'         => 'Student',
                    'email'             => 'coed.student@uc.edu.ph',
                    'contact_number'    => '09175550601',
                    'sex'               => 'Female',
                    'program'           => 'Bachelor of Secondary Education',
                    'department'        => 'College of Education',
                    'year_level'        => 4,
                    'section'           => '4BSED-A',
                    'school_year'       => '2025-2026',
                    'semester'          => '2nd Semester',
                    'enrollment_status' => 'Enrolled',
                ],
                'internship'     => null, // fresh / pending_placement
            ],
            [
                'student_number' => '2300602',
                'email'          => 'coe.student@uc.edu.ph',
                'profile'        => [
                    'student_number'    => '2300602',
                    'first_name'        => 'COE',
                    'middle_name'       => null,
                    'last_name'         => 'Student',
                    'email'             => 'coe.student@uc.edu.ph',
                    'contact_number'    => '09175550602',
                    'sex'               => 'Male',
                    'program'           => 'Bachelor of Science in Civil Engineering',
                    'department'        => 'College of Engineering',
                    'year_level'        => 4,
                    'section'           => '4BSCE-A',
                    'school_year'       => '2025-2026',
                    'semester'          => '2nd Semester',
                    'enrollment_status' => 'Enrolled',
                ],
                'internship'     => null, // fresh / pending_placement
            ],
            [
                'student_number' => '2300608',
                'email'          => 'coe.cpe@uc.edu.ph',
                'profile'        => [
                    'student_number'    => '2300608',
                    'first_name'        => 'COE',
                    'middle_name'       => null,
                    'last_name'         => 'Computer Engineering',
                    'email'             => 'coe.cpe@uc.edu.ph',
                    'contact_number'    => '09175550608',
                    'sex'               => 'Male',
                    'program'           => 'Bachelor of Science in Computer Engineering',
                    'department'        => 'College of Engineering',
                    'year_level'        => 4,
                    'section'           => '4BSCPE-A',
                    'school_year'       => '2025-2026',
                    'semester'          => '2nd Semester',
                    'enrollment_status' => 'Enrolled',
                ],
                'internship'     => null,
            ],
        ];

        foreach ($students as $row) {
            $departmentId = $this->ensureDepartment(
                $row['profile']['department'],
                $this->departmentCode($row['profile']['department'])
            );
            $programId = $this->ensureProgram(
                $row['profile']['program'],
                $departmentId,
                $this->programCode($row['profile']['program'])
            );

            // Username for students = student_number
            $user = User::withTrashed()->updateOrCreate(
                ['student_number' => $row['student_number']],
                [
                    'email'      => $row['email'],
                    'password'   => $password,
                    'role'       => 'student',
                    'is_active'  => true,
                    'deleted_at' => null,
                ]
            );

            if ($user->trashed()) {
                $user->restore();
            }

            $profileData = array_merge($row['profile'], [
                'department_id' => $departmentId,
                'program_id'    => $programId,
                'synced_at'      => now(),
            ]);
            unset($profileData['department'], $profileData['program']);

            $profile = StudentProfile::updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );

            $ay  = $profile->school_year ?: '2025-2026';
            $sem = $profile->semester    ?: '2nd Semester';

            if (!empty($row['internship'])) {
                // Populated internship state for progressed demo accounts (e.g. 2300592)
                $internshipData = array_merge($row['internship'], [
                    'student_id'     => $user->id,
                    'company_id'     => $techCorp?->id,
                    'supervisor_id'  => $supervisorUser?->id,
                    'faculty_id'     => $facultyUser?->id ?? app(\App\Services\FacultySectionAssignmentService::class)->resolveFacultyForProfile($profile)?->id,
                    'coordinator_id' => $coordUser?->id,
                ]);

                $existingInternship = $user->internshipsAsStudent()->first();
                if ($existingInternship) {
                    $existingInternship->update($internshipData);
                } else {
                    $user->internshipsAsStudent()->create($internshipData);
                }
            } else {
                // Fresh internship state
                if (!$user->internshipsAsStudent()->exists()) {
                    $user->internshipsAsStudent()->create([
                        'status'               => 'pending_placement',
                        'school_year'          => $ay,
                        'semester'             => $sem,
                        'term'                 => "AY {$ay}, {$sem}",
                        'program'              => $profile->program,
                        'company_id'           => null,
                        'supervisor_id'        => null,
                        'faculty_id'           => app(\App\Services\FacultySectionAssignmentService::class)->resolveFacultyForProfile($profile)?->id,
                        'coordinator_id'       => null,
                        'target_hours'         => config('interntrack.target_hours', 500),
                        'total_hours_rendered' => 0,
                    ]);
                }
            }
        }

        $this->command?->info('✅ Student accounts seeded:');
        $this->command?->info('  2300600 (Christian Valinado) — interntrack123 (Fresh/Pending)');
        $this->command?->info('  2300590 (John Taac-Taac)     — interntrack123 (Fresh/Pending)');
        $this->command?->info('  2300592 (Clarence Montealegre) — interntrack123 (Populated: TechCorp PH)');
        $this->command?->info('  2300601 (COED Student)       — interntrack123 (Fresh/Pending)');
        $this->command?->info('  2300602 (COE Civil Eng)      — interntrack123 (Fresh/Pending)');
        $this->command?->info('  2300608 (COE CpE)            — interntrack123 (Fresh/Pending)');
    }

    private function departmentCode(string $name): ?string
    {
        return match ($name) {
            'College of Computing Studies' => 'CCS',
            'College of Education' => 'COED',
            'College of Engineering' => 'COE',
            'College of Health and Allied Sciences' => 'CHAS',
            'College of Arts and Sciences' => 'CAS',
            'College of Business, Accountancy and Administration' => 'CBAA',
            default => null,
        };
    }

    private function programCode(string $name): ?string
    {
        return match ($name) {
            'Bachelor of Science in Information Technology' => 'BSIT',
            'Bachelor of Science in Computer Science' => 'BSCS',
            'Bachelor of Secondary Education' => 'BSED',
            'Bachelor of Elementary Education' => 'BEED',
            'Bachelor of Science in Civil Engineering' => 'BSCE',
            'Bachelor of Science in Computer Engineering' => 'BSCPE',
            'Bachelor of Science in Nursing' => 'BSN',
            'Bachelor of Science in Psychology' => 'BSPSY',
            'Bachelor of Science in Business Administration major in Marketing Management' => 'BSBAMM',
            'Bachelor of Science in Business Administration major in Financial Management' => 'BSBAFM',
            'Bachelor of Science in Accountancy' => 'BSA',
            default => null,
        };
    }
}