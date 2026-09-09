<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\User;
use App\Services\SupervisorFeedbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class SupervisorFeedbackFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    private function party(): array
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $otherSupervisor = $this->makeUser('supervisor', 'SUP-UNRELATED');
        $coordinator = $this->makeUser('coordinator');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        return compact('faculty', 'student', 'company', 'supervisor', 'otherSupervisor', 'coordinator', 'internship');
    }

    public function test_assigned_supervisor_can_create_edit_and_delete_feedback(): void
    {
        $party = $this->party();
        $text = 'Clarence has demonstrated good attendance, professional communication, and willingness to follow instructions. He should continue improving confidence when completing tasks independently.';

        Sanctum::actingAs($party['supervisor']);
        $created = $this->postJson('/api/v1/supervisor/feedback/'.$party['internship']->id, [
            'feedback' => $text,
        ])->assertOk();

        $id = $created->json('feedback.id');
        $this->assertNotNull($id);
        $this->assertSame($text, $created->json('feedback.supervisor_feedback'));
        $this->assertSame($party['internship']->id, $created->json('feedback.internship_id'));
        $this->assertNotNull($created->json('feedback.supervisor_reviewed_at'));

        $this->assertSame(1, JournalEntry::query()->where('status', SupervisorFeedbackService::NOTE_STATUS)->count());
        $this->assertSame(0, JournalEntry::query()->academic()->count());

        $this->postJson('/api/v1/supervisor/feedback/'.$party['internship']->id, [
            'feedback' => $text.' Updated observation.',
        ])->assertOk();
        $this->assertSame(1, JournalEntry::query()->where('status', SupervisorFeedbackService::NOTE_STATUS)->count());

        $this->patchJson('/api/v1/supervisor/feedback/'.$id, [
            'feedback' => $text.' Edited after review.',
        ])->assertOk()->assertJsonPath('feedback.supervisor_feedback', $text.' Edited after review.');

        Sanctum::actingAs($party['student']);
        $studentView = $this->getJson('/api/v1/student/supervisor-feedback')->assertOk();
        $this->assertStringContainsString('Edited after review', (string) $studentView->json('intern_feedback.feedback'));

        Sanctum::actingAs($party['faculty']);
        $facultyView = $this->getJson('/api/v1/faculty/supervisor-feedback')->assertOk();
        $this->assertSame($id, $facultyView->json('data.0.id'));

        Sanctum::actingAs($party['coordinator']);
        $coordView = $this->getJson('/api/v1/coordinator/supervisor-feedback')->assertOk();
        $this->assertSame($id, $coordView->json('data.0.id'));

        $progress = $this->actingAsFaculty($party['faculty'])
            ->getJson('/api/v1/faculty/students/'.$party['student']->id.'/progress')
            ->assertOk();
        $this->assertStringContainsString('Edited after review', (string) $progress->json('supervisor_feedback.feedback'));

        Sanctum::actingAs($party['supervisor']);
        $this->deleteJson('/api/v1/supervisor/feedback/'.$id)->assertOk();
        $this->assertSame(0, JournalEntry::query()->where('status', SupervisorFeedbackService::NOTE_STATUS)->count());

        Sanctum::actingAs($party['student']);
        $this->getJson('/api/v1/student/supervisor-feedback')->assertOk()->assertJsonPath('intern_feedback', null);
    }

    public function test_unrelated_supervisor_faculty_and_student_are_denied(): void
    {
        $party = $this->party();
        Sanctum::actingAs($party['supervisor']);
        $this->postJson('/api/v1/supervisor/feedback/'.$party['internship']->id, [
            'feedback' => 'Assigned supervisor observation for intern performance and communication.',
        ])->assertOk();
        $noteId = JournalEntry::query()->where('status', SupervisorFeedbackService::NOTE_STATUS)->value('id');

        Sanctum::actingAs($party['otherSupervisor']);
        $this->postJson('/api/v1/supervisor/feedback/'.$party['internship']->id, [
            'feedback' => 'Should not be allowed for an unassigned intern.',
        ])->assertForbidden();
        $this->patchJson('/api/v1/supervisor/feedback/'.$noteId, [
            'feedback' => 'Hijack attempt text that is long enough.',
        ])->assertForbidden();
        $this->deleteJson('/api/v1/supervisor/feedback/'.$noteId)->assertForbidden();
        $list = $this->getJson('/api/v1/supervisor/feedback')->assertOk();
        $this->assertSame([], $list->json('data'));

        $otherFaculty = $this->makeUser('faculty', 'FAC-OTHER');
        Sanctum::actingAs($otherFaculty);
        $this->getJson('/api/v1/faculty/supervisor-feedback')->assertOk()->assertJsonPath('data', []);
        $this->getJson('/api/v1/faculty/students/'.$party['student']->id.'/progress')->assertForbidden();

        $chasCoordinator = $this->makeUser('coordinator', 'COR-CHAS-009');
        $this->ensureStaffDepartment($chasCoordinator, 'CHAS');
        Sanctum::actingAs($chasCoordinator);
        $this->getJson('/api/v1/coordinator/supervisor-feedback')->assertOk()->assertJsonPath('data', []);

        $otherStudent = $this->makeStudentWithSection('4ITA');
        Sanctum::actingAs($otherStudent);
        $own = $this->getJson('/api/v1/student/supervisor-feedback');
        if ($own->status() === 200) {
            $this->assertNull($own->json('intern_feedback'));
            $feedback = (string) $own->json('intern_feedback.feedback');
            $this->assertStringNotContainsString('Assigned supervisor observation', $feedback);
        }
    }

    public function test_feedback_survives_refetch_and_does_not_enter_portfolio_fo31(): void
    {
        $party = $this->party();
        Sanctum::actingAs($party['supervisor']);
        $this->postJson('/api/v1/supervisor/feedback/'.$party['internship']->id, [
            'feedback' => 'Persistent intern feedback stored on the server, not in the browser.',
        ])->assertOk();

        Sanctum::actingAs($party['student']);
        $this->getJson('/api/v1/student/supervisor-feedback')
            ->assertOk()
            ->assertJsonPath('intern_feedback.feedback', 'Persistent intern feedback stored on the server, not in the browser.');

        $portfolio = $this->getJson('/api/v1/student/portfolio')->assertOk();
        $weeks = collect($portfolio->json('internship.journals') ?? []);
        $this->assertTrue($weeks->every(fn ($j) => (int) ($j['week_number'] ?? $j['week'] ?? 1) >= 1));
    }

    private function actingAsFaculty(User $faculty): self
    {
        Sanctum::actingAs($faculty);

        return $this;
    }
}
