<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\FacultySectionAssignment;
use App\Models\Internship;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

trait CreatesInternshipFixtures
{
    protected function makeUser(string $role, ?string $identifier = null): User
    {
        $field = $role === 'student' ? 'student_number' : 'faculty_number';
        return User::factory()->role($role)->create([
            $field => $identifier ?? strtoupper($role.'-'.fake()->unique()->numerify('####')),
            'password' => Hash::make('password'),
        ]);
    }

    protected function makeStudentWithSection(string $section = '4ITD'): User
    {
        $student = $this->makeUser('student', '20'.fake()->unique()->numerify('##-#####'));
        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => $student->student_number,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'program' => 'BSIT',
            'course_name' => 'BSIT',
            'section' => $section,
            'school_year' => '2024-2025',
            'semester' => 2,
        ]);

        return $student->fresh('studentProfile');
    }

    protected function mapFacultyForSection(User $faculty, string $section = '4ITD'): FacultySectionAssignment
    {
        return FacultySectionAssignment::create([
            'program' => 'BSIT',
            'section' => $section,
            'school_year' => '2024-2025',
            'semester' => 2,
            'faculty_user_id' => $faculty->id,
            'is_active' => true,
        ]);
    }

    protected function makeEligibleCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'company_name' => 'Eligible HTE '.fake()->unique()->numerify('###'),
            'moa_status' => 'active',
            'is_active' => true,
            'moa_expiry_date' => now()->addYear()->toDateString(),
            'slots_available' => 5,
        ], $overrides));
    }

    protected function makePendingInternship(User $student): Internship
    {
        $internship = Internship::where('student_id', $student->id)->first() ?? new Internship(['student_id' => $student->id]);
        $internship->fill([
            'status' => 'pending_placement',
            'target_hours' => 360,
            'total_hours_rendered' => 0,
            'school_year' => '2024-2025',
            'semester' => 2,
            'term' => 'AY 2024-2025, Sem 2',
            'program' => 'BSIT',
        ]);
        $internship->save();

        return $internship;
    }

    protected function makeActiveInternship(User $student, Company $company, User $supervisor, User $faculty, User $coordinator): Internship
    {
        $internship = Internship::where('student_id', $student->id)->first() ?? new Internship(['student_id' => $student->id]);
        $internship->fill([
            'company_id' => $company->id,
            'supervisor_id' => $supervisor->id,
            'faculty_id' => $faculty->id,
            'coordinator_id' => $coordinator->id,
            'status' => 'active',
            'target_hours' => 360,
            'total_hours_rendered' => 50,
            'start_date' => now()->subDays(40),
            'school_year' => '2024-2025',
            'semester' => 2,
            'term' => 'AY 2024-2025, Sem 2',
            'program' => 'BSIT',
        ]);
        $internship->save();

        return $internship;
    }
}
