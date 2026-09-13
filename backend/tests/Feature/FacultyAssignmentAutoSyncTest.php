<?php

namespace Tests\Feature;

use App\Models\FacultyProfile;
use App\Models\FacultySectionAssignment;
use App\Models\Internship;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Support\CreatesInternshipFixtures;

class FacultyAssignmentAutoSyncTest extends TestCase
{
    use RefreshDatabase, CreatesInternshipFixtures;

    public function test_newly_enrolled_student_internship_automatically_assigns_faculty_teacher(): void
    {
        // 1. Create a faculty member assigned to BSIT section 4ITA
        $faculty = $this->makeUser('faculty', 'FAC-001');
        FacultyProfile::updateOrCreate(
            ['user_id' => $faculty->id],
            [
                'faculty_number' => 'FAC-001',
                'first_name' => 'Prof',
                'last_name' => 'Teacher',
            ]
        );

        FacultySectionAssignment::create([
            'program' => 'BSIT',
            'section' => '4ITA',
            'school_year' => '2025-2026',
            'semester' => 2,
            'faculty_user_id' => $faculty->id,
            'is_active' => true,
        ]);

        // 2. Create a newly enrolled student without an internship record yet
        $student = $this->makeUser('student', 'STUD-001');
        $profile = StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => 'STUD-001',
            'first_name' => 'New',
            'last_name' => 'Student',
            'program' => 'BSIT',
            'course_name' => 'BSIT',
            'section' => '4ITA',
            'school_year' => '2025-2026',
            'semester' => 2,
            'enrollment_status' => 'Enrolled',
        ]);

        // 3. When student logs in and accesses dashboard (triggering lazy internship creation)
        Sanctum::actingAs($student);
        $response = $this->getJson('/api/v1/student/dashboard');
        $response->assertOk();

        // Verify that the internship record was created and automatically assigned the faculty teacher
        $this->assertDatabaseHas('internships', [
            'student_id' => $student->id,
            'faculty_id' => $faculty->id,
        ]);
    }

    public function test_internship_model_hook_assigns_faculty_on_create_even_if_not_provided(): void
    {
        $faculty = $this->makeUser('faculty', 'FAC-002');
        FacultyProfile::updateOrCreate(
            ['user_id' => $faculty->id],
            [
                'faculty_number' => 'FAC-002',
                'first_name' => 'Prof',
                'last_name' => 'Two',
            ]
        );

        FacultySectionAssignment::create([
            'program' => 'BSIT',
            'section' => '4ITB',
            'school_year' => '2025-2026',
            'semester' => 1,
            'faculty_user_id' => $faculty->id,
            'is_active' => true,
        ]);

        $student = $this->makeUser('student', 'STUD-002');
        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => 'STUD-002',
            'first_name' => 'IT',
            'last_name' => 'Student',
            'program' => 'BSIT',
            'section' => '4ITB',
            'school_year' => '2025-2026',
            'semester' => 1,
        ]);

        // Create internship directly with null faculty_id (simulating seeder or sync)
        $internship = Internship::create([
            'student_id' => $student->id,
            'status' => 'pending_placement',
            'target_hours' => 500,
            'total_hours_rendered' => 0,
            'school_year' => '2025-2026',
            'semester' => 1,
            'term' => 'AY 2025-2026, Sem 1',
            'program' => 'BSIT',
        ]);

        // The model creating hook should have automatically assigned the faculty
        $this->assertEquals($faculty->id, $internship->fresh()->faculty_id);
    }

    public function test_student_profile_creation_immediately_initializes_unplaced_internship_record_assigned_to_teacher(): void
    {
        $faculty = $this->makeUser('faculty', 'FAC-003');
        FacultyProfile::updateOrCreate(
            ['user_id' => $faculty->id],
            [
                'faculty_number' => 'FAC-003',
                'first_name' => 'Prof',
                'last_name' => 'Three',
            ]
        );

        FacultySectionAssignment::create([
            'program' => 'BSIT',
            'section' => '4ITC',
            'school_year' => '2025-2026',
            'semester' => 2,
            'faculty_user_id' => $faculty->id,
            'is_active' => true,
        ]);

        // When a student account is created/synced (e.g. via MISD or import) without logging in or starting OJT
        $student = $this->makeUser('student', 'STUD-003');
        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => 'STUD-003',
            'first_name' => 'Unplaced',
            'last_name' => 'Student',
            'program' => 'BSIT',
            'section' => '4ITC',
            'school_year' => '2025-2026',
            'semester' => 2,
        ]);

        // Verify an internship record in status 'pending_placement' (unplaced) is immediately created and assigned to teacher
        $this->assertDatabaseHas('internships', [
            'student_id' => $student->id,
            'faculty_id' => $faculty->id,
            'status' => 'pending_placement',
        ]);
    }

    public function test_teacher_section_assignment_syncs_existing_unplaced_students(): void
    {
        // Enrolled student created first when no teacher is assigned yet
        $student = $this->makeUser('student', 'STUD-004');
        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => 'STUD-004',
            'first_name' => 'Waiting',
            'last_name' => 'Teacher',
            'program' => 'BSIT',
            'section' => '4ITD',
            'school_year' => '2025-2026',
            'semester' => 2,
        ]);

        // Verify student has an internship initialized in pending_placement with null faculty_id
        $this->assertDatabaseHas('internships', [
            'student_id' => $student->id,
            'faculty_id' => null,
            'status' => 'pending_placement',
        ]);

        // Now assign a teacher to section 4ITD
        $faculty = $this->makeUser('faculty', 'FAC-004');
        FacultyProfile::updateOrCreate(
            ['user_id' => $faculty->id],
            [
                'faculty_number' => 'FAC-004',
                'first_name' => 'Prof',
                'last_name' => 'Four',
            ]
        );

        FacultySectionAssignment::create([
            'program' => 'BSIT',
            'section' => '4ITD',
            'school_year' => '2025-2026',
            'semester' => 2,
            'faculty_user_id' => $faculty->id,
            'is_active' => true,
        ]);

        // Verify the existing student's internship was automatically synced and assigned to the new teacher
        $this->assertDatabaseHas('internships', [
            'student_id' => $student->id,
            'faculty_id' => $faculty->id,
            'status' => 'pending_placement',
        ]);
    }
}
