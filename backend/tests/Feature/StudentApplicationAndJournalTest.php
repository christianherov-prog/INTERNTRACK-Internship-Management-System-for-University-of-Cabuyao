<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class StudentApplicationAndJournalTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_company_application_persists_company_name(): void
    {
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany(['company_name' => 'TechCorp PH']);
        Sanctum::actingAs($student);

        $this->postJson('/api/v1/student/applications', [
            'company_id' => $company->id,
        ])->assertOk()
            ->assertJsonPath('application.company_name', 'TechCorp PH');

        $list = $this->getJson('/api/v1/student/applications')->assertOk();
        $this->assertSame('TechCorp PH', $list->json('applications.0.company_name'));
        $this->assertSame('TechCorp PH', $list->json('applications.0.company.company_name'));
    }

    public function test_submitted_journal_can_be_edited_but_approved_cannot(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $coordinator = $this->makeUser('coordinator');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        JournalEntry::create([
            'internship_id' => $internship->id,
            'week_number' => 1,
            'entry_number' => 1,
            'date' => '2026-08-31',
            'end_date' => '2026-09-04',
            'activities_summary' => 'Original work',
            'status' => 'submitted',
        ]);

        Sanctum::actingAs($student);

        $this->postJson('/api/v1/student/logbook', [
            'week_number' => 1,
            'date' => '2026-08-31',
            'end_date' => '2026-09-04',
            'activities_summary' => 'Corrected work',
        ])->assertCreated()
            ->assertJsonPath('journal.activities_summary', 'Corrected work');

        $list = $this->getJson('/api/v1/student/logbook')->assertOk();
        $this->assertSame('2026-08-31', $list->json('data.0.date'));
        $this->assertTrue($list->json('data.0.editable'));

        JournalEntry::where('internship_id', $internship->id)->update(['status' => 'approved']);

        $this->postJson('/api/v1/student/logbook', [
            'week_number' => 1,
            'date' => '2026-08-31',
            'end_date' => '2026-09-04',
            'activities_summary' => 'Should not save',
        ])->assertStatus(422);

        $locked = $this->getJson('/api/v1/student/logbook')->assertOk();
        $this->assertFalse($locked->json('data.0.editable'));
    }

    public function test_pending_placement_is_not_treated_as_placed_on_dashboard(): void
    {
        $student = $this->makeStudentWithSection();
        Sanctum::actingAs($student);

        $dashboard = $this->getJson('/api/v1/student/dashboard')->assertOk();
        $this->assertSame('pending_placement', $dashboard->json('internship.status'));
        $this->assertSame('Pending Placement', $dashboard->json('internship.status_label'));
        $this->assertSame('pending_placement', $dashboard->json('stats.status'));
    }
}
