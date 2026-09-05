<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\FacultySectionAssignment;
use App\Models\Internship;
use App\Models\Program;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

trait CreatesInternshipFixtures
{
    protected function makeUser(string $role, ?string $identifier = null): User
    {
        $field = $role === 'student' ? 'student_number' : 'faculty_number';
        $user = User::factory()->role($role)->create([
            $field => $identifier ?? strtoupper($role.'-'.fake()->unique()->numerify('####')),
            'password' => Hash::make('password'),
        ]);

        if (in_array($role, ['faculty', 'coordinator', 'director', 'admin'], true)) {
            $this->ensureStaffDepartment($user, 'CCS');
        }

        return $user;
    }

    protected function ensureStaffDepartment(User $user, string $deptCode = 'CCS'): FacultyProfile
    {
        $department = $this->departmentByCode($deptCode);

        return FacultyProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'faculty_number' => $user->faculty_number,
                'first_name' => 'Test',
                'last_name' => ucfirst($user->role),
                'email' => $user->email,
                'department_id' => $department->id,
                'position' => ucfirst($user->role),
                'employment_status' => 'Regular',
            ]
        );
    }

    protected function departmentByCode(string $code): Department
    {
        $names = [
            'CCS' => 'College of Computer Studies',
            'COE' => 'College of Engineering',
            'COED' => 'College of Education',
            'CHAS' => 'College of Health and Allied Sciences',
            'CAS' => 'College of Arts and Sciences',
            'CBAA' => 'College of Business, Accountancy and Administration',
        ];

        return Department::firstOrCreate(
            ['code' => $code],
            ['name' => $names[$code] ?? $code, 'is_active' => true]
        );
    }

    protected function makeStudentInCollege(
        string $deptCode,
        string $programName,
        string $programCode,
        string $section
    ): User {
        $student = $this->makeUser('student', '20'.fake()->unique()->numerify('##-#####'));
        $department = $this->departmentByCode($deptCode);
        $program = Program::firstOrCreate(
            ['name' => $programName],
            ['code' => $programCode, 'department_id' => $department->id, 'is_active' => true]
        );
        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => $student->student_number,
            'first_name' => 'Test',
            'last_name' => $deptCode.' Student',
            'department_id' => $department->id,
            'program_id' => $program->id,
            'section' => $section,
            'school_year' => '2024-2025',
            'semester' => 2,
        ]);

        return $student->fresh('studentProfile.program');
    }

    protected function makeStudentWithSection(string $section = '4ITD'): User
    {
        $student = $this->makeUser('student', '20'.fake()->unique()->numerify('##-#####'));
        $department = Department::firstOrCreate(
            ['code' => 'CCS'],
            ['name' => 'College of Computer Studies', 'is_active' => true]
        );
        $program = Program::firstOrCreate(
            ['name' => 'Bachelor of Science in Information Technology'],
            ['code' => 'BSIT', 'department_id' => $department->id, 'is_active' => true]
        );
        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => $student->student_number,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'department_id' => $department->id,
            'program_id' => $program->id,
            'section' => $section,
            'school_year' => '2024-2025',
            'semester' => 2,
        ]);

        return $student->fresh('studentProfile.program');
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
