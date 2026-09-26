<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Services\SupervisorFeedbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class FacultyJournalsPageRepairTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    public function test_faculty_journals_imports_interntrack_loader_and_keeps_shell_on_refresh(): void
    {
        $jsx = file_get_contents(base_path('../frontend/src/pages/faculty/FacultyJournals.jsx'));
        $this->assertIsString($jsx);
        $this->assertStringContainsString("import InternTrackLoader from '../../components/InternTrackLoader'", $jsx);
        $this->assertStringContainsString('loading && journals.length === 0', $jsx);
        $this->assertStringContainsString('loadFacultyFo31Preview', $jsx);
        $this->assertStringContainsString('openReview', $jsx);
        $this->assertStringNotContainsString('scoreRequired', $jsx);
        $this->assertStringNotContainsString('defaultScore', $jsx);
        $this->assertStringNotContainsString('Review Journal — Week', $jsx);
        $this->assertStringNotContainsString('onPreview={() => handlePreview(modal)}', $jsx);
        $this->assertStringContainsString('formatFo31DateRange', $jsx);
        $this->assertStringContainsString('formatManilaDateTime', $jsx);
        $this->assertStringContainsString('PageError', $jsx);
        $this->assertStringNotContainsString('waiting_for_supervisor', $jsx);
        $this->assertDoesNotMatchRegularExpression('/\{loading\s*\?\s*</', $jsx);
    }

    public function test_interntrack_loader_usages_have_matching_imports(): void
    {
        $root = realpath(base_path('../frontend/src'));
        $this->assertNotFalse($root);

        $broken = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! preg_match('/\.(jsx?|tsx?)$/', $file->getFilename())) {
                continue;
            }
            if ($file->getFilename() === 'InternTrackLoader.jsx') {
                continue;
            }

            $src = file_get_contents($file->getPathname());
            if ($src === false || ! preg_match('/<\s*InternTrackLoader\b/', $src)) {
                continue;
            }

            $hasDefault = (bool) preg_match(
                '/import\s+InternTrackLoader\s+from\s+[\'"][^\'"]*InternTrackLoader[\'"]/',
                $src
            );
            if (! $hasDefault) {
                $broken[] = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $broken, 'InternTrackLoader used without a default import: '.implode(', ', $broken));
    }

    public function test_error_boundary_hides_stack_traces_outside_development(): void
    {
        $src = file_get_contents(base_path('../frontend/src/components/ErrorBoundary.jsx'));
        $this->assertStringContainsString('console.error', $src);
        $this->assertStringContainsString('Something went wrong', $src);
        $this->assertStringContainsString('import.meta.env.DEV && this.state.error', $src);
    }

    public function test_supervisor_journal_validation_remains_absent_from_nav_and_api(): void
    {
        $sidebar = file_get_contents(base_path('../frontend/src/components/Sidebar.jsx'));
        $app = file_get_contents(base_path('../frontend/src/App.jsx'));

        $this->assertStringNotContainsString('Journal Validation', $sidebar);
        $this->assertStringNotContainsString("to: '/supervisor/journals'", $sidebar);
        // The current workspace exposes journal review within Assigned Students.
        $this->assertStringContainsString("to: '/faculty/assigned-students'", $sidebar);
        $assigned = file_get_contents(base_path('../frontend/src/pages/faculty/FacultyAssignedStudents.jsx'));
        $this->assertStringContainsString('Journal Review Queue', $assigned);
        $this->assertStringContainsString('<Navigate to="/supervisor/assigned-interns" replace />', $app);

        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $coordinator = $this->makeUser('coordinator');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        $journal = JournalEntry::create([
            'internship_id' => $internship->id,
            'week_number' => 1,
            'entry_number' => 1,
            'date' => '2026-08-24',
            'end_date' => '2026-08-28',
            'activities_summary' => 'Week 1 work',
            'status' => 'submitted',
        ]);

        Sanctum::actingAs($supervisor);
        $this->getJson('/api/v1/supervisor/journals')->assertNotFound();
        $this->patchJson('/api/v1/supervisor/journals/'.$journal->id.'/review', [
            'action' => 'approved',
        ])->assertNotFound();
    }

    public function test_assigned_faculty_sees_authorized_journal_and_review_persists(): void
    {
        $faculty = $this->makeUser('faculty', 'FAC-1001');
        $this->mapFacultyForSection($faculty);
        $otherFaculty = $this->makeUser('faculty', 'FAC-UNRELATED');
        $this->ensureStaffDepartment($otherFaculty, 'CCS');
        $student = $this->makeStudentWithSection();
        $student->studentProfile->update([
            'first_name' => 'Clarence',
            'last_name' => 'Montealegre',
            'student_number' => '2300592',
        ]);
        $student->update(['student_number' => '2300592']);
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $coordinator = $this->makeUser('coordinator');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        // Make the asserted Week 1 independent of the day this test is executed.
        $internship->update(['start_date' => '2026-08-24']);

        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/logbook', [
            'week_number' => 1,
            'date' => '2026-08-24',
            'end_date' => '2026-08-28',
            'activities_summary' => 'Configured intern workstation',
            'challenges' => 'Limited access on day one',
            'learnings' => 'Followed onboarding checklist',
        ])->assertCreated();

        $journalId = JournalEntry::where('internship_id', $internship->id)->academic()->value('id');
        $this->assertNotNull($journalId);
        $this->assertSame('submitted', JournalEntry::find($journalId)->status);

        Sanctum::actingAs($otherFaculty);
        $foreignList = collect($this->getJson('/api/v1/faculty/journals')->assertOk()->json('data'));
        $this->assertFalse($foreignList->contains(fn ($row) => (int) ($row['id'] ?? 0) === (int) $journalId));
        $this->patchJson('/api/v1/faculty/journals/'.$journalId.'/review', [
            'action' => 'approved',
            'score' => 90,
        ])->assertNotFound();

        Sanctum::actingAs($faculty);
        $list = $this->getJson('/api/v1/faculty/journals')->assertOk();
        $row = collect($list->json('data'))->firstWhere('id', $journalId);
        $this->assertNotNull($row);
        $this->assertStringContainsString('Montealegre', (string) ($row['student_display_name'] ?? ''));
        $this->assertSame('2300592', $row['student_number'] ?? null);
        $this->assertSame('Configured intern workstation', $row['activities_summary'] ?? null);
        $this->assertSame('Limited access on day one', $row['challenges'] ?? null);
        $this->assertSame('Followed onboarding checklist', $row['learnings'] ?? null);
        $this->assertTrue($row['faculty_can_review']);
        $this->assertFalse($row['awaiting_supervisor']);

        $this->patchJson('/api/v1/faculty/journals/'.$journalId.'/review', [
            'action' => 'approved',
            'score' => 91,
            'feedback' => 'Approved after faculty review',
        ])->assertOk();

        $this->assertSame('approved', JournalEntry::find($journalId)->status);
        $this->assertSame('Approved after faculty review', JournalEntry::find($journalId)->faculty_feedback);
        $this->assertSame($faculty->id, (int) JournalEntry::find($journalId)->faculty_reviewed_by);

        $reloaded = collect($this->getJson('/api/v1/faculty/journals')->assertOk()->json('data'))
            ->firstWhere('id', $journalId);
        $this->assertSame('approved', $reloaded['status'] ?? null);

        $history = $this->getJson('/api/v1/faculty/students/'.$student->id.'/journals')->assertOk()->json();
        $this->assertTrue(collect($history)->contains(fn ($item) => (int) ($item['id'] ?? 0) === (int) $journalId));

        Sanctum::actingAs($student);
        $studentList = $this->getJson('/api/v1/student/logbook')->assertOk();
        $studentRow = collect($studentList->json('data'))->firstWhere('id', $journalId)
            ?? collect($studentList->json('data'))->first();
        $this->assertSame('approved', $studentRow['status'] ?? null);
        $this->assertSame('Approved after faculty review', $studentRow['faculty_feedback'] ?? null);

        $portfolio = $this->getJson('/api/v1/student/portfolio')->assertOk()->json();
        $fo31 = collect($portfolio['internship']['journals'] ?? [])->firstWhere('id', $journalId)
            ?? ($portfolio['internship']['journals'][0] ?? null);
        $this->assertNotNull($fo31);
        $this->assertSame(1, (int) $fo31['week_number']);
        $this->assertSame('2026-08-24', $fo31['date']);
        $this->assertSame('2026-08-28', $fo31['end_date']);
        $this->assertSame('Configured intern workstation', $fo31['activities_summary']);
        $this->assertSame('Limited access on day one', $fo31['challenges']);
        $this->assertSame('Followed onboarding checklist', $fo31['learnings']);
        $this->assertSame('approved', $fo31['status'] ?? JournalEntry::find($journalId)->status);
    }

    public function test_student_journal_history_excludes_supervisor_notes(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $coordinator = $this->makeUser('coordinator');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        $academic = JournalEntry::create([
            'internship_id' => $internship->id,
            'week_number' => 1,
            'entry_number' => 1,
            'date' => '2026-08-24',
            'end_date' => '2026-08-28',
            'activities_summary' => 'Academic week 1',
            'status' => 'submitted',
        ]);
        JournalEntry::create([
            'internship_id' => $internship->id,
            'week_number' => 0,
            'entry_number' => 0,
            'date' => '2026-08-24',
            'status' => SupervisorFeedbackService::NOTE_STATUS,
            'supervisor_feedback' => 'Intern-level note',
            'supervisor_reviewed_by' => $supervisor->id,
            'supervisor_reviewed_at' => now(),
        ]);

        Sanctum::actingAs($faculty);
        $history = collect($this->getJson('/api/v1/faculty/students/'.$student->id.'/journals')->assertOk()->json());
        $this->assertTrue($history->contains(fn ($row) => (int) ($row['id'] ?? 0) === (int) $academic->id));
        $this->assertFalse($history->contains(fn ($row) => ($row['status'] ?? '') === SupervisorFeedbackService::NOTE_STATUS));
    }
}
