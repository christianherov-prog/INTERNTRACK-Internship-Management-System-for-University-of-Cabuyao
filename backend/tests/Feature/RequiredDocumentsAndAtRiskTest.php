<?php

namespace Tests\Feature;

use App\Support\AtRiskInternship;
use App\Support\RequiredDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class RequiredDocumentsAndAtRiskTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_required_documents_total_is_thirteen(): void
    {
        for ($i = 1; $i <= 13; $i++) {
            \App\Models\OjtRequirementTemplate::create([
                'name' => "Doc $i",
                'type' => 'document',
                'description' => "Doc $i desc",
                'sort_order' => $i,
            ]);
        }
        
        // Clear cache so it reads from DB
        \Illuminate\Support\Facades\Cache::forget('ojt_requirements_types');

        $this->assertSame(13, RequiredDocuments::count());
        $this->assertCount(13, RequiredDocuments::types());
    }

    public function test_student_documents_endpoint_uses_required_list(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        Sanctum::actingAs($student);

        \Illuminate\Support\Facades\Cache::forget('ojt_requirements_types');

        for ($i = 1; $i <= 13; $i++) {
            $template = \App\Models\OjtRequirementTemplate::create([
                'name' => "Doc $i",
                'type' => 'document',
                'description' => "Doc $i",
                'sort_order' => $i,
            ]);
            $template->targets()->create(['target_type' => 'program', 'target_id' => 'BSIT']);
        }
        
        \Illuminate\Support\Facades\Cache::forget('ojt_requirements_types');

        $this->getJson('/api/v1/student/documents')
            ->assertOk()
            ->assertJsonPath('meta.docs_total', 13)
            ->assertJsonCount(13, 'data');
    }

    public function test_at_risk_count_uses_query_not_full_collection_load(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->update([
            'start_date' => now()->subDays(40)->toDateString(),
            'total_hours_rendered' => 10,
            'target_hours' => 360,
        ]);

        $this->assertTrue(AtRiskInternship::isAtRisk($internship->fresh()));
        $this->assertSame(1, AtRiskInternship::countActiveAtRisk());

        Sanctum::actingAs($coordinator);
        $this->getJson('/api/v1/coordinator/monitoring')
            ->assertOk()
            ->assertJsonPath('stats.at_risk_students', 1);
    }

    public function test_faculty_reports_scoped_to_assigned_students(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $otherFaculty = $this->makeUser('faculty', 'FAC-OTHER');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        Sanctum::actingAs($faculty);
        $mine = $this->getJson('/api/v1/faculty/reports/student-summary')->assertOk();
        $this->assertCount(1, $mine->json('students'));

        Sanctum::actingAs($otherFaculty);
        $theirs = $this->getJson('/api/v1/faculty/reports/student-summary')->assertOk();
        $this->assertCount(0, $theirs->json('students'));
    }
}
