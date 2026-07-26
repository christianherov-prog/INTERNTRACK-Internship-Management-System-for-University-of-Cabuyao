<?php

namespace Database\Seeders;

use App\Models\FacultyProfile;
use App\Models\FacultySectionAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Maps UC BSIT section codes (4ITA–4ITD) to faculty supervisors per AY/Sem.
 * Coordinators do not pick faculty at placement — this table drives auto-assignment.
 */
class FacultySectionAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $pw = Hash::make('interntrack123');
        $program = 'BS Information Technology';
        $ay = '2025-2026';
        $sem = 1;

        $facultyRows = [
            ['username' => 'FAC-1001', 'section' => '4ITD', 'first' => 'Marvin', 'last' => 'Bicua', 'emp' => 'FAC-1001', 'sex' => 'Male'],
            ['username' => 'FAC-1002', 'section' => '4ITC', 'first' => 'Andrea', 'last' => 'Reyes', 'emp' => 'FAC-1002', 'sex' => 'Female'],
            ['username' => 'FAC-1003', 'section' => '4ITB', 'first' => 'Roberto', 'last' => 'Lim', 'emp' => 'FAC-1003', 'sex' => 'Male'],
            ['username' => 'FAC-1004', 'section' => '4ITA', 'first' => 'Maria', 'last' => 'Santos', 'emp' => 'FAC-1004', 'sex' => 'Female'],
        ];

        foreach ($facultyRows as $row) {
            $user = User::withTrashed()->updateOrCreate(
                ['username' => $row['username']],
                [
                    'email'      => strtolower(str_replace('-', '.', $row['username'])) . '@uc.edu.ph',
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
                    'employee_number'   => $row['emp'],
                    'first_name'        => $row['first'],
                    'middle_name'       => null,
                    'last_name'         => $row['last'],
                    'email'             => strtolower(str_replace('-', '.', $row['username'])) . '@uc.edu.ph',
                    'sex'               => $row['sex'],
                    'department'        => 'Information Technology',
                    'college'           => 'College of Computing Studies',
                    'position'          => 'OJT Teacher',
                    'employment_status' => 'Regular',
                    'synced_at'         => now(),
                ]
            );

            FacultySectionAssignment::updateOrCreate(
                [
                    'program'       => $program,
                    'section'       => $row['section'],
                    'academic_year' => $ay,
                    'semester'      => $sem,
                ],
                [
                    'faculty_user_id' => $user->id,
                    'is_active'       => true,
                ]
            );
        }

        // Demo mappings for mock MISD students (AY 2025-2026 Sem 2)
        $demoAy = '2025-2026';
        $demoSem = 2;
        $demoMap = [
            '4ITA' => User::where('username', 'FAC-1004')->first(),
            '4ITB' => User::where('username', 'FAC-1003')->first(),
            '4ITC' => User::where('username', 'FAC-1002')->first(),
        ];

        foreach ($demoMap as $section => $facultyUser) {
            if (!$facultyUser) {
                continue;
            }
            FacultySectionAssignment::updateOrCreate(
                [
                    'program'       => $program,
                    'section'       => $section,
                    'academic_year' => $demoAy,
                    'semester'      => $demoSem,
                ],
                [
                    'faculty_user_id' => $facultyUser->id,
                    'is_active'       => true,
                ]
            );
        }

        $this->command?->info('Section–faculty mappings seeded: 4ITA, 4ITB, 4ITC, 4ITD.');
    }
}
