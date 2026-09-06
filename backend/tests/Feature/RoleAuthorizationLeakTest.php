<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class RoleAuthorizationLeakTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_coordinator_cannot_access_faculty_assigned_students(): void
    {
        $coordinator = $this->makeUser('coordinator');
        Sanctum::actingAs($coordinator);

        $this->getJson('/api/v1/faculty/assigned-students')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden. You do not have permission to access this resource.');
    }

    public function test_student_cannot_access_faculty_or_coordinator_endpoints(): void
    {
        $student = $this->makeStudentWithSection();
        Sanctum::actingAs($student);

        $this->getJson('/api/v1/faculty/assigned-students')->assertForbidden();
        $this->getJson('/api/v1/coordinator/monitoring')->assertForbidden();
        $this->getJson('/api/v1/supervisor/assigned-interns')->assertForbidden();
        $this->getJson('/api/v1/admin/coordinators')->assertForbidden();
    }

    public function test_supervisor_cannot_access_faculty_endpoints(): void
    {
        $supervisor = $this->makeUser('supervisor');
        Sanctum::actingAs($supervisor);

        $this->getJson('/api/v1/faculty/assigned-students')->assertForbidden();
        $this->getJson('/api/v1/coordinator/records')->assertForbidden();
    }

    public function test_faculty_can_still_access_faculty_assigned_students(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        Sanctum::actingAs($faculty);

        $this->getJson('/api/v1/faculty/assigned-students')->assertOk();
    }
}
