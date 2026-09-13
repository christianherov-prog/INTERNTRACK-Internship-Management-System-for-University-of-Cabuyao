<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\OjtRequirementTemplate;
use App\Models\RequirementTarget;
use App\Models\User;
use App\Services\DocumentComplianceService;
use App\Support\RequiredDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class DocumentComplianceAndRequirementsRepairTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        RequiredDocuments::clearCache();

        foreach (DocumentComplianceService::systemCodeCatalog() as $row) {
            OjtRequirementTemplate::updateOrCreate(
                ['system_code' => $row['code']],
                [
                    'name' => $row['name'],
                    'description' => $row['name'],
                    'category' => 'general',
                    'is_active' => true,
                    'is_system' => true,
                    'created_by' => null,
                    'sort_order' => 10,
                ]
            );
        }
        RequiredDocuments::clearCache();
    }

    public function test_approved_document_is_removed_from_missing_compliance(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $faculty);

        $before = app(DocumentComplianceService::class)->evaluateStudent($student->fresh('studentProfile'), $internship);
        $this->assertContains('Application Letter', $before['missing']);

        $doc = Document::create([
            'internship_id' => $internship->id,
            'document_type' => 'Application Letter',
            'status' => 'approved',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by' => $faculty->id,
        ]);

        $after = app(DocumentComplianceService::class)->evaluateStudent($student->fresh('studentProfile'), $internship);
        $this->assertNotContains('Application Letter', $after['missing']);
        $this->assertContains('Application Letter', $after['satisfied']);
        $this->assertSame($before['satisfied_count'] + 1, $after['satisfied_count']);
        $this->assertSame($after['satisfied_count'], $after['required_count'] - $after['missing_count'] - $after['pending_count']);
        $this->assertTrue($doc->exists());
    }

    public function test_rejected_then_approved_resubmission_satisfies_requirement(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $faculty);

        Document::create([
            'internship_id' => $internship->id,
            'document_type' => 'Curriculum Vitae',
            'status' => 'rejected',
            'submitted_at' => now()->subDay(),
            'reviewed_by' => $faculty->id,
        ]);
        Document::create([
            'internship_id' => $internship->id,
            'document_type' => 'Curriculum Vitae',
            'status' => 'approved',
            'submitted_at' => now(),
            'reviewed_by' => $faculty->id,
        ]);

        $result = app(DocumentComplianceService::class)->evaluateStudent($student->fresh('studentProfile'), $internship);
        $this->assertContains('Curriculum Vitae', $result['satisfied']);
        $this->assertNotContains('Curriculum Vitae', $result['missing']);
    }

    public function test_moa_alias_satisfies_memorandum_of_agreement(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $faculty);

        Document::create([
            'internship_id' => $internship->id,
            'document_type' => 'MOA',
            'status' => 'approved',
            'submitted_at' => now(),
            'reviewed_by' => $faculty->id,
        ]);

        $result = app(DocumentComplianceService::class)->evaluateStudent($student->fresh('studentProfile'), $internship);
        $this->assertContains('Memorandum of Agreement', $result['satisfied']);
        $this->assertNotContains('Memorandum of Agreement', $result['missing']);
    }

    public function test_faculty_can_edit_system_application_letter_without_targets(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $template = OjtRequirementTemplate::where('system_code', 'application_letter')->firstOrFail();

        Sanctum::actingAs($faculty);
        $this->post("/api/v1/faculty/requirements/{$template->id}", [
            'name' => 'Internship Application Letter',
            'description' => 'Updated description for letter',
            'deadline' => now()->addDays(14)->format('Y-m-d H:i:s'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('requirement.name', 'Internship Application Letter');

        $fresh = $template->fresh();
        $this->assertSame('application_letter', $fresh->system_code);
        $this->assertTrue((bool) $fresh->is_system);
        $this->assertSame('Updated description for letter', $fresh->description);
    }

    public function test_coordinator_list_hides_standard_requirements_but_keeps_customs(): void
    {
        $coordinator = $this->makeUser('coordinator');
        Sanctum::actingAs($coordinator);

        $custom = OjtRequirementTemplate::create([
            'name' => 'SAMPLE DOCUMENT',
            'description' => 'Custom',
            'category' => 'general',
            'is_active' => true,
            'is_system' => false,
            'created_by' => $coordinator->id,
            'sort_order' => 99,
        ]);
        RequirementTarget::create([
            'requirement_template_id' => $custom->id,
            'target_type' => 'section',
            'target_id' => '4ITD',
        ]);

        $list = $this->getJson('/api/v1/coordinator/requirements')->assertOk()->json('data');
        $names = collect($list)->pluck('name');
        $this->assertTrue($names->contains('SAMPLE DOCUMENT'));
        $this->assertFalse($names->contains('Application Letter'));
        $this->assertFalse(collect($list)->contains(fn ($r) => ! empty($r['is_system'])));
    }

    public function test_system_requirement_submissions_count_eligible_students_not_zero(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $this->makeActiveInternship($student, $company, $supervisor, $faculty, $faculty);

        Sanctum::actingAs($faculty);
        $list = $this->getJson('/api/v1/faculty/requirements')->assertOk()->json('data');
        $appLetter = collect($list)->firstWhere('system_code', 'application_letter')
            ?: collect($list)->firstWhere('name', 'Application Letter');

        $this->assertNotNull($appLetter);
        $this->assertGreaterThan(0, $appLetter['total_assigned'] ?? 0);
    }

    public function test_document_approval_writes_audit_and_admin_can_read_logs(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $faculty);

        $doc = Document::create([
            'internship_id' => $internship->id,
            'document_type' => 'Application Letter',
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($faculty);
        $this->postJson("/api/v1/faculty/documents/{$doc->id}/review", [
            'action' => 'approve',
            'remarks' => 'Looks good',
        ])->assertOk();

        $this->assertTrue(
            AuditLog::query()->where('action', 'document_approved')->where('user_id', $faculty->id)->exists()
        );

        $admin = $this->makeUser('admin');
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/audit-log?scope=all&action=document_approved')
            ->assertOk();

        $studentUser = $this->makeUser('student');
        Sanctum::actingAs($studentUser);
        $this->getJson('/api/v1/admin/audit-log')->assertStatus(403);
    }

    public function test_faculty_compliance_report_uses_satisfied_counts(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $faculty);

        Document::create([
            'internship_id' => $internship->id,
            'document_type' => 'Application Letter',
            'status' => 'approved',
            'submitted_at' => now(),
            'reviewed_by' => $faculty->id,
        ]);

        Sanctum::actingAs($faculty);
        $report = $this->getJson('/api/v1/faculty/reports/compliance')->assertOk();
        $row = collect($report->json('rows'))->first(fn ($r) => str_contains($r['student_name'], 'Student'));
        $this->assertNotNull($row);
        $this->assertGreaterThanOrEqual(1, $row['approved_docs']);
        $this->assertNotContains('Application Letter', $row['missing_docs']);
        $this->assertSame($row['approved_docs'] + count($row['missing_docs']) + ($row['pending_docs'] ?? 0) + ($row['rejected_count'] ?? 0), $row['required_docs']);
        $expectedPct = $row['required_docs'] > 0
            ? (int) round($row['approved_docs'] / $row['required_docs'] * 100)
            : 0;
        $this->assertSame($expectedPct, (int) $row['compliance_pct']);
        $this->assertIsArray($row['requirements'] ?? null);
        $this->assertCount($row['required_docs'], $row['requirements']);
        $approvedFromList = collect($row['requirements'])->where('status', 'approved')->count();
        $this->assertSame($row['approved_docs'], $approvedFromList);
        $appLetter = collect($row['requirements'])->firstWhere('name', 'Application Letter');
        $this->assertNotNull($appLetter);
        $this->assertSame('approved', $appLetter['status']);
        $this->assertSame('Approved', $appLetter['status_label']);
    }

    public function test_compliance_percentage_matches_approved_over_required_for_partial_progress(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $faculty);

        Document::create([
            'internship_id' => $internship->id,
            'document_type' => 'Application Letter',
            'status' => 'approved',
            'submitted_at' => now(),
            'reviewed_by' => $faculty->id,
        ]);
        Document::create([
            'internship_id' => $internship->id,
            'document_type' => 'Curriculum Vitae',
            'status' => 'approved',
            'submitted_at' => now(),
            'reviewed_by' => $faculty->id,
        ]);

        $result = app(DocumentComplianceService::class)->evaluateStudent($student->fresh('studentProfile'), $internship);
        $this->assertSame(2, $result['satisfied_count']);
        $this->assertGreaterThan(2, $result['required_count']);
        $this->assertSame(
            (int) round($result['satisfied_count'] / $result['required_count'] * 100),
            $result['compliance_pct']
        );
        $this->assertSame(
            $result['satisfied_count'] + $result['missing_count'] + $result['pending_count'] + $result['rejected_count'],
            $result['required_count']
        );
        $approvedDetails = collect($result['details'])->where('status', 'approved');
        $this->assertCount(2, $approvedDetails);
        $this->assertTrue($approvedDetails->every(fn ($d) => in_array($d['status_label'], ['Approved', 'Completed'], true)));
    }

    public function test_rejected_then_pending_then_approved_requirement_status_labels(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $faculty);

        Document::create([
            'internship_id' => $internship->id,
            'document_type' => 'Recommendation Letter',
            'status' => 'rejected',
            'submitted_at' => now()->subDays(2),
            'reviewed_by' => $faculty->id,
        ]);

        $svc = app(DocumentComplianceService::class);
        $rejected = $svc->evaluateStudent($student->fresh('studentProfile'), $internship);
        $rec = collect($rejected['details'])->firstWhere('name', 'Recommendation Letter');
        $this->assertSame('rejected', $rec['status']);
        $this->assertSame('Rejected', $rec['status_label']);

        Document::create([
            'internship_id' => $internship->id,
            'document_type' => 'Recommendation Letter',
            'status' => 'pending',
            'submitted_at' => now()->subDay(),
        ]);
        $pending = $svc->evaluateStudent($student->fresh('studentProfile'), $internship);
        $rec = collect($pending['details'])->firstWhere('name', 'Recommendation Letter');
        $this->assertSame('pending', $rec['status']);
        $this->assertSame('Pending Review', $rec['status_label']);

        Document::create([
            'internship_id' => $internship->id,
            'document_type' => 'Recommendation Letter',
            'status' => 'approved',
            'submitted_at' => now(),
            'reviewed_by' => $faculty->id,
        ]);
        $approved = $svc->evaluateStudent($student->fresh('studentProfile'), $internship);
        $rec = collect($approved['details'])->firstWhere('name', 'Recommendation Letter');
        $this->assertSame('approved', $rec['status']);
        $this->assertSame('Approved', $rec['status_label']);
        $this->assertNotContains('Recommendation Letter', $approved['rejected']);
        $this->assertNotContains('Recommendation Letter', $approved['missing']);
    }
}
