<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Services\SupervisorFeedbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class JournalWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_student_submission_goes_to_faculty_and_supervisor_cannot_review(): void
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

        $journalId = JournalEntry::where('internship_id', $internship->id)->academic()->value('id');
        $this->assertNotNull($journalId);
        $this->assertSame('submitted', JournalEntry::find($journalId)->status);

        Sanctum::actingAs($otherSupervisor);
        $this->getJson('/api/v1/supervisor/journals')->assertNotFound();
        $this->patchJson('/api/v1/supervisor/journals/'.$journalId.'/review', [
            'action' => 'approved',
        ])->assertNotFound();

        Sanctum::actingAs($supervisor);
        $this->getJson('/api/v1/supervisor/journals')->assertNotFound();
        $this->patchJson('/api/v1/supervisor/journals/'.$journalId.'/review', [
            'action' => 'approved',
            'feedback' => 'Should not be allowed',
        ])->assertNotFound();

        Sanctum::actingAs($faculty);
        $facultyList = $this->getJson('/api/v1/faculty/journals')->assertOk();
        $this->assertSame($journalId, $facultyList->json('data.0.id'));
        $this->assertNotEmpty($facultyList->json('data.0.student_name'));
        $this->assertTrue($facultyList->json('data.0.faculty_can_review'));
        $this->assertFalse($facultyList->json('data.0.awaiting_supervisor'));
        $this->patchJson('/api/v1/faculty/journals/'.$journalId.'/review', [
            'action' => 'approved',
            'score' => 92,
            'feedback' => 'Well written',
        ])->assertOk();

        Sanctum::actingAs($student);
        $studentList = $this->getJson('/api/v1/student/logbook')->assertOk();
        $this->assertSame('approved', $studentList->json('data.0.status'));
        $this->assertSame('Well written', $studentList->json('data.0.faculty_feedback'));
        $this->assertNull($studentList->json('intern_feedback'));
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

    public function test_supervisor_note_is_excluded_from_faculty_journal_review(): void
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
            'week_number' => 0,
            'entry_number' => 0,
            'date' => now()->toDateString(),
            'status' => SupervisorFeedbackService::NOTE_STATUS,
            'supervisor_feedback' => 'Intern-level note',
            'supervisor_reviewed_by' => $supervisor->id,
            'supervisor_reviewed_at' => now(),
        ]);

        Sanctum::actingAs($faculty);
        $list = $this->getJson('/api/v1/faculty/journals')->assertOk();
        $this->assertSame([], $list->json('data'));
    }
}
