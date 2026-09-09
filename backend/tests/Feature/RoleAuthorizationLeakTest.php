<?php

namespace Tests\Feature;

use App\Models\SupervisorInviteToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class RoleAuthorizationLeakTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    public function test_coordinator_can_access_faculty_assigned_students(): void
    {
        $coordinator = $this->makeUser('coordinator');
        Sanctum::actingAs($coordinator);

        $this->getJson('/api/v1/faculty/assigned-students')->assertOk();
    }

    public function test_coordinator_faculty_workspace_is_advisee_scoped(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $otherFaculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $company = $this->makeEligibleCompany();

        // Advisee: coordinator is the internship's faculty supervisor.
        $advisee = $this->makeStudentWithSection('4ITA');
        $this->makeActiveInternship($advisee, $company, $supervisor, $coordinator, $coordinator);

        // Department mate advised by another faculty — must NOT appear.
        $otherStudent = $this->makeStudentWithSection('4ITB');
        $this->makeActiveInternship($otherStudent, $company, $supervisor, $otherFaculty, $coordinator);

        Sanctum::actingAs($coordinator);
        $userIds = collect($this->getJson('/api/v1/faculty/assigned-students')->assertOk()->json('data'))
            ->pluck('user_id');

        $this->assertTrue($userIds->contains($advisee->id));
        $this->assertFalse($userIds->contains($otherStudent->id));
    }

    public function test_coordinator_can_review_supervisor_invites_in_department(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $otherFaculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $pendingSupervisor = $this->makeUser('supervisor');
        $pendingSupervisor->update(['is_active' => false]);
        $company = $this->makeEligibleCompany();

        $advisee = $this->makeStudentWithSection('4ITA');
        $adviseeInternship = $this->makeActiveInternship($advisee, $company, $supervisor, $coordinator, $coordinator);
        $adviseeInternship->update(['supervisor_id' => null]);

        $otherStudent = $this->makeStudentWithSection('4ITB');
        $otherInternship = $this->makeActiveInternship($otherStudent, $company, $supervisor, $otherFaculty, $coordinator);
        $otherInternship->update(['supervisor_id' => null]);

        $ownInvite = SupervisorInviteToken::create([
            'internship_id' => $adviseeInternship->id,
            'student_id' => $advisee->id,
            'token' => 'tok-own-advisee',
            'expires_at' => now()->addDay(),
            'status' => 'registered',
            'supervisor_user_id' => $pendingSupervisor->id,
            'first_name' => 'Own',
            'last_name' => 'Advisee',
            'company_id' => $company->id,
        ]);

        $foreignInvite = SupervisorInviteToken::create([
            'internship_id' => $otherInternship->id,
            'student_id' => $otherStudent->id,
            'token' => 'tok-other-advisee',
            'expires_at' => now()->addDay(),
            'status' => 'registered',
            'supervisor_user_id' => $pendingSupervisor->id,
            'first_name' => 'Other',
            'last_name' => 'Advisee',
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($coordinator);

        $pendingIds = collect($this->getJson('/api/v1/faculty/supervisor-approvals')->assertOk()->json('pending'))
            ->pluck('id');
        $this->assertTrue($pendingIds->contains($ownInvite->id));
        $this->assertTrue($pendingIds->contains($foreignInvite->id));

        $this->patchJson("/api/v1/faculty/supervisor-approvals/{$foreignInvite->id}/approve")
            ->assertOk();
        $this->assertSame('approved', $foreignInvite->fresh()->status);
        $this->assertSame($pendingSupervisor->id, (int) $otherInternship->fresh()->supervisor_id);
    }

    public function test_coordinator_cannot_review_supervisor_invite_from_other_department(): void
    {
        $ccsCoord = $this->makeUser('coordinator', 'COR-CCS-SUP');
        $coeFaculty = $this->makeUser('faculty', 'FAC-COE-SUP');
        $this->ensureStaffDepartment($coeFaculty, 'COE');
        $pendingSupervisor = $this->makeUser('supervisor');
        $pendingSupervisor->update(['is_active' => false]);
        $company = $this->makeEligibleCompany();

        $coeStudent = $this->makeStudentInCollege(
            'COE',
            'Bachelor of Science in Civil Engineering',
            'BSCE',
            '4BSCE-A'
        );
        $internship = $this->makePendingInternship($coeStudent);
        $internship->update([
            'company_id' => $company->id,
            'faculty_id' => $coeFaculty->id,
            'supervisor_id' => null,
            'status' => 'active',
        ]);

        $invite = SupervisorInviteToken::create([
            'internship_id' => $internship->id,
            'student_id' => $coeStudent->id,
            'token' => 'tok-coe-invite',
            'expires_at' => now()->addDay(),
            'status' => 'registered',
            'supervisor_user_id' => $pendingSupervisor->id,
            'first_name' => 'Coe',
            'last_name' => 'Supervisor',
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($ccsCoord);
        $pendingIds = collect($this->getJson('/api/v1/faculty/supervisor-approvals')->assertOk()->json('pending'))
            ->pluck('id');
        $this->assertFalse($pendingIds->contains($invite->id));

        $this->patchJson("/api/v1/faculty/supervisor-approvals/{$invite->id}/approve")
            ->assertForbidden();
    }

    public function test_faculty_cannot_review_another_faculty_advisee_invite(): void
    {
        $faculty = $this->makeUser('faculty');
        $otherFaculty = $this->makeUser('faculty');
        $coordinator = $this->makeUser('coordinator');
        $supervisor = $this->makeUser('supervisor');
        $pendingSupervisor = $this->makeUser('supervisor');
        $pendingSupervisor->update(['is_active' => false]);
        $company = $this->makeEligibleCompany();

        $ownStudent = $this->makeStudentWithSection('4ITA');
        $ownInternship = $this->makeActiveInternship($ownStudent, $company, $supervisor, $faculty, $coordinator);

        $otherStudent = $this->makeStudentWithSection('4ITB');
        $otherInternship = $this->makeActiveInternship($otherStudent, $company, $supervisor, $otherFaculty, $coordinator);

        $ownInvite = SupervisorInviteToken::create([
            'internship_id' => $ownInternship->id,
            'student_id' => $ownStudent->id,
            'token' => 'tok-fac-own',
            'expires_at' => now()->addDay(),
            'status' => 'registered',
            'supervisor_user_id' => $pendingSupervisor->id,
            'first_name' => 'Own',
            'last_name' => 'Advisee',
            'company_id' => $company->id,
        ]);

        $foreignInvite = SupervisorInviteToken::create([
            'internship_id' => $otherInternship->id,
            'student_id' => $otherStudent->id,
            'token' => 'tok-fac-other',
            'expires_at' => now()->addDay(),
            'status' => 'registered',
            'supervisor_user_id' => $pendingSupervisor->id,
            'first_name' => 'Other',
            'last_name' => 'Advisee',
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($faculty);

        $pendingIds = collect($this->getJson('/api/v1/faculty/supervisor-approvals')->assertOk()->json('pending'))
            ->pluck('id');
        $this->assertTrue($pendingIds->contains($ownInvite->id));
        $this->assertFalse($pendingIds->contains($foreignInvite->id));

        $this->patchJson("/api/v1/faculty/supervisor-approvals/{$foreignInvite->id}/approve")
            ->assertForbidden();
    }

    public function test_faculty_cannot_access_coordinator_endpoints(): void
    {
        $faculty = $this->makeUser('faculty');
        Sanctum::actingAs($faculty);

        $this->getJson('/api/v1/coordinator/monitoring')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden. You do not have permission to access this resource.');
        $this->getJson('/api/v1/coordinator/placement-options')->assertForbidden();
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
