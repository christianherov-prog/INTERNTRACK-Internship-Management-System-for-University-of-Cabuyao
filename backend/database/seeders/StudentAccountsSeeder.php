<?php

namespace Database\Seeders;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Known student accounts for the capstone team.
 *
 * Original path for 2300600: Eloquent via DatabaseSeeder (User + StudentProfile),
 * password Hash::make — not admin API, not raw SQL. This seeder keeps that path
 * and also creates a pending_placement internship (company/coordinator null) so
 * teammates get the same related rows after migrate:fresh --seed.
 *
 * Soft-deleted users are included via withTrashed() and restored so re-seed
 * never fails unique constraints or leaves inactive ghost rows.
 */
class StudentAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('interntrack123');

        $students = [
            [
                'username' => '2300600',
                'email' => 'christian.valinado@uc.edu.ph',
                'profile' => [
                    'student_number' => '2300600',
                    'first_name' => 'Christian Hero',
                    'middle_name' => 'Aboy',
                    'last_name' => 'Valinado',
                    'email' => 'christian.valinado@uc.edu.ph',
                    'contact_number' => '09123456789',
                    'birthday' => '2000-01-01',
                    'sex' => 'Male',
                    'program' => 'BS Information Technology',
                    'college' => 'College of Computing and Information Sciences',
                    'department' => 'Information Technology',
                    'course_name' => 'BS Information Technology',
                    'year_level' => 4,
                    'section' => '4ITD',
                    'academic_year' => '2025-2026',
                    'semester' => 1,
                    'enrollment_status' => 'Enrolled',
                ],
            ],
            [
                'username' => '2300592',
                'email' => 'clarence.montealegre@uc.edu.ph',
                'profile' => [
                    'student_number' => '2300592',
                    'first_name' => 'Clarence',
                    'middle_name' => null,
                    'last_name' => 'Montealegre',
                    'email' => 'clarence.montealegre@uc.edu.ph',
                    'contact_number' => null,
                    'birthday' => null,
                    'sex' => null,
                    'program' => 'BS Information Technology',
                    'college' => 'College of Computing and Information Sciences',
                    'department' => 'Information Technology',
                    'course_name' => 'BS Information Technology',
                    'year_level' => 4,
                    'section' => '4ITD',
                    'academic_year' => '2025-2026',
                    'semester' => 1,
                    'enrollment_status' => 'Enrolled',
                ],
            ],
        ];

        foreach ($students as $row) {
            // withTrashed: soft-deleted rows match by username instead of creating duplicates.
            $user = User::withTrashed()->updateOrCreate(
                ['username' => $row['username']],
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

            $profile = StudentProfile::updateOrCreate(
                ['user_id' => $user->id],
                array_merge($row['profile'], ['synced_at' => now()])
            );

            $ay = $profile->academic_year ?: '2025-2026';
            $sem = (int) ($profile->semester ?: 1);

            // Same shape as StudentController::internship() lazy-create, but term
            // comes from the student profile (current AY/Sem for both accounts).
            if (!$user->internshipsAsStudent()->exists()) {
                $user->internshipsAsStudent()->create([
                    'status' => 'pending_placement',
                    'academic_year' => $ay,
                    'semester' => $sem,
                    'term' => "AY {$ay}, Sem {$sem}",
                    'program' => $profile->program ?: $profile->course_name,
                    'company_id' => null,
                    'supervisor_id' => null,
                    'faculty_id' => null,
                    'coordinator_id' => null,
                    'target_hours' => 360,
                    'total_hours_rendered' => 0,
                ]);
            }
        }

        $this->command?->info('Student accounts seeded: 2300600 (Valinado), 2300592 (Montealegre).');
    }
}