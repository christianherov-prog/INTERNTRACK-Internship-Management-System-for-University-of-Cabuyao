<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\FacultySectionAssignment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequirementTargetOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_coe_coordinator_sees_college_students_and_sections(): void
    {
        [$coord, $faculty, $civil, $cpe] = $this->seedCoeCollege();

        Sanctum::actingAs($coord);

        $this->getJson('/api/v1/coordinator/requirements/options')
            ->assertOk()
            ->assertJsonCount(2, 'students')
            ->assertJsonFragment(['id' => $civil->id, 'section' => '4BSCE-A'])
            ->assertJsonFragment(['id' => $cpe->id, 'section' => '4BSCPE-A'])
            ->assertJsonFragment(['id' => '4BSCE-A', 'name' => '4BSCE-A'])
            ->assertJsonFragment(['id' => '4BSCPE-A', 'name' => '4BSCPE-A']);
    }

    public function test_coe_faculty_sees_assigned_students_and_sections(): void
    {
        [$coord, $faculty, $civil, $cpe] = $this->seedCoeCollege();

        Sanctum::actingAs($faculty);

        $this->getJson('/api/v1/faculty/requirements/options')
            ->assertOk()
            ->assertJsonCount(2, 'students')
            ->assertJsonFragment(['id' => $civil->id, 'section' => '4BSCE-A'])
            ->assertJsonFragment(['id' => $cpe->id, 'section' => '4BSCPE-A']);
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: User}
     */
    private function seedCoeCollege(): array
    {
        $department = Department::create([
            'name' => 'College of Engineering',
            'code' => 'COE',
            'is_active' => true,
        ]);

        $coord = User::factory()->role('coordinator')->create(['faculty_number' => 'COR-COE-TEST']);
        $faculty = User::factory()->role('faculty')->create(['faculty_number' => 'FAC-COE-TEST']);

        foreach ([$coord, $faculty] as $staff) {
            FacultyProfile::create([
                'user_id' => $staff->id,
                'faculty_number' => $staff->faculty_number,
                'first_name' => 'COE',
                'last_name' => $staff->role === 'coordinator' ? 'Coordinator' : 'Faculty',
                'email' => $staff->email,
                'department_id' => $department->id,
                'position' => 'COE Staff',
                'employment_status' => 'Regular',
            ]);
        }

        $civil = $this->makeCoeStudent('2300691', 'Civil', '4BSCE-A', $department->id);
        $cpe = $this->makeCoeStudent('2300692', 'Computer', '4BSCPE-A', $department->id);

        FacultySectionAssignment::create([
            'section' => '4BSCE-A',
            'program' => 'Bachelor of Science in Civil Engineering',
            'school_year' => '2025-2026',
            'semester' => '2nd Semester',
            'faculty_user_id' => $faculty->id,
            'is_active' => true,
        ]);
        FacultySectionAssignment::create([
            'section' => '4BSCPE-A',
            'program' => 'Bachelor of Science in Computer Engineering',
            'school_year' => '2025-2026',
            'semester' => '2nd Semester',
            'faculty_user_id' => $faculty->id,
            'is_active' => true,
        ]);

        return [$coord->fresh('facultyProfile'), $faculty->fresh('facultyProfile'), $civil, $cpe];
    }

    private function makeCoeStudent(string $studentNumber, string $lastName, string $section, int $departmentId): User
    {
        $student = User::factory()->role('student')->create(['student_number' => $studentNumber]);
        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => $studentNumber,
            'first_name' => 'COE',
            'last_name' => $lastName,
            'section' => $section,
            'department_id' => $departmentId,
            'year_level' => 4,
            'school_year' => '2025-2026',
            'semester' => '2nd Semester',
            'enrollment_status' => 'Enrolled',
        ]);

        return $student->fresh('studentProfile');
    }
}
