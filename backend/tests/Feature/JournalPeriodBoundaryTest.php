<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\Notification;
use App\Models\SupervisorProfile;
use App\Services\JournalPeriodValidator;
use App\Services\OneWeekOjtDemoService;
use App\Support\ManilaTime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class JournalPeriodBoundaryTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-12 12:00:00', ManilaTime::TZ));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function party(string $start = '2026-08-24', ?string $end = null): array
    {
        $student = $this->makeStudentWithSection();
        $faculty = $this->makeUser('faculty', 'FAC-BOUND-1');
        $this->mapFacultyForSection($faculty);
        $supervisor = $this->makeUser('supervisor', 'SUP-BOUND-1');
        SupervisorProfile::create([
            'user_id' => $supervisor->id,
            'first_name' => 'Bound',
            'last_name' => 'Supervisor',
            'email' => $supervisor->email,
            'position' => 'Industry Supervisor',
        ]);
        $company = $this->makeEligibleCompany();
        $coordinator = $this->makeUser('coordinator', 'COR-BOUND-1');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->forceFill([
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'ongoing',
        ])->save();

        return compact('student', 'faculty', 'supervisor', 'company', 'coordinator', 'internship');
    }

    public function test_journal_before_internship_start_rejected(): void
    {
        $party = $this->party('2026-08-24');
        Sanctum::actingAs($party['student']);

        $this->postJson('/api/v1/student/logbook', [
            'week_number' => 3,
            'date' => '2026-08-10',
            'end_date' => '2026-08-14',
            'activities_summary' => 'Invalid early week',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => JournalPeriodValidator::MSG_BEFORE_START]);
    }

    public function test_journal_crossing_internship_start_rejected(): void
    {
        $party = $this->party('2026-08-24');
        Sanctum::actingAs($party['student']);

        $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-08-20',
            'end_date' => '2026-08-26',
            'activities_summary' => 'Crosses start',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => JournalPeriodValidator::MSG_BEFORE_START]);
    }

    public function test_journal_exactly_on_internship_start_accepted(): void
    {
        $party = $this->party('2026-08-24');
        Sanctum::actingAs($party['student']);

        $res = $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-08-24',
            'end_date' => '2026-08-28',
            'activities_summary' => 'Week 1 start',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertCreated();

        $this->assertSame(1, (int) $res->json('journal.week_number'));
        $this->assertSame('2026-08-24', substr((string) $res->json('journal.date'), 0, 10));
    }

    public function test_journal_after_start_gets_chronological_week_number(): void
    {
        $party = $this->party('2026-08-24');
        Sanctum::actingAs($party['student']);

        $res = $this->postJson('/api/v1/student/logbook', [
            'week_number' => 99,
            'date' => '2026-08-31',
            'end_date' => '2026-09-04',
            'activities_summary' => 'Second week',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertCreated();

        $this->assertSame(2, (int) $res->json('journal.week_number'));
    }

    public function test_future_journal_rejected_in_asia_manila(): void
    {
        $party = $this->party('2026-08-24');
        Sanctum::actingAs($party['student']);

        $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-09-14',
            'end_date' => '2026-09-18',
            'activities_summary' => 'Future',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => JournalPeriodValidator::MSG_FUTURE]);
    }

    public function test_no_active_internship_rejected(): void
    {
        $student = $this->makeStudentWithSection();
        Sanctum::actingAs($student);

        $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-08-24',
            'end_date' => '2026-08-28',
            'activities_summary' => 'No company',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => JournalPeriodValidator::MSG_NO_ACTIVE]);
    }

    public function test_overlap_still_rejected_and_non_overlap_accepted(): void
    {
        $party = $this->party('2026-08-24');
        Sanctum::actingAs($party['student']);

        $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-08-24',
            'end_date' => '2026-08-28',
            'activities_summary' => 'W1',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertCreated();

        $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-08-31',
            'end_date' => '2026-09-04',
            'activities_summary' => 'W2',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertCreated();

        $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-08-27',
            'end_date' => '2026-09-02',
            'activities_summary' => 'Overlap',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => JournalPeriodValidator::MSG_OVERLAP]);

        $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-09-07',
            'end_date' => '2026-09-11',
            'activities_summary' => 'W3',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertCreated()
            ->assertJsonPath('journal.week_number', 3);
    }

    public function test_update_cannot_move_journal_before_internship_start(): void
    {
        $party = $this->party('2026-08-24');
        Sanctum::actingAs($party['student']);

        $created = $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-08-31',
            'end_date' => '2026-09-04',
            'activities_summary' => 'Valid first',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertCreated();

        $id = (int) $created->json('journal.id');

        $this->postJson('/api/v1/student/logbook', [
            'journal_id' => $id,
            'date' => '2026-08-10',
            'end_date' => '2026-08-14',
            'activities_summary' => 'Moved earlier',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => JournalPeriodValidator::MSG_BEFORE_START]);
    }

    public function test_approved_journal_remains_locked(): void
    {
        $party = $this->party('2026-08-24');
        Sanctum::actingAs($party['student']);

        $created = $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-08-24',
            'end_date' => '2026-08-28',
            'activities_summary' => 'Lock me',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertCreated();

        JournalEntry::whereKey($created->json('journal.id'))->update(['status' => 'approved']);

        $this->postJson('/api/v1/student/logbook', [
            'journal_id' => $created->json('journal.id'),
            'date' => '2026-08-24',
            'end_date' => '2026-08-28',
            'activities_summary' => 'Should fail',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertStatus(422);
    }

    public function test_creation_order_does_not_assign_invalid_week_number(): void
    {
        $party = $this->party('2026-08-24');
        Sanctum::actingAs($party['student']);

        $later = $this->postJson('/api/v1/student/logbook', [
            'week_number' => 1,
            'date' => '2026-08-31',
            'end_date' => '2026-09-04',
            'activities_summary' => 'Created first but week 2',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertCreated();

        $earlier = $this->postJson('/api/v1/student/logbook', [
            'week_number' => 9,
            'date' => '2026-08-24',
            'end_date' => '2026-08-28',
            'activities_summary' => 'Created second but week 1',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertCreated();

        $this->assertSame(2, (int) $later->json('journal.week_number'));
        $this->assertSame(1, (int) $earlier->json('journal.week_number'));
    }

    public function test_supervisor_still_cannot_validate_journals(): void
    {
        $party = $this->party('2026-08-24');
        Sanctum::actingAs($party['supervisor']);
        $this->getJson('/api/v1/supervisor/journals')->assertNotFound();
        $this->patchJson('/api/v1/supervisor/journals/1/review', ['action' => 'approved'])->assertNotFound();
    }

    public function test_clarence_invalid_aug10_journal_removed_and_portfolio_clean(): void
    {
        $student = $this->makeStudentWithSection();
        $faculty = $this->makeUser('faculty', 'FAC-1001');
        $this->mapFacultyForSection($faculty);
        $supervisor = $this->makeUser('supervisor', 'SUP-0002');
        SupervisorProfile::create([
            'user_id' => $supervisor->id,
            'first_name' => 'Adrian',
            'last_name' => 'Reyes',
            'email' => $supervisor->email,
            'position' => 'Industry Supervisor',
        ]);
        $company = $this->makeEligibleCompany(['company_name' => 'Accenture PH']);
        $coordinator = $this->makeUser('coordinator', 'COR-CCS-001');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->forceFill([
            'start_date' => '2026-08-24',
            'status' => 'ongoing',
            'target_hours' => 500,
        ])->save();

        app(OneWeekOjtDemoService::class); // ensure service class is loadable

        JournalEntry::create([
            'internship_id' => $internship->id,
            'entry_number' => 1,
            'week_number' => 1,
            'date' => '2026-08-24',
            'end_date' => '2026-08-28',
            'activities_summary' => OneWeekOjtDemoService::ACCOMPLISHMENT,
            'challenges' => 'x',
            'learnings' => 'y',
            'status' => 'approved',
            'faculty_reviewed_by' => $faculty->id,
            'faculty_reviewed_at' => now(),
        ]);
        JournalEntry::create([
            'internship_id' => $internship->id,
            'entry_number' => 2,
            'week_number' => 2,
            'date' => '2026-08-31',
            'end_date' => '2026-09-04',
            'activities_summary' => OneWeekOjtDemoService::WEEK2_ACCOMPLISHMENT,
            'challenges' => OneWeekOjtDemoService::WEEK2_DIFFICULTIES,
            'learnings' => OneWeekOjtDemoService::WEEK2_INSIGHTS,
            'status' => 'approved',
            'faculty_reviewed_by' => $faculty->id,
            'faculty_reviewed_at' => now(),
        ]);

        $invalid = JournalEntry::create([
            'internship_id' => $internship->id,
            'entry_number' => 3,
            'week_number' => 3,
            'date' => '2026-08-10',
            'end_date' => '2026-08-14',
            'activities_summary' => 'Invalid pre-start test journal',
            'challenges' => 'x',
            'learnings' => 'y',
            'status' => 'submitted',
        ]);

        Notification::notify(
            (int) $faculty->id,
            'journal_submitted',
            'Weekly journal submitted',
            'Invalid week',
            '/faculty/journals',
            ['journal_id' => $invalid->id, 'internship_id' => $internship->id]
        );

        $this->assertNotNull(JournalEntry::find($invalid->id));

        // Safe cleanup: internship + exact id + exact range.
        $target = JournalEntry::query()
            ->whereKey($invalid->id)
            ->where('internship_id', $internship->id)
            ->whereDate('date', '2026-08-10')
            ->whereDate('end_date', '2026-08-14')
            ->first();
        $this->assertNotNull($target);
        Notification::query()
            ->where('data->journal_id', $target->id)
            ->delete();
        $target->forceDelete();

        $this->assertNull(JournalEntry::withTrashed()->find($invalid->id));

        $weeks = JournalEntry::where('internship_id', $internship->id)
            ->academic()
            ->orderBy('week_number')
            ->get(['week_number', 'date', 'end_date']);
        $this->assertCount(2, $weeks);
        $this->assertSame(1, (int) $weeks[0]->week_number);
        $this->assertSame('2026-08-24', $weeks[0]->date?->toDateString());
        $this->assertSame(2, (int) $weeks[1]->week_number);
        $this->assertSame('2026-08-31', $weeks[1]->date?->toDateString());

        Sanctum::actingAs($student);
        $portfolio = $this->getJson('/api/v1/student/portfolio')->assertOk()->json();
        $journals = collect($portfolio['internship']['journals'] ?? []);
        $this->assertFalse($journals->contains(fn ($j) => ($j['date'] ?? null) === '2026-08-10'));
        $this->assertNotNull($journals->firstWhere('week_number', 1));
        $this->assertNotNull($journals->firstWhere('week_number', 2));

        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/logbook', [
            'week_number' => 3,
            'date' => '2026-08-10',
            'end_date' => '2026-08-14',
            'activities_summary' => 'Bypass attempt',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertStatus(422);
    }

    public function test_generic_october_start_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00', ManilaTime::TZ));
        $party = $this->party('2026-10-05');
        Sanctum::actingAs($party['student']);

        $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-09-28',
            'end_date' => '2026-10-02',
            'activities_summary' => 'Too early',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertStatus(422);

        $ok = $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-10-05',
            'end_date' => '2026-10-09',
            'activities_summary' => 'Valid week 1',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertCreated();
        $this->assertSame(1, (int) $ok->json('journal.week_number'));

        $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-10-12',
            'end_date' => '2026-10-16',
            'activities_summary' => 'Valid week 2',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertCreated();

        $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-10-08',
            'end_date' => '2026-10-14',
            'activities_summary' => 'Overlap',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertStatus(422);
    }

    public function test_logbook_exposes_period_bounds(): void
    {
        $party = $this->party('2026-08-24', '2026-12-18');
        Sanctum::actingAs($party['student']);

        $res = $this->getJson('/api/v1/student/logbook')->assertOk();
        $this->assertTrue((bool) $res->json('journal_period.can_create'));
        $this->assertSame('2026-08-24', $res->json('journal_period.start_date'));
        $this->assertSame('2026-12-18', $res->json('journal_period.end_date'));
        $this->assertSame('2026-09-12', $res->json('journal_period.today'));
    }
}
