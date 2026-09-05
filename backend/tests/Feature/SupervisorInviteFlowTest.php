<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Internship;
use App\Models\SupervisorInviteToken;
use App\Models\SupervisorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class SupervisorInviteFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    private function makeSupervisorAccount(string $facultyNumber = 'SUP-9001'): User
    {
        $supervisor = $this->makeUser('supervisor', $facultyNumber);
        SupervisorProfile::create([
            'user_id' => $supervisor->id,
            'first_name' => 'Existing',
            'last_name' => 'Supervisor',
            'email' => $supervisor->email,
            'contact_number' => '09171234567',
            'sex' => 'Male',
            'position' => 'IT Manager',
        ]);

        return $supervisor->fresh('supervisorProfile');
    }

    private function studentReadyForInvite(): array
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = Internship::where('student_id', $student->id)->first()
            ?? $this->makePendingInternship($student);
        $internship->update([
            'company_id' => $company->id,
            'faculty_id' => $faculty->id,
            'supervisor_id' => null,
            'status' => 'active',
        ]);

        return compact('faculty', 'student', 'company', 'internship');
    }

    private function generateInviteToken(User $student): string
    {
        Sanctum::actingAs($student);
        $res = $this->postJson('/api/v1/student/supervisor-invite')->assertOk();

        return $res->json('token');
    }

    public function test_invite_validate_is_public_and_registration_rules_are_unchanged(): void
    {
        $party = $this->studentReadyForInvite();
        $token = $this->generateInviteToken($party['student']);

        $this->postJson('/api/v1/supervisor-register/validate', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('student_name', 'Student, Test');

        $this->postJson('/api/v1/supervisor-register', [
            'token' => $token,
            'email' => 'new.supervisor@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_id' => $party['company']->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'contact_number', 'position', 'sex', 'acceptance_forms']);
    }

    public function test_new_supervisor_registers_via_invite_and_faculty_approves(): void
    {
        Storage::fake('local');
        $party = $this->studentReadyForInvite();
        $token = $this->generateInviteToken($party['student']);
        $form = UploadedFile::fake()->create('acceptance.pdf', 120, 'application/pdf');

        $created = $this->post('/api/v1/supervisor-register', [
            'token' => $token,
            'first_name' => 'Maria',
            'last_name' => 'Reyes',
            'email' => 'maria.reyes@hte.example',
            'contact_number' => '09170001111',
            'position' => 'HR Supervisor',
            'sex' => 'Female',
            'company_id' => $party['company']->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'acceptance_forms' => [$form],
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame('SUP-0001', $created->json('username'));

        $invite = SupervisorInviteToken::where('token', $token)->first();
        $this->assertSame('registered', $invite->status);
        $this->assertNotEmpty($invite->acceptance_forms);
        $this->assertNull($party['internship']->fresh()->supervisor_id);
        Storage::disk('local')->assertExists($invite->acceptance_forms[0]['path']);

        $newUser = User::find($invite->supervisor_user_id);
        $this->assertFalse((bool) $newUser->is_active);
        $this->assertSame($party['company']->id, (int) $newUser->supervisorProfile->company_id);

        $this->postJson('/api/v1/auth/login', [
            'username' => $newUser->faculty_number,
            'password' => 'password123',
        ])->assertStatus(422);

        Sanctum::actingAs($party['faculty']);
        $pending = $this->getJson('/api/v1/faculty/supervisor-approvals')->assertOk();
        $this->assertNotEmpty($pending->json('pending.0.acceptance_forms.0.path'));

        $this->patchJson("/api/v1/faculty/supervisor-approvals/{$invite->id}/approve")
            ->assertOk();

        $this->assertTrue((bool) $newUser->fresh()->is_active);
        $this->assertSame($newUser->id, (int) $party['internship']->fresh()->supervisor_id);
        $this->assertSame('approved', $invite->fresh()->status);
    }

    public function test_existing_email_is_rejected_so_supervisor_must_sign_in(): void
    {
        Storage::fake('local');
        $party = $this->studentReadyForInvite();
        $existing = $this->makeSupervisorAccount('SUP-9002');
        $token = $this->generateInviteToken($party['student']);
        $form = UploadedFile::fake()->create('acceptance.pdf', 80, 'application/pdf');

        $this->post('/api/v1/supervisor-register', [
            'token' => $token,
            'first_name' => 'Dup',
            'last_name' => 'Account',
            'email' => $existing->email,
            'contact_number' => '09170002222',
            'position' => 'Lead',
            'sex' => 'Male',
            'company_id' => $party['company']->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'acceptance_forms' => [$form],
        ], ['Accept' => 'application/json'])->assertStatus(409)
            ->assertJsonPath('code', 'existing_account');

        $this->assertSame('pending', SupervisorInviteToken::where('token', $token)->value('status'));
        $this->assertNull($party['internship']->fresh()->supervisor_id);
    }

    public function test_existing_supervisor_login_binds_invite_without_auto_attaching(): void
    {
        $party = $this->studentReadyForInvite();
        $supervisor = $this->makeSupervisorAccount();
        $token = $this->generateInviteToken($party['student']);

        $this->postJson('/api/v1/auth/login', [
            'username' => $supervisor->faculty_number,
            'password' => 'password',
        ])->assertOk();

        Sanctum::actingAs($supervisor);
        $this->postJson('/api/v1/supervisor/invites/bind', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('invite.status', 'pending_accept');

        $this->assertNull($party['internship']->fresh()->supervisor_id);
        $this->assertSame('pending_accept', SupervisorInviteToken::where('token', $token)->value('status'));
        $this->assertSame($supervisor->id, (int) SupervisorInviteToken::where('token', $token)->value('supervisor_user_id'));

        Sanctum::actingAs($party['student']);
        $this->getJson('/api/v1/student/supervisor-invite/status')
            ->assertOk()
            ->assertJsonPath('state', 'awaiting_supervisor');
    }

    public function test_existing_supervisor_accepts_new_student_invite(): void
    {
        $party = $this->studentReadyForInvite();
        $supervisor = $this->makeSupervisorAccount();
        $token = $this->generateInviteToken($party['student']);

        Sanctum::actingAs($supervisor);
        $bind = $this->postJson('/api/v1/supervisor/invites/bind', ['token' => $token])->assertOk();
        $inviteId = $bind->json('invite.id');

        $pending = $this->getJson('/api/v1/supervisor/invites/pending')->assertOk();
        $this->assertCount(1, $pending->json('invites'));

        $this->postJson("/api/v1/supervisor/invites/{$inviteId}/accept")->assertOk();

        $this->assertSame($supervisor->id, (int) $party['internship']->fresh()->supervisor_id);
        $this->assertSame('approved', SupervisorInviteToken::find($inviteId)->status);
        $this->assertCount(0, $this->getJson('/api/v1/supervisor/invites/pending')->json('invites'));
    }

    public function test_existing_supervisor_declines_new_student_invite(): void
    {
        $party = $this->studentReadyForInvite();
        $supervisor = $this->makeSupervisorAccount();
        $token = $this->generateInviteToken($party['student']);

        Sanctum::actingAs($supervisor);
        $inviteId = $this->postJson('/api/v1/supervisor/invites/bind', ['token' => $token])->json('invite.id');
        $this->postJson("/api/v1/supervisor/invites/{$inviteId}/decline")->assertOk();

        $this->assertNull($party['internship']->fresh()->supervisor_id);
        $this->assertSame('declined', SupervisorInviteToken::find($inviteId)->status);

        Sanctum::actingAs($party['student']);
        $this->getJson('/api/v1/student/supervisor-invite/status')
            ->assertOk()
            ->assertJsonPath('state', 'declined');

        $this->postJson('/api/v1/student/supervisor-invite')->assertOk();
    }

    public function test_one_supervisor_account_can_supervise_multiple_interns(): void
    {
        $faculty = $this->makeUser('faculty');
        $coordinator = $this->makeUser('coordinator');
        $supervisor = $this->makeSupervisorAccount();
        $companyA = $this->makeEligibleCompany(['company_name' => 'Alpha HTE']);
        $companyB = $this->makeEligibleCompany(['company_name' => 'Beta HTE']);

        $studentA = $this->makeStudentWithSection('4ITA');
        $studentB = $this->makeStudentWithSection('4ITB');
        $internA = $this->makeActiveInternship($studentA, $companyA, $supervisor, $faculty, $coordinator);
        $internB = $this->makeActiveInternship($studentB, $companyB, $supervisor, $faculty, $coordinator);

        Sanctum::actingAs($supervisor);
        $dash = $this->getJson('/api/v1/supervisor/dashboard')->assertOk();
        $ids = collect($dash->json('assigned_interns'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$internA->id, $internB->id], $ids);

        $companies = collect($dash->json('assigned_interns'))->pluck('company')->all();
        $this->assertContains('Alpha HTE', $companies);
        $this->assertContains('Beta HTE', $companies);
    }

    public function test_ending_supervision_of_one_intern_leaves_others_intact(): void
    {
        $faculty = $this->makeUser('faculty');
        $coordinator = $this->makeUser('coordinator');
        $supervisor = $this->makeSupervisorAccount();
        $company = $this->makeEligibleCompany();
        $studentA = $this->makeStudentWithSection('4ITA');
        $studentB = $this->makeStudentWithSection('4ITB');
        $internA = $this->makeActiveInternship($studentA, $company, $supervisor, $faculty, $coordinator);
        $internB = $this->makeActiveInternship($studentB, $company, $supervisor, $faculty, $coordinator);

        Sanctum::actingAs($supervisor);
        $this->postJson("/api/v1/supervisor/internships/{$internA->id}/end-supervision", [
            'reason' => 'Placement ended',
        ])->assertOk();

        $this->assertNull($internA->fresh()->supervisor_id);
        $this->assertSame($supervisor->id, (int) $internB->fresh()->supervisor_id);
        $this->assertNotNull($supervisor->fresh());

        $dashIds = collect($this->getJson('/api/v1/supervisor/dashboard')->json('assigned_interns'))->pluck('id')->all();
        $this->assertSame([$internB->id], $dashIds);
    }

    public function test_supervisor_can_edit_own_profile_company_and_position(): void
    {
        $supervisor = $this->makeSupervisorAccount();
        $company = $this->makeEligibleCompany(['company_name' => 'New Host Co']);

        Sanctum::actingAs($supervisor);
        $this->putJson('/api/v1/auth/profile', [
            'name' => 'Updated Supervisor',
            'email' => 'updated.sup@hte.example',
            'contact' => '09179998888',
            'position' => 'Engineering Manager',
            'company_id' => $company->id,
            'sex' => 'Female',
        ])->assertOk()
            ->assertJsonPath('user.position', 'Engineering Manager')
            ->assertJsonPath('user.company', 'New Host Co')
            ->assertJsonPath('user.company_id', $company->id);

        $profile = $supervisor->supervisorProfile()->first();
        $this->assertSame('Engineering Manager', $profile->position);
        $this->assertSame($company->id, (int) $profile->company_id);
        $this->assertSame('Female', $profile->sex);
    }

    public function test_supervisor_rbac_hides_unlinked_interns_and_blocks_foreign_actions(): void
    {
        $faculty = $this->makeUser('faculty');
        $coordinator = $this->makeUser('coordinator');
        $owner = $this->makeSupervisorAccount('SUP-OWNER');
        $other = $this->makeSupervisorAccount('SUP-OTHER');
        $company = $this->makeEligibleCompany();
        $student = $this->makeStudentWithSection();
        $internship = $this->makeActiveInternship($student, $company, $owner, $faculty, $coordinator);

        Sanctum::actingAs($other);
        $dashIds = collect($this->getJson('/api/v1/supervisor/dashboard')->json('assigned_interns'))->pluck('id')->all();
        $this->assertNotContains($internship->id, $dashIds);

        $assigned = $this->getJson('/api/v1/supervisor/assigned-interns')->assertOk();
        $assignedIds = collect($assigned->json('data') ?? $assigned->json('items') ?? [])->pluck('id')->all();
        $this->assertNotContains($internship->id, $assignedIds);

        $this->postJson("/api/v1/supervisor/internships/{$internship->id}/end-supervision")
            ->assertNotFound();

        $this->postJson("/api/v1/supervisor/evaluations/{$internship->id}", [
            'evaluation_period' => 'midterm',
            'form_type' => 'FO-24',
            'responses' => ['q1' => 5],
        ])->assertNotFound();

        $this->postJson("/api/v1/supervisor/feedback/{$internship->id}", [
            'feedback' => 'Should not be allowed.',
        ])->assertNotFound();

        $party = $this->studentReadyForInvite();
        $token = $this->generateInviteToken($party['student']);
        Sanctum::actingAs($owner);
        $inviteId = $this->postJson('/api/v1/supervisor/invites/bind', ['token' => $token])->json('invite.id');

        Sanctum::actingAs($other);
        $this->postJson("/api/v1/supervisor/invites/{$inviteId}/accept")->assertForbidden();
        $this->postJson("/api/v1/supervisor/invites/{$inviteId}/decline")->assertForbidden();
    }

    public function test_faculty_and_students_cannot_self_serve_supervisor_profile(): void
    {
        $faculty = $this->makeUser('faculty');
        Sanctum::actingAs($faculty);
        $this->putJson('/api/v1/auth/profile', [
            'name' => 'Should Fail',
            'position' => 'Hacker',
        ])->assertForbidden();
    }
}
