<?php

namespace Tests\Feature;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class UserResourceDepartmentCoordinatorTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    public function test_student_department_is_a_structured_object(): void
    {
        $psych = $this->makeStudentInCollege('CAS', 'Bachelor of Science in Psychology', 'BSPSY', '4PSY-A');
        $nursing = $this->makeStudentInCollege('CHAS', 'Bachelor of Science in Nursing', 'BSN', '4BSN-A');
        $it = $this->makeStudentWithSection();

        Sanctum::actingAs($psych);
        $this->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('user.department.code', 'CAS')
            ->assertJsonPath('user.department.name', 'College of Arts and Sciences');

        Sanctum::actingAs($nursing);
        $this->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('user.department.code', 'CHAS');

        Sanctum::actingAs($it);
        $this->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('user.department.code', 'CCS');
    }

    public function test_faculty_and_coordinator_department_objects(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->ensureStaffDepartment($faculty, 'CCS');
        $coordinator = $this->makeUser('coordinator');
        $this->ensureStaffDepartment($coordinator, 'CHAS');

        Sanctum::actingAs($faculty->fresh());
        $this->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('user.department.code', 'CCS');

        Sanctum::actingAs($coordinator->fresh());
        $this->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('user.department.code', 'CHAS');
    }

    public function test_missing_department_is_null(): void
    {
        $supervisor = $this->makeUser('supervisor');

        Sanctum::actingAs($supervisor);
        $this->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('user.department', null);
    }

    public function test_coordinator_name_uses_internship_relationship_only(): void
    {
        $first = $this->makeUser('coordinator', 'COR-FIRST');
        $this->ensureStaffDepartment($first, 'CCS');
        $first->facultyProfile->update(['first_name' => 'Alpha', 'last_name' => 'Coordinator']);

        $assigned = $this->makeUser('coordinator', 'COR-ASSIGNED');
        $this->ensureStaffDepartment($assigned, 'CAS');
        $assigned->facultyProfile->update(['first_name' => 'Beta', 'last_name' => 'Coordinator']);

        $student = $this->makeStudentInCollege('CAS', 'Bachelor of Science in Psychology', 'BSPSY', '4PSY-A');
        $internship = $student->internshipsAsStudent()->first();
        $internship->update(['coordinator_id' => $assigned->id]);

        $payload = $this->userResourcePayload($student->fresh());
        $this->assertSame('Beta Coordinator', $payload['coordinator']);
    }

    public function test_missing_coordinator_does_not_fall_back_to_first_coordinator(): void
    {
        $unrelated = $this->makeUser('coordinator', 'COR-UNRELATED');
        $this->ensureStaffDepartment($unrelated, 'CCS');
        $unrelated->facultyProfile->update(['first_name' => 'Wrong', 'last_name' => 'Person']);

        $student = $this->makeStudentWithSection();
        $internship = $student->internshipsAsStudent()->first();
        $internship->update(['coordinator_id' => null]);

        $payload = $this->userResourcePayload($student->fresh());
        $this->assertSame('N/A', $payload['coordinator']);
        $this->assertStringNotContainsString('Wrong', $payload['coordinator']);
    }

    private function userResourcePayload(User $user): array
    {
        $user->load(AuthService::USER_RELATIONS);

        return (new UserResource($user))->toArray(Request::create('/api/v1/auth/user'));
    }
}
