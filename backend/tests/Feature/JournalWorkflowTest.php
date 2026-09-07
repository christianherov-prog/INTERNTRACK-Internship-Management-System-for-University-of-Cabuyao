<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class JournalWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_supervisor_can_view_and_validate_assigned_journal_then_faculty_reviews(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $otherSupervisor = $this->makeUser('supervisor');
        $coordinator = $this->makeUser('coordinator');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/logbook', [
            'week_number' => 2,
            'date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'activities_summary' => 'Installed network drops',
        ])->assertCreated();

        $journalId = JournalEntry::where('internship_id', $internship->id)->value('id');

        Sanctum::actingAs($otherSupervisor);
        $this->getJson('/api/v1/supervisor/journals')->assertOk()->assertJsonPath('data', []);
        $this->patchJson('/api/v1/supervisor/journals/'.$journalId.'/review', [
            'action' => 'approved',
        ])->assertForbidden();

        Sanctum::actingAs($faculty);
        $this->patchJson('/api/v1/faculty/journals/'.$journalId.'/review', [
            'action' => 'approved',
            'score' => 90,
        ])->assertStatus(422);

        Sanctum::actingAs($supervisor);
        $list = $this->getJson('/api/v1/supervisor/journals')->assertOk();
        $this->assertSame($journalId, $list->json('data.0.id'));
        $this->patchJson('/api/v1/supervisor/journals/'.$journalId.'/review', [
            'action' => 'approved',
            'feedback' => 'Accurate weekly report',
        ])->assertOk();

        $this->assertNotNull(JournalEntry::find($journalId)->supervisor_reviewed_at);
        $this->assertSame('submitted', JournalEntry::find($journalId)->status);

        Sanctum::actingAs($faculty);
        $facultyList = $this->getJson('/api/v1/faculty/journals')->assertOk();
        $this->assertTrue($facultyList->json('data.0.supervisor_validated'));
        $this->assertTrue($facultyList->json('data.0.faculty_can_review'));
        $this->patchJson('/api/v1/faculty/journals/'.$journalId.'/review', [
            'action' => 'approved',
            'score' => 92,
            'feedback' => 'Well written',
        ])->assertOk();

        Sanctum::actingAs($student);
        $studentList = $this->getJson('/api/v1/student/logbook')->assertOk();
        $this->assertSame('approved', $studentList->json('data.0.status'));
        $this->assertSame('Accurate weekly report', $studentList->json('data.0.supervisor_feedback'));
        $this->assertSame('Well written', $studentList->json('data.0.faculty_feedback'));
    }

    public function test_faculty_can_review_when_internship_has_no_supervisor(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $coordinator = $this->makeUser('coordinator');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->update(['supervisor_id' => null]);

        $journal = JournalEntry::create([
            'internship_id' => $internship->id,
            'week_number' => 1,
            'entry_number' => 1,
            'date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'activities_summary' => 'Legacy weekly entry',
            'status' => 'submitted',
        ]);

        Sanctum::actingAs($faculty);
        $this->patchJson('/api/v1/faculty/journals/'.$journal->id.'/review', [
            'action' => 'approved',
            'score' => 85,
        ])->assertOk();
    }
}
