<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Department;
use App\Models\HteRequest;
use App\Models\Internship;
use App\Models\InternshipApplication;
use App\Models\Program;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\FacultySectionAssignmentService;
use App\Services\InternshipProgressService;
use App\Services\ProgramRequirementService;
use App\Support\DepartmentScope;
use App\Support\InternshipProvisioning;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Known student accounts for the capstone team & demo walkthroughs.
 *
 * Login credential: username = student_number, password = interntrack123
 *
 * Seeded accounts:
 *   - 2300600: Christian Hero Valinado (BSIT, 4IT-A) — fresh enrollee / pending placement
 *     Assigned to FAC-1001 (Marvin Bicua)
 *   - 2300590: Angel Luis Taac - Taac (BSIT, 4IT-D) — fresh enrollee / pending placement
 *   - 2300500: Mark Joseph V. Taduran (BSIT, 4IT-D) — fresh enrollee / pending placement
 *   - 2300592: Clarence Montealegre (BSIT, 4IT-D) — progressed profile at Accenture PH
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
        $password = Hash::make(config('interntrack.default_password'));

        // "TechCorp PH" is a retired demo company; "Accenture PH" resolves to the
        // canonical "Accenture Philippines" through the identity key.
        $techCorp = null;
        $accenture = \App\Support\CompanyNameNormalizer::findExisting('Accenture Philippines');
        $ccsFaculty = User::where('faculty_number', 'FAC-CCS-001')->first()
            ?? User::where('faculty_number', 'FAC-1001')->first();
        $supervisorUser = User::where('faculty_number', 'SUP-0002')->first()
            ?? User::where('email', 'adrian.reyes@accenture.ph')->first();
        $facultyResolver = app(FacultySectionAssignmentService::class);

        $students = [
            [
                'student_number' => '2300600',
                'email' => 'christian.valinado@uc.edu.ph',
                'profile' => [
                    'student_number' => '2300600',
                    'first_name' => 'Christian Hero',
                    'middle_name' => 'Aboy',
                    'last_name' => 'Valinado',
                    'email' => 'christian.valinado@uc.edu.ph',
                    'contact_number' => '09123456789',
                    'sex' => 'Male',
                    'program' => 'Bachelor of Science in Information Technology',
                    'department' => 'College of Computing Studies',
                    'year_level' => 4,
                    'section' => '4IT-A',
                    'school_year' => '2025-2026',
                    'semester' => '2nd Semester',
                    'enrollment_status' => 'Enrolled',
                ],
                'internship' => null, // fresh / pending_placement
                'reset_progress' => true, // wipe leftover company / journals / DTR from older demo data
            ],
            [
                'student_number' => '2300590',
                'email' => 'angel.taactaac@uc.edu.ph',
                'profile' => [
                    'student_number' => '2300590',
                    'first_name' => 'Angel Luis',
                    'middle_name' => null,
                    'last_name' => 'Taac - Taac',
                    'email' => 'angel.taactaac@uc.edu.ph',
                    'contact_number' => '09175550590',
                    'sex' => 'Male',
                    'program' => 'Bachelor of Science in Information Technology',
                    'department' => 'College of Computing Studies',
                    'year_level' => 4,
                    'section' => '4IT-D',
                    'school_year' => '2025-2026',
                    'semester' => '2nd Semester',
                    'enrollment_status' => 'Enrolled',
                ],
                'internship' => null, // fresh / pending_placement
            ],
            [
                'student_number' => '2300500',
                'email' => 'mark.taduran@uc.edu.ph',
                'profile' => [
                    'student_number' => '2300500',
                    'first_name' => 'Mark Joseph',
                    'middle_name' => 'V',
                    'last_name' => 'Taduran',
                    'email' => 'mark.taduran@uc.edu.ph',
                    'contact_number' => '09175550500',
                    'sex' => 'Male',
                    'program' => 'Bachelor of Science in Information Technology',
                    'department' => 'College of Computing Studies',
                    'year_level' => 4,
                    'section' => '4IT-D',
                    'school_year' => '2025-2026',
                    'semester' => '2nd Semester',
                    'enrollment_status' => 'Enrolled',
                ],
                'internship' => null, // fresh / pending_placement
            ],
            [
                'student_number' => '2300592',
                'email' => 'clarence.montealegre@uc.edu.ph',
                'profile' => [
                    'student_number' => '2300592',
                    'first_name' => 'Clarence',
                    'middle_name' => null,
                    'last_name' => 'Montealegre',
                    'email' => 'clarence.montealegre@uc.edu.ph',
                    'contact_number' => '09175550592',
                    'sex' => 'Male',
                    'program' => 'Bachelor of Science in Information Technology',
                    'department' => 'College of Computing Studies',
                    'year_level' => 4,
                    'section' => '4IT-D',
                    'school_year' => '2025-2026',
                    'semester' => '2nd Semester',
                    'enrollment_status' => 'Enrolled',
                ],
                'internship' => [
                    'status' => 'ongoing',
                    'school_year' => '2025-2026',
                    'semester' => '2nd Semester',
                    'term' => 'AY 2025-2026, 2nd Semester',
                    'program' => 'Bachelor of Science in Information Technology',
                    'target_hours' => 500,
                    'total_hours_rendered' => 280,
                    'start_date' => now()->subMonths(2)->toDateString(),
                ],
            ],
            [
                'student_number' => '2300601',
                'email' => 'coed.student@uc.edu.ph',
                'profile' => [
                    'student_number' => '2300601',
                    'first_name' => 'COED',
                    'middle_name' => null,
                    'last_name' => 'Student',
                    'email' => 'coed.student@uc.edu.ph',
                    'contact_number' => '09175550601',
                    'sex' => 'Female',
                    'program' => 'Bachelor of Secondary Education',
                    'department' => 'College of Education',
                    'year_level' => 4,
                    'section' => '4BSED-A',
                    'school_year' => '2025-2026',
                    'semester' => '2nd Semester',
                    'enrollment_status' => 'Enrolled',
                ],
                'internship' => null, // fresh / pending_placement
            ],
            [
                'student_number' => '2300602',
                'email' => 'coe.student@uc.edu.ph',
                'profile' => [
                    'student_number' => '2300602',
                    'first_name' => 'COE',
                    'middle_name' => null,
                    'last_name' => 'Student',
                    'email' => 'coe.student@uc.edu.ph',
                    'contact_number' => '09175550602',
                    'sex' => 'Male',
                    'program' => 'Bachelor of Science in Civil Engineering',
                    'department' => 'College of Engineering',
                    'year_level' => 4,
                    'section' => '4BSCE-A',
                    'school_year' => '2025-2026',
                    'semester' => '2nd Semester',
                    'enrollment_status' => 'Enrolled',
                ],
                'internship' => null, // fresh / pending_placement
            ],
            [
                'student_number' => '2300608',
                'email' => 'coe.cpe@uc.edu.ph',
                'profile' => [
                    'student_number' => '2300608',
                    'first_name' => 'COE',
                    'middle_name' => null,
                    'last_name' => 'Computer Engineering',
                    'email' => 'coe.cpe@uc.edu.ph',
                    'contact_number' => '09175550608',
                    'sex' => 'Male',
                    'program' => 'Bachelor of Science in Computer Engineering',
                    'department' => 'College of Engineering',
                    'year_level' => 4,
                    'section' => '4BSCPE-A',
                    'school_year' => '2025-2026',
                    'semester' => '2nd Semester',
                    'enrollment_status' => 'Enrolled',
                ],
                'internship' => null,
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
                    'email' => $row['email'],
                    'password' => $password,
                    'role' => 'student',
                    'is_active' => true,
                    'deleted_at' => null,
                ]
            );

            if ($user->trashed()) {
                $user->restore();
            }

            $profileData = array_merge($row['profile'], [
                'department_id' => $departmentId,
                'program_id' => $programId,
                'synced_at' => now(),
            ]);
            unset($profileData['department'], $profileData['program']);

            $profile = StudentProfile::updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );

            $ay = $profile->school_year ?: '2025-2026';
            $sem = $profile->semester ?: '2nd Semester';

            $programHours = ProgramRequirementService::targetHoursForProfile($profile);
            $profile->loadMissing('program');
            $user->setRelation('studentProfile', $profile);

            $facultyId = $facultyResolver->resolveFacultyForProfile($profile)?->id;
            if (! $facultyId && $ccsFaculty && DepartmentScope::facultyMatchesStudent($ccsFaculty, $profile)) {
                $facultyId = $ccsFaculty->id;
            }
            $coordId = DepartmentScope::coordinatorIdForStudent($user);

            if (! empty($row['internship'])) {
                // Populated internship state for progressed demo accounts (e.g. 2300592)
                $companyId = $row['student_number'] === '2300592'
                    ? ($accenture?->id ?? $techCorp?->id)
                    : $techCorp?->id;
                $internshipData = array_merge($row['internship'], [
                    'student_id' => $user->id,
                    'company_id' => $companyId,
                    'supervisor_id' => $supervisorUser?->id,
                    'faculty_id' => $facultyId,
                    'coordinator_id' => $coordId,
                    'target_hours' => $programHours > 0 ? $programHours : ($row['internship']['target_hours'] ?? 0),
                ]);

                $existingInternship = InternshipProvisioning::openForStudent($user->id)
                    ?? $user->internshipsAsStudent()->first();
                $internship = $existingInternship
                    ? tap($existingInternship)->update($internshipData)
                    : $user->internshipsAsStudent()->create($internshipData);

                $this->seedValidatedHoursIfMissing($internship, (float) ($row['internship']['total_hours_rendered'] ?? 0));
            } else {
                if (! empty($row['reset_progress'])) {
                    $this->resetToFreshEnrollee($user, $profile, $facultyId, $coordId, $programHours, $ay, $sem);
                } else {
                    $open = InternshipProvisioning::openForStudent($user->id);
                    if ($open) {
                        $patch = [];
                        if ($facultyId && (int) $open->faculty_id !== (int) $facultyId) {
                            $patch['faculty_id'] = $facultyId;
                        }
                        if ($coordId && (int) $open->coordinator_id !== (int) $coordId) {
                            $patch['coordinator_id'] = $coordId;
                        } elseif (! $open->coordinator_id && $coordId) {
                            $patch['coordinator_id'] = $coordId;
                        }
                        if ($patch !== []) {
                            $open->forceFill($patch)->saveQuietly();
                        }
                    } else {
                        InternshipProvisioning::createPendingIfNone($user, [
                            'status' => 'pending_placement',
                            'school_year' => $ay,
                            'semester' => $sem,
                            'term' => "AY {$ay}, {$sem}",
                            'program' => $profile->program?->name,
                            'company_id' => null,
                            'supervisor_id' => null,
                            'faculty_id' => $facultyId,
                            'coordinator_id' => $coordId,
                            'target_hours' => $programHours,
                            'total_hours_rendered' => 0,
                        ]);
                    }
                }
            }
        }

        $this->command?->info('✅ Student accounts seeded:');
        $this->command?->info('  2300600 (Christian Valinado) — interntrack123 (Fresh/Pending, FAC-1001 / 4IT-A)');
        $this->command?->info('  2300590 (Angel Luis Taac - Taac) — interntrack123 (Fresh/Pending)');
        $this->command?->info('  2300500 (Mark Joseph Taduran) — interntrack123 (Fresh/Pending)');
        $this->command?->info('  2300592 (Clarence Montealegre) — interntrack123 (Populated: Accenture PH / Adrian Reyes)');
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

    /**
     * Wipe leftover placement, journals, and DTR so a previously populated demo
     * student matches the fresh-enrollee state used by 2300590.
     */
    private function resetToFreshEnrollee(
        User $user,
        StudentProfile $profile,
        ?int $facultyId,
        ?int $coordId,
        int $programHours,
        string $ay,
        string $sem
    ): void {
        $internships = Internship::withTrashed()->where('student_id', $user->id)->orderByDesc('id')->get();

        foreach ($internships as $internship) {
            $internship->journals()->withTrashed()->forceDelete();
            $internship->attendance()->withTrashed()->forceDelete();
            $internship->documents()->withTrashed()->forceDelete();
            $internship->evaluations()->delete();
            $internship->overtimeEntries()->delete();
            $internship->correctionRequests()->delete();
            $internship->workSchedules()->delete();
            $internship->portfolio()->delete();
            $internship->forceFill(['current_placement_id' => null])->saveQuietly();
            if (Schema::hasTable('internship_placements')) {
                $internship->placements()->delete();
            }
        }

        InternshipApplication::where('student_id', $user->id)->delete();
        HteRequest::where('student_id', $user->id)->delete();

        $keep = $internships->first(fn ($row) => ! $row->trashed())
            ?? $internships->first();

        $pending = [
            'status' => 'pending_placement',
            'company_id' => null,
            'supervisor_id' => null,
            'current_placement_id' => null,
            'faculty_id' => $facultyId,
            'coordinator_id' => $coordId,
            'school_year' => $ay,
            'semester' => $sem,
            'term' => "AY {$ay}, {$sem}",
            'program' => $profile->program?->name,
            'target_hours' => $programHours,
            'total_hours_rendered' => 0,
            'start_date' => null,
            'end_date' => null,
        ];

        if ($keep) {
            if ($keep->trashed()) {
                $keep->restore();
            }
            foreach ($internships as $internship) {
                if ((int) $internship->id === (int) $keep->id) {
                    continue;
                }
                if (! $internship->trashed()) {
                    $internship->forceFill(['status' => 'cancelled'])->saveQuietly();
                }
            }
            $keep->forceFill($pending)->save();
            InternshipProgressService::synchronize($keep->fresh());

            return;
        }

        InternshipProvisioning::createPendingIfNone($user, $pending);
    }

    /**
     * Persist validated attendance that matches a seeded hours total so dashboard
     * and records share the same source of truth after refreshTotalHours().
     */
    private function seedValidatedHoursIfMissing(Internship $internship, float $hours): void
    {
        if ($hours <= 0) {
            return;
        }

        $existing = (float) $internship->attendance()->where('status', 'validated')->sum('hours_rendered');
        if ($existing >= $hours) {
            return;
        }

        $remaining = $hours - $existing;
        $fullDays = (int) floor($remaining / 8);
        $leftover = $remaining - ($fullDays * 8);
        $start = now()->subMonths(2)->startOfDay();

        for ($i = 0; $i < $fullDays; $i++) {
            AttendanceLog::create([
                'internship_id' => $internship->id,
                'date' => $start->copy()->addDays($i)->toDateString(),
                'clock_in' => '08:00:00',
                'clock_out' => '16:00:00',
                'hours_rendered' => 8,
                'status' => 'validated',
                'validated_at' => now(),
            ]);
        }

        if ($leftover > 0) {
            AttendanceLog::create([
                'internship_id' => $internship->id,
                'date' => $start->copy()->addDays($fullDays)->toDateString(),
                'clock_in' => '08:00:00',
                'clock_out' => '12:00:00',
                'hours_rendered' => $leftover,
                'status' => 'validated',
                'validated_at' => now(),
            ]);
        }

        $internship->refreshTotalHours();
    }
}
