<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\JournalDeadline;
use App\Models\JournalEntry;
use App\Models\SupervisorProfile;
use App\Models\User;
use App\Services\JournalPeriodValidator;
use App\Support\EvaluationPeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

/**
 * Faculty workflow fixes: authoritative evaluation-period state, journal-week
 * based deadlines, and the DTR preview / FO-24 UI fixes. All accounts are
 * created fresh, so the behavior holds for future Faculty and Students.
 */
class FacultyWorkflowFixesTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function party(string $section = '4ITD', string $start = '2026-06-01', ?string $end = '2026-09-30', ?User $faculty = null): array
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty ??= $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        SupervisorProfile::create([
            'user_id' => $supervisor->id, 'first_name' => 'Industry', 'last_name' => 'Mentor',
            'position' => 'Lead', 'email' => $supervisor->email,
        ]);
        $student = $this->makeStudentWithSection($section);
        $company = $this->makeEligibleCompany(['company_name' => 'HTE '.$student->id]);
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->update(['start_date' => $start, 'end_date' => $end]);

        return compact('coordinator', 'faculty', 'supervisor', 'student', 'company', 'internship');
    }

    // ── Evaluation period (EVAL-PERIOD-01..05) ───────────────────────────────

    public function test_eval_period_01_to_04_banner_follows_faculty_approval(): void
    {
        $p = $this->party();

        // EVAL-PERIOD-01: pending → waiting banner state; forms locked.
        Sanctum::actingAs($p['student']);
        $period = $this->getJson('/api/v1/student/evaluations')->assertOk()->json('evaluation_period');
        $this->assertSame(EvaluationPeriod::PENDING, $period['status']);
        $this->assertFalse($period['approved']);
        $this->assertStringContainsString('Waiting for Faculty approval', $period['message']);
        $this->postJson('/api/v1/student/evaluations', [
            'evaluation_period' => 'final', 'form_type' => 'FO-23', 'responses' => ['q1' => 5],
        ])->assertForbidden();

        // EVAL-PERIOD-02: assigned faculty approves → authoritative status approved.
        Sanctum::actingAs($p['faculty']);
        $this->postJson('/api/v1/faculty/evaluations/'.$p['internship']->id.'/approve-period')
            ->assertOk()->assertJsonPath('evaluation_period.status', EvaluationPeriod::APPROVED);
        $this->assertSame('approved', $p['internship']->fresh()->evaluation_period_status);
        $row = collect($this->getJson('/api/v1/faculty/evaluations')->assertOk()->json('internships'))->firstWhere('id', $p['internship']->id);
        $this->assertSame(EvaluationPeriod::APPROVED, $row['evaluation_period']['status']);

        // EVAL-PERIOD-03: student re-fetch → no waiting banner, no new login needed.
        Sanctum::actingAs($p['student']);
        $period = $this->getJson('/api/v1/student/evaluations')->assertOk()->json('evaluation_period');
        $this->assertSame(EvaluationPeriod::APPROVED, $period['status']);
        $this->assertNull($period['message']);

        // EVAL-PERIOD-04: eligible forms unlock.
        $this->postJson('/api/v1/student/evaluations', [
            'evaluation_period' => 'final', 'form_type' => 'FO-23', 'responses' => ['q1' => 5],
        ])->assertCreated();
        Sanctum::actingAs($p['supervisor']);
        $this->postJson('/api/v1/supervisor/evaluations/'.$p['internship']->id, [
            'evaluation_period' => 'final', 'form_type' => 'FO-03', 'responses' => ['crit_0' => 5],
        ])->assertCreated();

        // A legitimate reset is reflected again from the same source.
        EvaluationPeriod::setApproved($p['internship']->fresh(), false, $p['faculty']);
        Sanctum::actingAs($p['student']);
        $this->getJson('/api/v1/student/evaluations')->assertOk()->assertJsonPath('evaluation_period.status', EvaluationPeriod::PENDING);
    }

    public function test_eval_period_05_unassigned_faculty_and_other_roles_cannot_approve(): void
    {
        $p = $this->party();

        Sanctum::actingAs($this->makeUser('faculty'));
        $this->postJson('/api/v1/faculty/evaluations/'.$p['internship']->id.'/approve-period')->assertForbidden();
        Sanctum::actingAs($p['coordinator']);
        $this->postJson('/api/v1/faculty/evaluations/'.$p['internship']->id.'/approve-period')->assertForbidden();
        Sanctum::actingAs($p['supervisor']);
        $this->postJson('/api/v1/faculty/evaluations/'.$p['internship']->id.'/approve-period')->assertForbidden();
        Sanctum::actingAs($p['student']);
        $this->postJson('/api/v1/faculty/evaluations/'.$p['internship']->id.'/approve-period')->assertForbidden();

        $this->assertSame('pending', $p['internship']->fresh()->evaluation_period_status ?: 'pending');
    }

    public function test_stale_duplicate_internship_cannot_hide_the_approval_from_the_student(): void
    {
        $p = $this->party();
        // Legacy duplicate (older, superseded) row for the same student and faculty.
        $staleId = DB::table('internships')->insertGetId([
            'student_id' => $p['student']->id, 'faculty_id' => $p['faculty']->id, 'status' => 'cancelled',
            'program' => 'BSIT', 'term' => 'old', 'school_year' => '2024-2025', 'semester' => '2',
            'target_hours' => 360, 'total_hours_rendered' => 0, 'created_at' => now()->subYear(), 'updated_at' => now()->subYear(),
        ]);

        Sanctum::actingAs($p['faculty']);
        $ids = collect($this->getJson('/api/v1/faculty/evaluations')->json('internships'))->where('student_id', $p['student']->id)->pluck('id')->all();
        $this->assertSame([$p['internship']->id], $ids, 'Faculty sees exactly the internship the student reads.');

        $this->postJson("/api/v1/faculty/evaluations/{$staleId}/approve-period")
            ->assertStatus(409)->assertJsonPath('current_internship_id', $p['internship']->id);

        $this->postJson('/api/v1/faculty/evaluations/'.$p['internship']->id.'/approve-period')->assertOk();
        Sanctum::actingAs($p['student']);
        $this->getJson('/api/v1/student/evaluations')->assertJsonPath('evaluation_period.status', EvaluationPeriod::APPROVED);
    }

    public function test_pending_placement_is_not_yet_eligible_not_waiting(): void
    {
        $student = $this->makeStudentWithSection('4ITB');
        $this->makePendingInternship($student);
        Sanctum::actingAs($student);

        $period = $this->getJson('/api/v1/student/evaluations')->assertOk()->json('evaluation_period');
        $this->assertSame(EvaluationPeriod::NOT_YET_ELIGIBLE, $period['status']);
    }

    // ── Journal deadlines (JDL-01..10) ───────────────────────────────────────

    public function test_jdl_01_02_weeks_come_from_the_authoritative_journal_rule(): void
    {
        $p = $this->party('4ITD', '2026-06-01', '2026-06-30');
        Carbon::setTestNow(Carbon::parse('2026-06-20 02:00:00', 'UTC'));

        // A student journal submitted for Jun 8–12 is week 2 by the journal rule.
        Sanctum::actingAs($p['student']);
        $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-06-08', 'end_date' => '2026-06-12', 'activities_summary' => 'Week two',
        ])->assertCreated();
        $journal = JournalEntry::where('internship_id', $p['internship']->id)->firstOrFail();
        $this->assertSame(2, (int) $journal->week_number);

        Sanctum::actingAs($p['faculty']);
        $data = $this->getJson('/api/v1/faculty/journal-weeks?internship_ids[]='.$p['internship']->id)->assertOk()->json('data.0');
        $weeks = collect($data['weeks']);

        // 30-day internship → weeks 1..5; the last week is clipped to the end date.
        $this->assertSame([1, 2, 3, 4, 5], $weeks->pluck('week_number')->all());
        $this->assertSame(['2026-06-01', '2026-06-07'], [$weeks[0]['start_date'], $weeks[0]['end_date']]);
        $this->assertSame(['2026-06-29', '2026-06-30'], [$weeks[4]['start_date'], $weeks[4]['end_date']]);

        // Same week identity as the journal and JournalPeriodValidator.
        $this->assertSame($journal->id, $weeks->firstWhere('week_number', 2)['journal']['id']);
        $validator = app(JournalPeriodValidator::class);
        foreach ($weeks as $week) {
            $this->assertSame($week['week_number'], $validator->weekNumberFor('2026-06-01', $week['start_date']));
            $this->assertSame($week['week_number'], $validator->weekNumberFor('2026-06-01', $week['end_date']));
        }
    }

    public function test_jdl_03_invalid_week_numbers_are_rejected(): void
    {
        $p = $this->party('4ITD', '2026-06-01', '2026-06-30');
        Sanctum::actingAs($p['faculty']);

        foreach ([0, -1, 6, 999] as $week) {
            $this->postJson('/api/v1/faculty/journal-deadlines', ['deadlines' => [
                ['internship_id' => $p['internship']->id, 'week_number' => $week, 'due_at' => '2026-06-10T23:59'],
            ]])->assertStatus(422);
        }
        $this->assertSame(0, JournalDeadline::count());

        // Internship without a start date has no journal weeks at all.
        $p['internship']->update(['start_date' => null]);
        $this->postJson('/api/v1/faculty/journal-deadlines', ['deadlines' => [
            ['internship_id' => $p['internship']->id, 'week_number' => 1, 'due_at' => '2026-06-10T23:59'],
        ]])->assertStatus(422);
    }

    public function test_jdl_04_05_06_save_authorize_and_show_to_student(): void
    {
        $p = $this->party('4ITD', '2026-06-01', '2026-06-30');

        // JDL-05: every non-assigned actor is denied.
        foreach ([$this->makeUser('faculty'), $p['coordinator'], $p['supervisor'], $p['student']] as $actor) {
            Sanctum::actingAs($actor);
            $this->postJson('/api/v1/faculty/journal-deadlines', ['deadlines' => [
                ['internship_id' => $p['internship']->id, 'week_number' => 2, 'due_at' => '2026-06-14T23:59'],
            ]])->assertForbidden();
            $this->getJson('/api/v1/faculty/journal-weeks?internship_ids[]='.$p['internship']->id)->assertForbidden();
        }
        $this->assertSame(0, JournalDeadline::count());

        // JDL-04: assigned faculty saves a valid student/week.
        Sanctum::actingAs($p['faculty']);
        $this->postJson('/api/v1/faculty/journal-deadlines', ['deadlines' => [
            ['internship_id' => $p['internship']->id, 'week_number' => 2, 'due_at' => '2026-06-14T23:59'],
        ]])->assertOk()
            ->assertJsonPath('data.0.week_number', 2)
            ->assertJsonPath('data.0.week_range_display', 'Jun 8 – Jun 14, 2026')
            ->assertJsonPath('data.0.due_at_display', 'June 14, 2026, 11:59 PM');

        // JDL-06: student reads week, range, and deadline.
        Sanctum::actingAs($p['student']);
        $deadline = $this->getJson('/api/v1/student/logbook')->assertOk()->json('journal_deadlines.0');
        $this->assertSame(2, $deadline['week_number']);
        $this->assertSame('Jun 8 – Jun 14, 2026', $deadline['week_range_display']);
        $this->assertSame('June 14, 2026, 11:59 PM', $deadline['due_at_display']);
        $this->assertNull($deadline['journal_status']);
    }

    public function test_jdl_07_08_on_time_and_late_submissions(): void
    {
        $p = $this->party('4ITD', '2026-06-01', '2026-06-30');
        Sanctum::actingAs($p['faculty']);
        $this->postJson('/api/v1/faculty/journal-deadlines', ['deadlines' => [
            ['internship_id' => $p['internship']->id, 'week_number' => 1, 'due_at' => '2026-06-08T23:59'],
            ['internship_id' => $p['internship']->id, 'week_number' => 2, 'due_at' => '2026-06-14T23:59'],
        ]])->assertOk();

        Sanctum::actingAs($p['student']);
        Carbon::setTestNow(Carbon::parse('2026-06-08 10:00:00', 'UTC')); // Jun 8, 6 PM Manila
        $this->postJson('/api/v1/student/logbook', ['date' => '2026-06-01', 'end_date' => '2026-06-05', 'activities_summary' => 'On time'])->assertCreated();
        Carbon::setTestNow(Carbon::parse('2026-06-15 02:00:00', 'UTC')); // Jun 15, 10 AM Manila
        $this->postJson('/api/v1/student/logbook', ['date' => '2026-06-08', 'end_date' => '2026-06-12', 'activities_summary' => 'Late but accepted'])->assertCreated();

        $weeks = JournalEntry::where('internship_id', $p['internship']->id)->get()->keyBy('week_number');
        $this->assertFalse($weeks[1]->submitted_late);
        $this->assertTrue($weeks[2]->submitted_late);

        $rows = collect($this->getJson('/api/v1/student/logbook')->json('journal_deadlines'))->keyBy('week_number');
        $this->assertFalse($rows[1]['submitted_late']);
        $this->assertTrue($rows[2]['submitted_late']);

        // Faculty review queue shows range, deadline, and timing per journal.
        Sanctum::actingAs($p['faculty']);
        $queue = collect($this->getJson('/api/v1/faculty/journals')->assertOk()->json('data'))->keyBy('week_number');
        $this->assertCount(2, $queue);
        $this->assertTrue($queue[2]['submitted_late']);
        $this->assertSame('Jun 8 – Jun 12, 2026', $queue[2]['range_display']);
        $this->assertSame('June 14, 2026, 11:59 PM', $queue[2]['deadline_display']);
        $this->assertSame('June 15, 2026, 10:00 AM', $queue[2]['submitted_at_display']);
    }

    public function test_jdl_09_students_with_different_start_dates_keep_their_own_weeks(): void
    {
        $faculty = $this->makeUser('faculty');
        $a = $this->party('4ITD', '2026-06-01', '2026-07-31', $faculty);
        $b = $this->party('4ITA', '2026-06-10', '2026-08-31', $faculty);

        Sanctum::actingAs($faculty);
        $data = collect($this->getJson('/api/v1/faculty/journal-weeks', [])->assertOk()->json('data'))->keyBy('internship_id');
        $this->assertSame('2026-06-01', $data[$a['internship']->id]['weeks'][0]['start_date']);
        $this->assertSame('2026-06-10', $data[$b['internship']->id]['weeks'][0]['start_date']);

        $this->postJson('/api/v1/faculty/journal-deadlines', ['deadlines' => [
            ['internship_id' => $a['internship']->id, 'week_number' => 1, 'due_at' => '2026-06-07T23:59'],
            ['internship_id' => $b['internship']->id, 'week_number' => 1, 'due_at' => '2026-06-16T23:59'],
        ]])->assertOk()
            ->assertJsonPath('data.0.week_range_display', 'Jun 1 – Jun 7, 2026')
            ->assertJsonPath('data.1.week_range_display', 'Jun 10 – Jun 16, 2026');

        $this->assertSame('2026-06-07 15:59:00', JournalDeadline::where('internship_id', $a['internship']->id)->first()->due_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-16 15:59:00', JournalDeadline::where('internship_id', $b['internship']->id)->first()->due_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_jdl_10_updating_a_deadline_updates_the_same_student_week(): void
    {
        $p = $this->party('4ITD', '2026-06-01', '2026-06-30');
        Sanctum::actingAs($p['faculty']);
        $payload = fn ($due) => ['deadlines' => [['internship_id' => $p['internship']->id, 'week_number' => 3, 'due_at' => $due]]];

        $this->postJson('/api/v1/faculty/journal-deadlines', $payload('2026-06-21T23:59'))->assertOk();
        $this->postJson('/api/v1/faculty/journal-deadlines', $payload('2026-06-22T12:00'))->assertOk();

        $this->assertSame(1, JournalDeadline::where('internship_id', $p['internship']->id)->count());
        $this->assertSame('2026-06-22 04:00:00', JournalDeadline::where('internship_id', $p['internship']->id)->first()->due_at->utc()->format('Y-m-d H:i:s'));

        // The same student week twice in one save is rejected, not duplicated.
        $this->postJson('/api/v1/faculty/journal-deadlines', ['deadlines' => [
            ['internship_id' => $p['internship']->id, 'week_number' => 3, 'due_at' => '2026-06-21T23:59'],
            ['internship_id' => $p['internship']->id, 'week_number' => 3, 'due_at' => '2026-06-23T23:59'],
        ]])->assertStatus(422);
    }

    // ── Restoring approvals lost outside the app ─────────────────────────────

    public function test_restore_command_recovers_only_safely_restorable_approvals(): void
    {
        $lost = $this->party('4ITD');
        $reset = $this->party('4ITA');
        $closed = $this->party('4ITB');
        $reassigned = $this->party('4ITC');

        foreach ([$lost, $reset, $closed, $reassigned] as $p) {
            Sanctum::actingAs($p['faculty']);
            $this->postJson('/api/v1/faculty/evaluations/'.$p['internship']->id.'/approve-period')->assertOk();
            // Simulate the approval being wiped at the database level.
            DB::table('internships')->where('id', $p['internship']->id)->update([
                'evaluation_period_status' => 'pending', 'evaluation_period_approved_by' => null, 'evaluation_period_approved_at' => null,
            ]);
        }
        audit_log(null, 'evaluation_period_reset', ['internship_id' => $reset['internship']->id]);
        DB::table('internships')->where('id', $closed['internship']->id)->update(['status' => 'cancelled']);
        DB::table('internships')->where('id', $reassigned['internship']->id)->update(['faculty_id' => $this->makeUser('faculty')->id]);

        $this->artisan('interntrack:restore-evaluation-approvals', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('pending', $lost['internship']->fresh()->evaluation_period_status, 'Dry run changes nothing.');

        $this->artisan('interntrack:restore-evaluation-approvals')->assertSuccessful();

        $restored = $lost['internship']->fresh();
        $this->assertSame('approved', $restored->evaluation_period_status);
        $this->assertSame($lost['faculty']->id, (int) $restored->evaluation_period_approved_by);
        $this->assertNotNull($restored->evaluation_period_approved_at);
        foreach ([$reset, $closed, $reassigned] as $p) {
            $this->assertSame('pending', $p['internship']->fresh()->evaluation_period_status);
        }

        Sanctum::actingAs($lost['student']);
        $this->getJson('/api/v1/student/evaluations')->assertJsonPath('evaluation_period.status', EvaluationPeriod::APPROVED);
    }

    // ── Frontend source regressions (no frontend test runner is configured) ──

    public function test_frontend_fixes_are_in_place(): void
    {
        $src = fn (string $path) => file_get_contents(base_path('../frontend/src/'.$path));

        // DTR preview flicker: overlay portaled to <body>, never nested in a hover-lifted card.
        // The preview now uses the shared AppModal, which owns the portal for every dialog.
        $modal = $src('components/portfolio/FormPreviewModal.jsx');
        $this->assertStringContainsString("import AppModal from '../modals/AppModal'", $modal);
        $this->assertStringContainsString('size="document"', $modal);
        $shell = $src('components/modals/AppModal.jsx');
        $this->assertStringContainsString('createPortal(', $shell);
        $this->assertStringContainsString('document.body', $shell);
        $css = $src('styles/styles.css');
        $this->assertStringContainsString('.content-card:has(.fpm-backdrop', $css);
        $attendanceTab = substr($src('pages/faculty/FacultyAssignedStudents.jsx'), strpos($src('pages/faculty/FacultyAssignedStudents.jsx'), 'function TabAttendance'));
        $this->assertStringContainsString('Kept outside every .content-card', $attendanceTab);

        // Student banner reads the authoritative state object only.
        $student = $src('pages/student/StudentEvaluations.jsx');
        $this->assertStringContainsString("period?.status === 'pending_faculty_approval'", $student);
        $this->assertStringNotContainsString('seed?.periodApproved', $student);
        // "Pending Forms" counts the four required forms, never total evaluations (was -1).
        $this->assertStringNotContainsString('4 - evaluations.length', $student);
        $this->assertStringContainsString("FORM_STATUS.filter(f => !f.eval || f.eval.status === 'pending').length", $student);

        // Journal deadline week is a selector fed by /faculty/journal-weeks.
        $manager = $src('components/faculty/JournalDeadlineManager.jsx');
        $this->assertStringNotContainsString('type="number"', $manager);
        $this->assertStringContainsString('/faculty/journal-weeks', $manager);
        $this->assertStringContainsString('<select', $manager);

        // FO-24 section layout + fixed section filter.
        $fo24 = $src('pages/faculty/FacultyEvaluations.jsx');
        $this->assertStringContainsString('fo24-review__table', $fo24);
        $this->assertStringNotContainsString('sec.id', $fo24);
        $this->assertStringContainsString('.fo24-actions', $css);
    }
}
