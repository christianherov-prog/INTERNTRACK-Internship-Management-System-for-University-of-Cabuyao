<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Internship;
use App\Models\Program;
use App\Models\StudentProfile;
use App\Models\SupervisorProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo students at hire-progress milestones for client/adviser walkthroughs.
 *
 * Stages: 0% → 25% → 50% → 75% (pending absorption) → 100% (absorbed | not_hired)
 * Login password for all: interntrack123
 */
class HireProgressDemoSeeder extends Seeder
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
        $company = Company::where('company_name', 'TechCorp PH')->first()
            ?? Company::orderBy('id')->first();
        $coord = User::where('role', 'coordinator')->first();
        $faculty = User::where('role', 'faculty')->first();
        $director = User::where('username', 'DIR-1001')->first()
            ?? User::where('role', 'director')->first();

        if (!$company || !$coord || !$faculty) {
            $this->command?->warn('HireProgressDemoSeeder skipped: need company, coordinator, and faculty first.');
            return;
        }

        $supervisor = User::withTrashed()->updateOrCreate(
            ['username' => 'SUP-DEMO1'],
            [
                'email' => 'demo.supervisor@interntrack.local',
                'password' => $password,
                'role' => 'supervisor',
                'is_active' => true,
                'deleted_at' => null,
            ]
        );
        if ($supervisor->trashed()) {
            $supervisor->restore();
        }

        SupervisorProfile::updateOrCreate(
            ['user_id' => $supervisor->id],
            [
                'first_name' => 'Demo',
                'last_name' => 'Supervisor',
                'email' => 'demo.supervisor@interntrack.local',
                'contact_number' => '09170000000',
                'sex' => 'Male',
                'position' => 'Industry Supervisor (Demo)',
            ]
        );

        $demos = [
            [
                'username' => 'DEMO-0100N',
                'first' => 'Felix',
                'last' => 'NotHired',
                'email' => 'demo.nothired@interntrack.local',
                'hours' => 360,
                'status' => 'completed',
                'absorption_status' => 'not_hired',
                'label' => '100% — not hired',
            ],
        ];

        foreach ($demos as $demo) {
            $departmentId = $this->ensureDepartment('College of Computing Studies', 'CCS');
            $programId = $this->ensureProgram('Bachelor of Science in Information Technology', $departmentId, 'BSIT');

            $user = User::withTrashed()->updateOrCreate(
                ['username' => $demo['username']],
                [
                    'email' => $demo['email'],
                    'password' => $password,
                    'role' => 'student',
                    'is_active' => true,
                    'deleted_at' => null,
                ]
            );
            if ($user->trashed()) {
                $user->restore();
            }

            StudentProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'student_number' => $demo['username'],
                    'first_name' => $demo['first'],
                    'last_name' => $demo['last'],
                    'email' => $demo['email'],
                    'contact_number' => '09171234567',
                    'sex' => in_array($demo['first'], ['Ana', 'Mia'], true) ? 'Female' : 'Male',
                    'program_id' => $programId,
                    'department_id' => $departmentId,
                    'year_level' => 4,
                    'section' => '4IT-D',
                    'school_year' => '2025-2026',
                    'semester' => '2nd Semester',
                    'enrollment_status' => 'Enrolled',
                    'synced_at' => now(),
                ]
            );

            $payload = [
                'company_id' => $company->id,
                'supervisor_id' => $supervisor->id,
                'faculty_id' => $faculty->id,
                'coordinator_id' => $coord->id,
                'school_year' => '2025-2026',
                'semester' => '2nd Semester',
                'term' => 'AY 2025-2026',
                'program' => 'Bachelor of Science in Information Technology',
                'target_hours' => 360,
                'total_hours_rendered' => $demo['hours'],
                'status' => $demo['status'],
                'status_reason' => $demo['status'] === 'completed'
                    ? 'Demo seed: internship completed for hire-progress walkthrough.'
                    : null,
                'start_date' => now()->subMonths(4)->toDateString(),
                'end_date' => $demo['status'] === 'completed' ? now()->subDays(7)->toDateString() : null,
                'expected_end_date' => now()->addWeeks(2)->toDateString(),
                'absorption_status' => $demo['absorption_status'],
                'absorbed_at' => ($demo['absorption_status'] ?? null) === 'absorbed'
                    ? now()->subDays(3)->toDateString()
                    : null,
                'job_title' => $demo['job_title'] ?? null,
                'absorption_notes' => match ($demo['absorption_status'] ?? null) {
                    'absorbed' => 'Demo: PALD Director recorded absorbed / hired.',
                    'not_hired' => 'Demo: PALD Director recorded not hired.',
                    'pending' => 'Demo: waiting for Director confirmation.',
                    default => null,
                },
                'absorption_recorded_by' => in_array($demo['absorption_status'] ?? null, ['absorbed', 'not_hired'], true)
                    ? ($director?->id)
                    : null,
                'absorption_recorded_at' => in_array($demo['absorption_status'] ?? null, ['absorbed', 'not_hired'], true)
                    ? now()->subDays(2)
                    : null,
                'absorption_recorded_by_role' => in_array($demo['absorption_status'] ?? null, ['absorbed', 'not_hired'], true)
                    ? 'director'
                    : null,
                'student_declared_hired' => ($demo['absorption_status'] ?? null) === 'pending',
                'student_declared_at' => ($demo['absorption_status'] ?? null) === 'pending' ? now()->subDays(5) : null,
                'student_declaration_notes' => ($demo['absorption_status'] ?? null) === 'pending'
                    ? 'Demo: student reported they were hired — Director confirms Yes/No.'
                    : null,
            ];

            $existing = Internship::where('student_id', $user->id)
                ->where('term', 'AY 2025-2026, Sem 2 (Hire Progress Demo)')
                ->first();

            if ($existing) {
                $existing->update($payload);
            } else {
                // Replace leftover rows so the demo internship is the current one.
                Internship::withTrashed()->where('student_id', $user->id)->forceDelete();
                Internship::create(array_merge($payload, ['student_id' => $user->id]));
            }
        }

        $this->command?->info('Hire-progress demo seeded:');
        $this->command?->info('  Supervisor: SUP-DEMO1 / interntrack123');
        foreach ($demos as $demo) {
            $this->command?->info("  {$demo['username']} — {$demo['label']}");
        }
    }
}
