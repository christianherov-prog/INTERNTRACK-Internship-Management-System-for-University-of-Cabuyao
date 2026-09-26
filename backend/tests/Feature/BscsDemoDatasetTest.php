<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\Internship;
use App\Models\InternshipApplication;
use App\Models\JournalEntry;
use App\Models\StudentProfile;
use App\Models\SupervisorProfile;
use App\Models\User;
use App\Services\DocumentComplianceService;
use App\Services\InternshipProgressService;
use App\Services\OfficialFormDataService;
use App\Services\ProgramRequirementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * BSCS-DEMO-01..16 plus CCS totals, on the seeded controlled CCS dataset.
 * BSCS hour targets come from the program requirement (300 h), never a
 * hard-coded number; counts come from real records.
 */
class BscsDemoDatasetTest extends TestCase
{
    use RefreshDatabase;

    private const TERRENCE = '2300613';

    private const ADA = '2300611';

    private const NATHAN = '2300612';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Artisan::call('interntrack:seed-ccs-demo');
    }

    private function student(string $studentNumber): User
    {
        return User::findOrFail(StudentProfile::where('student_number', $studentNumber)->value('user_id'));
    }

    private function internship(string $studentNumber): Internship
    {
        return Internship::where('student_id', $this->student($studentNumber)->id)->orderByDesc('id')->firstOrFail();
    }

    private function bscsTarget(): float
    {
        return ProgramRequirementService::targetHoursFor(\App\Models\Program::where('code', 'BSCS')->firstOrFail());
    }

    private function validatedHours(Internship $internship): float
    {
        return (float) $internship->attendance()->where('status', 'validated')->sum('hours_rendered');
    }

    /** BSCS-DEMO-01 and program totals (BSIT 9, BSCS 3, CCS 12 — from records). */
    public function test_exactly_three_bscs_students_and_twelve_ccs_students(): void
    {
        $ccs = \App\Models\Department::where('code', 'CCS')->firstOrFail();
        $controlled = ['2300500', '2300501', '2300502', '2300590', '2300592', '2300595', '2300600', '2300609', '2300610', self::ADA, self::NATHAN, self::TERRENCE];

        $byProgram = StudentProfile::whereIn('student_number', $controlled)->with('program')->get()
            ->groupBy(fn ($p) => $p->program->code)->map->count();
        $this->assertSame(9, $byProgram['BSIT'] ?? 0);
        $this->assertSame(3, $byProgram['BSCS'] ?? 0);
        $this->assertSame(12, $byProgram->sum());
        $this->assertSame(3, StudentProfile::where('department_id', $ccs->id)->whereHas('program', fn ($q) => $q->where('code', 'BSCS'))->count());

        foreach ($controlled as $num) {
            $this->assertSame(1, StudentProfile::where('student_number', $num)->count(), "{$num} must exist exactly once");
        }

        $statuses = collect($controlled)->map(fn ($n) => $this->internship($n)->status)->countBy();
        $this->assertSame(4, $statuses['completed'] ?? 0);
        $this->assertSame(4, $statuses['active'] ?? 0);
        $this->assertSame(4, $statuses['pending_placement'] ?? 0);
    }

    /** BSCS-DEMO-02..07 */
    public function test_terrence_is_a_complete_finished_bscs_intern_at_cognizant(): void
    {
        $internship = $this->internship(self::TERRENCE)->load('company', 'student.studentProfile');
        $target = $this->bscsTarget();

        $this->assertSame('completed', $internship->status);
        $this->assertSame('Cognizant Philippines', $internship->company->company_name);
        $this->assertSame('4CSA', $internship->student->studentProfile->section);
        $this->assertSame(User::where('faculty_number', 'FAC-1001')->value('id'), (int) $internship->faculty_id);

        // Exactly the program target, from authoritative attendance rows.
        $this->assertEquals($target, $this->validatedHours($internship));
        $this->assertEquals($target, (float) $internship->total_hours_rendered);
        $this->assertSame(0, $internship->attendance()->where('status', '!=', 'validated')->count());
        $this->assertLessThan('2026-09-01', $internship->end_date->toDateString(), 'Finished by August 2026');
        $snap = InternshipProgressService::snapshot($internship);
        $this->assertEquals($target, $snap['target_hours']);
        $this->assertEquals(100.0, $snap['progress_pct']);

        // FO-30 equals the source attendance.
        $fo30 = app(OfficialFormDataService::class)->fo30($internship);
        $this->assertEquals($target, collect($fo30['logs'] ?? [])->sum(fn ($r) => (float) ($r['hours_rendered'] ?? 0)));

        // Supervisor belongs to Cognizant.
        $supervisor = SupervisorProfile::where('user_id', $internship->supervisor_id)->firstOrFail();
        $this->assertSame($internship->company_id, (int) $supervisor->company_id);

        // Journals: every week present, distinct, approved by Marvin.
        $journals = JournalEntry::where('internship_id', $internship->id)->orderBy('week_number')->get();
        $this->assertGreaterThan(0, $journals->count());
        $this->assertSame(range(1, $journals->count()), $journals->pluck('week_number')->all());
        $this->assertCount($journals->count(), $journals->pluck('activities_summary')->unique());
        foreach ($journals as $journal) {
            $this->assertSame('approved', $journal->status);
            $this->assertSame((int) $internship->faculty_id, (int) $journal->faculty_reviewed_by);
        }

        // Requirements: every applicable one satisfied (count derived, not assumed).
        $compliance = app(DocumentComplianceService::class)->evaluateStudent($internship->student, $internship);
        $this->assertGreaterThan(0, $compliance['required_count']);
        $this->assertSame($compliance['required_count'], $compliance['satisfied_count']);

        // Evaluations: final forms present; FO-24 mixes 8/9/10 and differs from BSIT finishers.
        $fo24 = Evaluation::where('internship_id', $internship->id)->where('form_type', 'FO-24')->firstOrFail();
        $this->assertEqualsCanonicalizing([80, 90, 100], collect($fo24->responses)->map(fn ($v) => (int) $v)->unique()->values()->all());
        $clarenceFo24 = Evaluation::where('internship_id', $this->internship('2300592')->id)->where('form_type', 'FO-24')->firstOrFail();
        $this->assertNotEquals($clarenceFo24->responses, $fo24->responses);
        foreach (['FO-03', 'FO-22', 'FO-23'] as $form) {
            $this->assertTrue(Evaluation::where('internship_id', $internship->id)->where('form_type', $form)->exists(), $form);
        }

        // Portfolio and absorption tracking.
        $this->assertSame('Cognizant Philippines', \App\Models\StudentPortfolio::where('internship_id', $internship->id)->value('company_name'));
        $this->assertSame('pending', $internship->absorption_status);
        $this->assertTrue((bool) $internship->student_declared_hired);

        // Single accepted application.
        $this->assertSame(1, InternshipApplication::where('student_id', $internship->student_id)->where('status', 'approved')->count());
    }

    /** BSCS-DEMO-08..12 */
    public function test_ada_is_an_ongoing_bscs_intern_at_ntt_data(): void
    {
        $internship = $this->internship(self::ADA)->load('company', 'student.studentProfile');

        $this->assertSame('active', $internship->status);
        $this->assertSame('NTT DATA Philippines', $internship->company->company_name);
        $this->assertSame('4CSB', $internship->student->studentProfile->section);
        $this->assertSame(User::where('faculty_number', 'COR-CCS-001')->value('id'), (int) $internship->faculty_id);

        $hours = $this->validatedHours($internship);
        $this->assertEquals(216.0, $hours);
        $this->assertEquals(216.0, (float) $internship->total_hours_rendered);
        $this->assertLessThan($this->bscsTarget(), $hours);
        $this->assertSame(0, $internship->attendance()->where('date', '>', now('Asia/Manila')->toDateString())->count(), 'No future attendance');

        $supervisor = SupervisorProfile::where('user_id', $internship->supervisor_id)->firstOrFail();
        $this->assertSame($internship->company_id, (int) $supervisor->company_id);

        // Requirements: mixed approved / pending / returned, with at least one missing.
        $statuses = Document::where('internship_id', $internship->id)->pluck('status')->countBy();
        $this->assertGreaterThan(0, $statuses['approved'] ?? 0);
        $this->assertGreaterThan(0, $statuses['pending_review'] ?? 0);
        $this->assertGreaterThan(0, $statuses['rejected'] ?? 0);
        $compliance = app(DocumentComplianceService::class)->evaluateStudent($internship->student, $internship);
        $this->assertLessThan($compliance['required_count'], $compliance['satisfied_count']);
        $this->assertGreaterThan(0, $compliance['compliance_pct']);

        // Journals: earlier weeks approved, latest finished week awaiting review, current week not submitted.
        $journals = JournalEntry::where('internship_id', $internship->id)->orderBy('week_number')->get();
        $this->assertSame('submitted', $journals->last()->status);
        $this->assertTrue($journals->slice(0, -1)->every(fn ($j) => $j->status === 'approved'));
        $this->assertTrue($journals->every(fn ($j) => $j->end_date->toDateString() <= now('Asia/Manila')->toDateString()));

        // Only currently eligible evaluations: no final internship evaluation yet.
        $this->assertFalse(Evaluation::where('internship_id', $internship->id)->where('form_type', 'FO-24')->exists());
    }

    /** BSCS-DEMO-13..16 */
    public function test_nathan_is_a_fresh_bscs_enrollee_with_zero_progress(): void
    {
        $internship = $this->internship(self::NATHAN)->load('student.studentProfile');

        $this->assertSame('pending_placement', $internship->status);
        $this->assertSame('4CSA', $internship->student->studentProfile->section);
        $this->assertEquals(0.0, (float) $internship->total_hours_rendered);
        $this->assertSame(0, $internship->attendance()->count());
        $this->assertNull($internship->company_id);
        $this->assertNull($internship->supervisor_id);
        $this->assertSame(0, JournalEntry::where('internship_id', $internship->id)->count());
        $this->assertSame(0, Evaluation::where('internship_id', $internship->id)->count());
        $this->assertNull($internship->absorption_status);
        $this->assertSame(User::where('faculty_number', 'FAC-1001')->value('id'), (int) $internship->faculty_id);

        // Placement Hub: adviser present, nothing locked, CCS companies visible.
        Sanctum::actingAs($this->student(self::NATHAN));
        $this->getJson('/api/v1/student/applications')->assertOk()
            ->assertJsonPath('adviser.assigned', true)
            ->assertJsonPath('placement_lock.locked', false);
        $this->assertCount(10, $this->getJson('/api/v1/student/companies')->assertOk()->json('companies'));
    }

    /** Slot effects follow the real placement logic. */
    public function test_bscs_placements_affect_company_slots(): void
    {
        $cognizant = Company::where('company_name', 'Cognizant Philippines')->firstOrFail();
        $ntt = Company::where('company_name', 'NTT DATA Philippines')->firstOrFail();

        // Completed placement freed its slot; active one occupies one.
        $this->assertSame(59, (int) $cognizant->slots_available);
        $this->assertSame(33 - 1, (int) $ntt->slots_available);
        $this->assertSame(1, Internship::where('company_id', $ntt->id)->where('status', 'active')->count());
    }

    /** Faculty summary derives from sections + actual advisers (not forced). */
    public function test_faculty_advisee_counts_by_program(): void
    {
        $counts = function (string $facultyNumber): array {
            $faculty = User::where('faculty_number', $facultyNumber)->firstOrFail();
            // Rosters are department-scoped to the logged-in faculty.
            Sanctum::actingAs($faculty);

            return \App\Services\FacultySectionAssignmentService::assignedStudentsQuery($faculty, false)
                ->with('studentProfile.program')->get()
                ->groupBy(fn ($u) => $u->studentProfile->program->code)->map->count()->all();
        };

        $this->assertEquals(['BSIT' => 5, 'BSCS' => 2], $counts('FAC-1001'));
        $this->assertEquals(['BSIT' => 4, 'BSCS' => 1], $counts('COR-CCS-001'));
    }

    /** Re-running the command creates nothing new. */
    public function test_rerun_is_idempotent_for_bscs_records(): void
    {
        $snapshot = fn () => [
            StudentProfile::whereIn('student_number', [self::ADA, self::NATHAN, self::TERRENCE])->count(),
            Internship::whereIn('student_id', StudentProfile::whereIn('student_number', [self::ADA, self::NATHAN, self::TERRENCE])->pluck('user_id'))->count(),
            \App\Models\AttendanceLog::count(),
            JournalEntry::count(),
            SupervisorProfile::count(),
            \App\Models\FacultySectionAssignment::count(),
            Company::orderBy('id')->pluck('slots_available')->all(),
        ];
        $before = $snapshot();

        Artisan::call('interntrack:seed-ccs-demo');

        $this->assertSame($before, $snapshot());
    }
}
