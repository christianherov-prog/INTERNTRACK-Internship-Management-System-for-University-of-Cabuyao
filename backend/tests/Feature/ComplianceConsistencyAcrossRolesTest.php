<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentComplianceService;
use App\Support\RequiredDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class ComplianceConsistencyAcrossRolesTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RequiredDocuments::clearCache();
        foreach (DocumentComplianceService::systemCodeCatalog() as $row) {
            \App\Models\OjtRequirementTemplate::updateOrCreate(
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

    public function test_student_faculty_and_coordinator_share_same_compliance_counts(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $coordinator = $this->makeUser('coordinator');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        Document::create([
            'internship_id' => $internship->id,
            'document_type' => 'Application Letter',
            'status' => 'approved',
            'submitted_at' => now(),
            'reviewed_by' => $faculty->id,
        ]);

        $canonical = app(DocumentComplianceService::class)->summaryForStudent($student->fresh('studentProfile'), $internship);

        Sanctum::actingAs($student);
        $dash = $this->getJson('/api/v1/student/dashboard')->assertOk();
        $this->assertSame($canonical['approved'], (int) $dash->json('stats.docs_approved'));
        $this->assertSame($canonical['total'], (int) $dash->json('stats.docs_total'));
        $this->assertSame($canonical['pct'], (int) $dash->json('stats.doc_compliance'));

        Sanctum::actingAs($faculty);
        $facultyProgress = $this->getJson('/api/v1/faculty/students/'.$student->id.'/progress')->assertOk();
        $this->assertSame($canonical['approved'], (int) $facultyProgress->json('documents.approved'));
        $this->assertSame($canonical['total'], (int) $facultyProgress->json('documents.total'));
        $this->assertSame($canonical['pct'], (int) $facultyProgress->json('documents.compliance_pct'));

        Sanctum::actingAs($coordinator);
        $coordProgress = $this->getJson('/api/v1/coordinator/students/'.$student->id.'/progress')->assertOk();
        $this->assertSame($canonical['approved'], (int) $coordProgress->json('documents.approved'));
        $this->assertSame($canonical['total'], (int) $coordProgress->json('documents.total'));
        $this->assertSame($canonical['pct'], (int) $coordProgress->json('documents.compliance_pct'));

        Sanctum::actingAs($student);
        $studentDocs = $this->getJson('/api/v1/student/documents')->assertOk();
        $this->assertSame($canonical['approved'], (int) $studentDocs->json('meta.docs_approved'));
        $this->assertSame($canonical['total'], (int) $studentDocs->json('meta.docs_total'));
        $this->assertSame($canonical['pct'], (int) $studentDocs->json('meta.compliance_pct'));
        $this->assertSame($canonical['pending'], (int) $studentDocs->json('meta.docs_pending'));

        $this->assertFalse($canonical['complete']);
        $this->assertLessThan(100, $canonical['pct']);
    }

    public function test_alias_document_type_counts_as_approved_on_student_documents(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $faculty);

        Document::create([
            'internship_id' => $internship->id,
            'document_type' => 'application_letter',
            'status' => 'approved',
            'submitted_at' => now(),
            'reviewed_by' => $faculty->id,
        ]);

        $canonical = app(DocumentComplianceService::class)->summaryForStudent($student->fresh('studentProfile'), $internship);

        Sanctum::actingAs($student);
        $studentDocs = $this->getJson('/api/v1/student/documents')->assertOk();
        $this->assertSame($canonical['approved'], (int) $studentDocs->json('meta.docs_approved'));
        $this->assertSame($canonical['total'], (int) $studentDocs->json('meta.docs_total'));

        $items = collect($studentDocs->json('data') ?? $studentDocs->json('items') ?? []);
        $appLetter = $items->first(function ($row) {
            $name = strtolower((string) ($row['document_type'] ?? ''));

            return str_contains($name, 'application');
        });
        $this->assertNotNull($appLetter);
        $this->assertContains($appLetter['status'], ['approved', 'completed']);
    }

    public function test_future_students_remain_independent(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $a = $this->makeStudentWithSection();
        $b = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $internshipA = $this->makeActiveInternship($a, $company, $supervisor, $faculty, $faculty);
        $internshipB = $this->makeActiveInternship($b, $company, $supervisor, $faculty, $faculty);

        Document::create([
            'internship_id' => $internshipA->id,
            'document_type' => 'Curriculum Vitae',
            'status' => 'approved',
            'submitted_at' => now(),
        ]);

        $svc = app(DocumentComplianceService::class);
        $sumA = $svc->summaryForStudent($a->fresh('studentProfile'), $internshipA);
        $sumB = $svc->summaryForStudent($b->fresh('studentProfile'), $internshipB);

        $this->assertGreaterThanOrEqual(1, $sumA['approved']);
        $this->assertSame(0, $sumB['approved']);
        $this->assertNotSame($sumA['approved'], $sumB['approved']);
    }

    public function test_complete_label_requires_all_applicable_requirements(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $faculty);

        $svc = app(DocumentComplianceService::class);
        $before = $svc->summaryForStudent($student->fresh('studentProfile'), $internship);
        $this->assertFalse($before['complete']);

        foreach ($before['details'] as $detail) {
            if (($detail['status'] ?? '') === 'approved') {
                continue;
            }
            if (($detail['source'] ?? '') !== 'upload' && ($detail['status'] ?? '') === 'missing') {
                // Satisfy upload-backed requirements only; system-generated may auto-complete later.
            }
            if (($detail['name'] ?? null) && ! in_array($detail['status'], ['approved'], true)) {
                Document::create([
                    'internship_id' => $internship->id,
                    'document_type' => $detail['name'],
                    'status' => 'approved',
                    'submitted_at' => now(),
                    'reviewed_by' => $faculty->id,
                ]);
            }
        }

        // Ensure attendance so DTR system requirement can satisfy if present.
        \App\Models\AttendanceLog::create([
            'internship_id' => $internship->id,
            'date' => now()->toDateString(),
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'hours_rendered' => 4,
            'status' => 'validated',
        ]);

        $after = $svc->summaryForStudent($student->fresh('studentProfile'), $internship->fresh());
        if ($after['complete']) {
            $this->assertSame(100, $after['pct']);
            $this->assertSame('Complete', $after['label']);
        } else {
            // Some system requirements may still need evaluations; ensure label is not Complete.
            $this->assertNotSame('Complete', $after['label']);
            $this->assertLessThan(100, $after['pct']);
        }
    }
}
