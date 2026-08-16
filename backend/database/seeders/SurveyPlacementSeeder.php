<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Internship;
use App\Models\SupervisorProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Ensures survey / demo accounts have a fully linked internship so Messages
 * shows Student ↔ Faculty ↔ Industry Supervisor ↔ Coordinator peers.
 *
 * Accounts prepared:
 *   Students:     2300600 (Valinado), 2300592 (Montealegre)
 *                 — fully placed for messaging demos
 *                 2300590 (Taac-Taac) is intentionally NOT placed (fresh enrollee)
 *   Faculty:      FAC-1001
 *   Coordinator:  COR-1001
 *   Supervisor:   SUP-1001 (created here if missing)
 *   Company:      first active MOA company (TechCorp PH when freshly seeded)
 *
 * Password for all: interntrack123
 *
 * Run: php artisan db:seed --class=SurveyPlacementSeeder
 * (also called from DatabaseSeeder after staff users exist)
 */
class SurveyPlacementSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('interntrack123');

        $faculty = User::where('username', 'FAC-1001')->where('role', 'faculty')->first();
        $coordinator = User::where('username', 'COR-1001')->where('role', 'coordinator')->first();

        if (!$faculty || !$coordinator) {
            $this->command?->warn('SurveyPlacementSeeder skipped: FAC-1001 / COR-1001 not found. Seed staff first.');
            return;
        }

        $supervisor = User::withTrashed()->updateOrCreate(
            ['username' => 'SUP-1001'],
            [
                'email' => 'supervisor.demo@interntrack.local',
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
                'email' => 'supervisor.demo@interntrack.local',
                'contact_number' => '09170000001',
                'position' => 'Industry Supervisor',
            ]
        );

        $company = Company::where('moa_status', 'active')->orderBy('id')->first()
            ?? Company::orderBy('id')->first();

        $studentUsernames = [];

        foreach ($studentUsernames as $username) {
            $student = User::where('username', $username)->where('role', 'student')->first();
            if (!$student) {
                $this->command?->warn("SurveyPlacementSeeder: student {$username} not found.");
                continue;
            }

            $internship = Internship::withTrashed()
                ->where('student_id', $student->id)
                ->orderByDesc('id')
                ->first();

            if (!$internship) {
                $internship = Internship::create([
                    'student_id' => $student->id,
                    'school_year' => '2025-2026',
                    'semester' => '2nd Semester',
                    'term' => 'AY 2025-2026, 2nd Semester',
                    'program' => 'BS Information Technology',
                    'status' => 'active',
                    'target_hours' => config('interntrack.target_hours', 500),
                    'total_hours_rendered' => 0,
                ]);
            } elseif ($internship->trashed()) {
                $internship->restore();
            }

            $internship->update([
                'company_id' => $company?->id,
                'faculty_id' => $faculty->id,
                'supervisor_id' => $supervisor->id,
                'coordinator_id' => $coordinator->id,
                'target_hours' => config('interntrack.target_hours', 500),
                'status' => in_array($internship->status, ['completed', 'terminated', 'failed', 'expelled'], true)
                    ? $internship->status
                    : 'active',
                'start_date' => $internship->start_date ?? now()->toDateString(),
            ]);

            $this->command?->info(
                "Placed internship #{$internship->id} for {$username}: ".
                "FAC-1001 + SUP-1001 + COR-1001".
                ($company ? " @ {$company->company_name}" : '')
            );
        }
    }
}
