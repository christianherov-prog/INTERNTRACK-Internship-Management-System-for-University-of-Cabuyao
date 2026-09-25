<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\HteRequest;
use App\Models\InternshipApplication;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class PlacementApprovalWorkflowTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    public function test_student_can_apply_with_moa_and_coordinator_approves(): void
    {
        Storage::fake('local');
        [$student, $coordinator, $company] = $this->ccsParty();
        $moa = UploadedFile::fake()->create('moa.pdf', 120, 'application/pdf');

        Sanctum::actingAs($student);
        $this->post('/api/v1/student/applications', [
            'company_id' => $company->id,
            'moa' => $moa,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('application.status', 'pending')
            ->assertJsonPath('application.has_moa', true)
            ->assertJsonPath('application.company_name', 'TechCorp PH');

        $application = InternshipApplication::first();
        $this->assertNotNull($application->moa_path);
        Storage::disk('local')->assertExists($application->moa_path);
        $this->assertMatchesRegularExpression('/[0-9a-f-]{36}\.pdf$/i', basename($application->moa_path));
        $this->assertSame(1, Notification::where('type', 'placement_application')->count());

        Sanctum::actingAs($coordinator);
        $list = $this->getJson('/api/v1/coordinator/applications')->assertOk();
        $this->assertSame($application->id, $list->json('applications.0.id'));
        $this->assertSame('pending', $list->json('applications.0.status'));

        $this->getJson('/api/v1/files/download?path='.urlencode($application->moa_path))->assertOk();

        $this->patchJson("/api/v1/coordinator/applications/{$application->id}/status", [
            'status' => 'approved',
        ])->assertOk()->assertJsonPath('application.status', 'approved');

        $this->assertSame('approved', $application->fresh()->status);
        $this->assertSame($company->id, (int) $student->internshipsAsStudent()->first()->fresh()->company_id);
        $this->assertTrue(Notification::where('user_id', $student->id)->where('type', 'placement_application_approved')->exists());

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/student/applications')
            ->assertOk()
            ->assertJsonPath('applications.0.status', 'approved');
    }

    public function test_coordinator_rejects_application_with_remarks(): void
    {
        Storage::fake('local');
        [$student, $coordinator, $company] = $this->ccsParty();

        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/applications', ['company_id' => $company->id])->assertOk();
        $application = InternshipApplication::first();

        Sanctum::actingAs($coordinator);
        $this->patchJson("/api/v1/coordinator/applications/{$application->id}/status", [
            'status' => 'rejected',
        ])->assertStatus(422);

        $this->patchJson("/api/v1/coordinator/applications/{$application->id}/status", [
            'status' => 'rejected',
            'coordinator_remarks' => 'Incomplete MOA packet.',
        ])->assertOk()->assertJsonPath('application.status', 'rejected');

        $this->assertSame('Incomplete MOA packet.', $application->fresh()->coordinator_remarks);
        $this->assertNull($student->internshipsAsStudent()->first()->fresh()->company_id);

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/student/applications')
            ->assertOk()
            ->assertJsonPath('applications.0.status', 'rejected')
            ->assertJsonPath('applications.0.coordinator_remarks', 'Incomplete MOA packet.');
    }

    public function test_duplicate_approve_and_reject_are_rejected(): void
    {
        [$student, $coordinator, $company] = $this->ccsParty();
        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/applications', ['company_id' => $company->id])->assertOk();
        $id = InternshipApplication::value('id');

        Sanctum::actingAs($coordinator);
        $this->patchJson("/api/v1/coordinator/applications/{$id}/status", ['status' => 'approved'])->assertOk();
        $this->patchJson("/api/v1/coordinator/applications/{$id}/status", ['status' => 'approved'])->assertStatus(422);
        $this->patchJson("/api/v1/coordinator/applications/{$id}/status", [
            'status' => 'rejected',
            'coordinator_remarks' => 'too late',
        ])->assertStatus(422);
        $this->assertSame('approved', InternshipApplication::find($id)->status);
    }

    public function test_hte_request_with_moa_can_be_approved_without_duplicate_company(): void
    {
        Storage::fake('local');
        [$student, $coordinator] = $this->ccsParty();

        Sanctum::actingAs($student);
        $this->post('/api/v1/student/hte-requests', [
            'company_name' => 'Acme Technologies Inc.',
            'address' => 'BGC',
            'contact_person' => 'HR',
            'contact_email' => 'hr@acme.test',
            'contact_number' => '09170001111',
            'remarks' => 'Want to intern here',
            'moa' => UploadedFile::fake()->create('hte-moa.pdf', 80, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('request.has_moa', true);

        $req = HteRequest::first();
        Storage::disk('local')->assertExists($req->moa_path);
        $before = Company::count();

        Sanctum::actingAs($coordinator);
        $this->getJson('/api/v1/files/download?path='.urlencode($req->moa_path))->assertOk();
        $this->patchJson("/api/v1/coordinator/hte-requests/{$req->id}/status", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('request.status', 'approved');

        $this->assertSame($before + 1, Company::count());
        $this->assertTrue(Company::where('company_name', 'Acme Technologies Inc.')->where('moa_status', 'on-process')->exists());
        $this->assertTrue(Notification::where('user_id', $student->id)->where('type', 'hte_request_approved')->exists());
    }

    public function test_approving_hte_does_not_duplicate_existing_company(): void
    {
        [$student] = $this->ccsParty();
        $existing = $this->makeEligibleCompany(['company_name' => 'Acme Technologies Inc.']);

        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/hte-requests', [
            'company_name' => 'Acme Technologies Inc.',
            'address' => 'BGC',
            'contact_person' => 'HR',
            'contact_email' => 'hr@acme.test',
            'contact_number' => '09170001111',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This HTE already exists in the accredited company list.');

        $this->assertSame(0, HteRequest::count());
        $this->assertSame(1, Company::whereRaw('LOWER(company_name) = ?', ['acme technologies inc.'])->count());
        $this->assertTrue(Company::whereKey($existing->id)->exists());
    }

    public function test_hte_request_creates_company_when_missing_and_reject_keeps_history(): void
    {
        [$student, $coordinator] = $this->ccsParty();

        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/hte-requests', [
            'company_name' => 'Brand New HTE',
            'address' => 'Cabuyao',
            'contact_person' => 'Ana',
            'contact_email' => 'ana@new.test',
            'contact_number' => '09171112222',
            'remarks' => 'Near campus',
        ])->assertOk();

        $req = HteRequest::first();
        Sanctum::actingAs($coordinator);
        $this->patchJson("/api/v1/coordinator/hte-requests/{$req->id}/status", ['status' => 'rejected'])
            ->assertStatus(422);

        $this->patchJson("/api/v1/coordinator/hte-requests/{$req->id}/status", [
            'status' => 'rejected',
            'coordinator_remarks' => 'No MOA counterpart yet.',
        ])->assertOk();

        $this->assertSame('rejected', $req->fresh()->status);
        $this->assertSame('No MOA counterpart yet.', $req->fresh()->coordinator_remarks);
        $this->assertSame('Near campus', $req->fresh()->remarks);
        $this->assertFalse(Company::where('company_name', 'Brand New HTE')->exists());
        $this->assertSame(1, HteRequest::count());

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/student/hte-requests')
            ->assertOk()
            ->assertJsonPath('requests.0.status', 'rejected')
            ->assertJsonPath('requests.0.coordinator_remarks', 'No MOA counterpart yet.');
    }

    public function test_invalid_moa_and_unauthorized_access_are_blocked(): void
    {
        Storage::fake('local');
        [$student, $coordinator, $company] = $this->ccsParty();
        $other = $this->makeStudentWithSection('4ITA');
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);

        Sanctum::actingAs($student);
        $this->post('/api/v1/student/applications', [
            'company_id' => $company->id,
            'moa' => UploadedFile::fake()->create('virus.exe', 20, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $ok = UploadedFile::fake()->create('moa.pdf', 40, 'application/pdf');
        $this->post('/api/v1/student/applications', [
            'company_id' => $company->id,
            'moa' => $ok,
        ], ['Accept' => 'application/json'])->assertOk();
        $path = InternshipApplication::value('moa_path');

        Sanctum::actingAs($other);
        $this->getJson('/api/v1/files/download?path='.urlencode($path))->assertForbidden();

        Sanctum::actingAs($faculty);
        $this->patchJson('/api/v1/coordinator/applications/'.InternshipApplication::value('id').'/status', [
            'status' => 'approved',
        ])->assertForbidden();
        $this->getJson('/api/v1/files/download?path='.urlencode($path))->assertForbidden();

        Sanctum::actingAs($coordinator);
        $this->getJson('/api/v1/files/download?path='.urlencode($path))->assertOk();
    }

    public function test_student_can_attach_moa_to_an_existing_pending_application(): void
    {
        Storage::fake('local');
        [$student, $coordinator, $company] = $this->ccsParty();

        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/applications', ['company_id' => $company->id])->assertOk();
        $this->assertFalse((bool) InternshipApplication::value('moa_path'));

        $this->post('/api/v1/student/applications', [
            'company_id' => $company->id,
            'moa' => UploadedFile::fake()->create('late-moa.pdf', 60, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('application.has_moa', true);

        $path = InternshipApplication::value('moa_path');
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        Sanctum::actingAs($coordinator);
        $this->getJson('/api/v1/files/download?path='.urlencode($path))->assertOk();
        $this->assertSame(1, InternshipApplication::count());
    }

    public function test_pending_hte_cannot_be_submitted_twice_for_same_company(): void
    {
        [$student] = $this->ccsParty();
        $payload = [
            'company_name' => 'Twice HTE',
            'address' => 'A',
            'contact_person' => 'B',
            'contact_email' => 'b@test.com',
            'contact_number' => '09170000000',
        ];
        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/hte-requests', $payload)->assertOk();
        $this->postJson('/api/v1/student/hte-requests', $payload)->assertStatus(422);
        $this->assertSame(1, HteRequest::count());
    }

    public function test_duplicate_pending_send_without_moa_does_not_create_second_row(): void
    {
        [$student, , $company] = $this->ccsParty();
        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/applications', ['company_id' => $company->id])->assertOk();
        $this->postJson('/api/v1/student/applications', ['company_id' => $company->id])->assertOk();
        $this->assertSame(1, InternshipApplication::count());
        $this->assertSame('pending', InternshipApplication::value('status'));
    }

    /**
     * @return array{0: User, 1: User, 2: Company}
     */
    private function ccsParty(): array
    {
        $coordinator = $this->makeUser('coordinator', 'COR-CCS-APP');
        $student = $this->makeStudentWithSection();
        $this->assignFixtureAdviser($student); // required before applying / requesting an HTE
        $company = $this->makeEligibleCompany(['company_name' => 'TechCorp PH']);

        return [$student, $coordinator, $company];
    }
}
