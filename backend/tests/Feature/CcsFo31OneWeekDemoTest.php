<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Evaluation;
use App\Models\JournalEntry;
use App\Models\SupervisorProfile;
use App\Services\InternshipProgressService;
use App\Services\OneWeekOjtDemoService;
use App\Services\SupervisorFeedbackService;
use App\Support\Fo31JournalPresenter;
use App\Support\ManilaAttendanceClock;
use App\Support\ManilaTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class CcsFo31OneWeekDemoTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    private function party(): array
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $supervisor = $this->makeUser('supervisor', 'SUP-0002');
        SupervisorProfile::create([
            'user_id' => $supervisor->id,
            'first_name' => 'Adrian',
            'last_name' => 'Reyes',
            'position' => 'Industry Supervisor',
            'email' => $supervisor->email,
        ]);
        $otherSupervisor = $this->makeUser('supervisor', 'SUP-UNRELATED');
        SupervisorProfile::create([
            'user_id' => $otherSupervisor->id,
            'first_name' => 'Other',
            'last_name' => 'Supervisor',
            'email' => $otherSupervisor->email,
        ]);
        $student = $this->makeStudentWithSection();
        $student->studentProfile->update([
            'first_name' => 'Clarence',
            'last_name' => 'Montealegre',
            'student_number' => '2300592',
        ]);
        $student->update(['student_number' => '2300592']);
        $otherStudent = $this->makeStudentWithSection('4ITA');
        $company = $this->makeEligibleCompany(['company_name' => 'TechCorp PH']);
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->update([
            'target_hours' => 500,
            'total_hours_rendered' => 0,
            'start_date' => '2026-08-24',
            'status' => 'ongoing',
        ]);
        $otherInternship = $this->makeActiveInternship($otherStudent, $company, $otherSupervisor, $faculty, $coordinator);

        return compact(
            'coordinator',
            'faculty',
            'supervisor',
            'otherSupervisor',
            'student',
            'otherStudent',
            'company',
            'internship',
            'otherInternship'
        );
    }

    public function test_fo31_date_range_and_week_labels(): void
    {
        $this->assertSame('August 24–28, 2026', Fo31JournalPresenter::dateRange('2026-08-24', '2026-08-28'));
        $this->assertSame('Week 1', Fo31JournalPresenter::weekLabel(1));
        $this->assertSame(8.0, ManilaAttendanceClock::creditedHours('08:00', '12:00', '13:00', '17:00'));
        $this->assertSame(8.0, ManilaAttendanceClock::creditedHours('07:58', '12:00', '13:00', '16:58'));
        $this->assertSame('00:00:00', ManilaAttendanceClock::storedTime('08:00'));
        $this->assertSame('09:00:00', ManilaAttendanceClock::storedTime('17:00'));
        $this->assertSame('23:58:00', ManilaAttendanceClock::storedTime('07:58'));
    }

    public function test_ccs_fo31_template_has_reusable_alignment_rules(): void
    {
        $jsx = file_get_contents(base_path('../frontend/src/components/portfolio/WeeklyInternshipJournal.jsx'));
        $css = file_get_contents(base_path('../frontend/src/assets/css/portfolio-print.css'));
        $this->assertIsString($jsx);
        $this->assertStringContainsString('fo31-page', $jsx);
        $this->assertStringContainsString('tableLayout: \'fixed\'', $jsx);
        $this->assertStringContainsString('overflowWrap: \'break-word\'', $jsx);
        $this->assertStringContainsString('formatFo31DateRange', $jsx);
        $this->assertStringContainsString('PortfolioSignature', $jsx);
        $this->assertStringContainsString('studentSignaturePath', $jsx);
        $this->assertStringNotContainsString('MONTEALEGRE, CLARENCE', $jsx);
        $this->assertStringNotContainsString('2300592', $jsx);
        $this->assertSame(1, substr_count($jsx, 'className="a4-page page-break portfolio-document fo31-page"'));
        $this->assertStringContainsString('.fo31-grid', $css);
        $this->assertStringContainsString('table-layout: fixed', $css);
    }

    public function test_five_validated_days_are_forty_of_five_hundred_and_eight_percent(): void
    {
        $party = $this->party();
        $logs = app(OneWeekOjtDemoService::class)->syncAttendance($party['internship'], $party['supervisor']);
        $this->assertCount(5, $logs);
        $this->assertEquals(40.0, collect($logs)->sum(fn ($log) => (float) $log->hours_rendered));

        InternshipProgressService::synchronize($party['internship']);
        $snap = InternshipProgressService::snapshot($party['internship']->fresh());
        $this->assertEquals(40.0, $snap['hours_rendered']);
        $this->assertEquals(500.0, $snap['target_hours']);
        $this->assertEquals(460.0, $snap['remaining_hours']);
        $this->assertEquals(8.0, $snap['progress_pct']);

        $eligibility = InternshipProgressService::evaluationEligibility($party['internship']->fresh());
        $this->assertSame('not_yet_eligible', $eligibility['status']);
        $this->assertSame('Not Yet Eligible', $eligibility['label']);
        $this->assertStringContainsString('50%', $eligibility['reason']);
    }

    public function test_attendance_appears_on_student_supervisor_and_fo30(): void
    {
        $party = $this->party();
        app(OneWeekOjtDemoService::class)->syncAttendance($party['internship'], $party['supervisor']);
        InternshipProgressService::synchronize($party['internship']);

        Sanctum::actingAs($party['student']);
        $studentAttendance = $this->getJson('/api/v1/student/attendance')->assertOk();
        $rows = collect($studentAttendance->json('attendance.data') ?? $studentAttendance->json('data') ?? []);
        $this->assertCount(5, $rows);
        $monday = $rows->firstWhere('date_display', '2026-08-24') ?? $rows->firstWhere('date', '2026-08-24');
        $this->assertNotNull($monday);
        $this->assertSame(ManilaTime::TZ, $monday['timezone'] ?? $studentAttendance->json('timezone'));
        $this->assertSame('08:00', $monday['clock_in_display']);
        $this->assertSame('17:00', $monday['clock_out_display']);
        $this->assertEquals(8.0, (float) ($monday['actual_hours'] ?? $monday['hours_rendered']));

        $wednesday = $rows->firstWhere('date_display', '2026-08-26') ?? $rows->firstWhere('date', '2026-08-26');
        $this->assertNotNull($wednesday);
        $this->assertSame('07:58', $wednesday['clock_in_display']);
        $this->assertSame('16:58', $wednesday['clock_out_display']);
        $this->assertEquals(8.0, (float) ($wednesday['actual_hours'] ?? $wednesday['hours_rendered']));
        $this->assertEquals(40.0, $rows->sum(fn ($row) => (float) ($row['actual_hours'] ?? $row['hours_rendered'])));

        $dashboard = $this->getJson('/api/v1/student/dashboard')->assertOk();
        $this->assertEquals(40.0, (float) $dashboard->json('stats.hours_rendered'));
        $this->assertEquals(500.0, (float) $dashboard->json('stats.target_hours'));
        $this->assertEquals(8.0, (float) $dashboard->json('stats.progress_percent'));

        $records = $this->getJson('/api/v1/student/records')->assertOk();
        $record = collect($records->json('data'))->firstWhere('id', $party['internship']->id);
        $this->assertNotNull($record);
        $this->assertEquals(40.0, (float) $record['total_hours_rendered']);
        $this->assertEquals(500.0, (float) $record['target_hours']);

        $portfolio = $this->getJson('/api/v1/student/portfolio')->assertOk()->json();
        $this->assertSame('MONTEALEGRE, CLARENCE', strtoupper($portfolio['identity']['student_name']));
        $this->assertSame('Bachelor of Science in Information Technology', $portfolio['identity']['program']);
        $this->assertSame('REYES, ADRIAN', strtoupper($portfolio['identity']['supervisor_name']));
        $this->assertSame('SUP-0002', $portfolio['identity']['supervisor_faculty_number']);
        $this->assertCount(5, $portfolio['internship']['attendance']);
        $this->assertSame('2026-08-24', $portfolio['internship']['attendance'][0]['date']);
        $this->assertSame('08:00', $portfolio['internship']['attendance'][0]['am_time_in']);
        $this->assertSame('17:00', $portfolio['internship']['attendance'][0]['pm_time_out']);
        $this->assertSame('2026-08-26', $portfolio['internship']['attendance'][2]['date']);
        $this->assertSame('07:58', $portfolio['internship']['attendance'][2]['am_time_in']);
        $this->assertSame('16:58', $portfolio['internship']['attendance'][2]['pm_time_out']);
        $this->assertEquals(40.0, collect($portfolio['internship']['attendance'])->sum('hours_rendered'));

        Sanctum::actingAs($party['supervisor']);
        $assigned = $this->getJson('/api/v1/supervisor/assigned-interns')->assertOk();
        $intern = collect($assigned->json('data'))->firstWhere('id', $party['internship']->id);
        $this->assertNotNull($intern);
        $this->assertEquals(40.0, (float) $intern['total_hours_rendered']);
        $this->assertEquals(500.0, (float) $intern['target_hours']);
        $this->assertSame('Not Yet Eligible', $intern['evaluation_eligibility']['label']);
        $this->assertCount(5, $intern['attendance_logs']);

        $dash = $this->getJson('/api/v1/supervisor/dashboard')->assertOk();
        $row = collect($dash->json('assigned_interns'))->firstWhere('id', $party['internship']->id);
        $this->assertSame('Montealegre, Clarence', $row['student']);
        $this->assertEquals(40.0, (float) $row['hours_rendered']);

        Sanctum::actingAs($party['faculty']);
        $facultyRoster = $this->getJson('/api/v1/faculty/assigned-students')->assertOk();
        $facultyRow = collect($facultyRoster->json('data'))->first(function ($row) {
            $number = $row['student']['student_number']
                ?? $row['student']['student_profile']['student_number']
                ?? null;

            return $number === '2300592';
        });
        $this->assertNotNull($facultyRow);
        $this->assertEquals(40.0, (float) $facultyRow['total_hours_rendered']);
        $this->assertEquals(500.0, (float) $facultyRow['target_hours']);
        $this->assertSame('TechCorp PH', $facultyRow['company']['company_name'] ?? $facultyRow['company']);
        $this->assertStringContainsString('Reyes', (string) (
            $facultyRow['supervisor']['supervisor_profile']['full_name']
            ?? $facultyRow['supervisor']['supervisor_profile']['last_name']
            ?? ''
        ));

        Sanctum::actingAs($party['otherSupervisor']);
        $otherAssigned = $this->getJson('/api/v1/supervisor/assigned-interns')->assertOk();
        $ids = collect($otherAssigned->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($party['internship']->id));
    }

    public function test_week1_journal_is_faculty_reviewed_and_absent_from_supervisor(): void
    {
        $party = $this->party();
        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/logbook', [
            'week_number' => 1,
            'date' => '2026-08-24',
            'end_date' => '2026-08-28',
            'activities_summary' => OneWeekOjtDemoService::ACCOMPLISHMENT,
            'challenges' => OneWeekOjtDemoService::DIFFICULTIES,
            'learnings' => OneWeekOjtDemoService::INSIGHTS,
        ])->assertCreated();

        $journalId = JournalEntry::where('internship_id', $party['internship']->id)->academic()->value('id');

        Sanctum::actingAs($party['supervisor']);
        $this->getJson('/api/v1/supervisor/journals')->assertNotFound();
        $this->patchJson('/api/v1/supervisor/journals/'.$journalId.'/review', [
            'action' => 'approved',
        ])->assertNotFound();

        Sanctum::actingAs($party['faculty']);
        $this->patchJson('/api/v1/faculty/journals/'.$journalId.'/review', [
            'action' => 'approved',
            'score' => 90,
            'feedback' => 'Approved Week 1 journal',
        ])->assertOk();

        $facultyList = $this->getJson('/api/v1/faculty/journals')->assertOk();
        $facultyRow = collect($facultyList->json('data'))->firstWhere('id', $journalId)
            ?? collect($facultyList->json('data'))->first(fn ($row) => (int) ($row['week_number'] ?? 0) === 1);
        $this->assertNotNull($facultyRow);
        $this->assertStringContainsString('Montealegre', (string) ($facultyRow['student_display_name'] ?? ''));
        $this->assertSame('Bachelor of Science in Information Technology', $facultyRow['program_name'] ?? null);

        Sanctum::actingAs($party['student']);
        $portfolio = $this->getJson('/api/v1/student/portfolio')->assertOk()->json();
        $journal = $portfolio['internship']['journals'][0];
        $this->assertSame(1, (int) $journal['week_number']);
        $this->assertSame('2026-08-24', $journal['date']);
        $this->assertSame('2026-08-28', $journal['end_date']);
        $this->assertSame(OneWeekOjtDemoService::ACCOMPLISHMENT, $journal['activities_summary']);
        $this->assertSame(OneWeekOjtDemoService::DIFFICULTIES, $journal['challenges']);
        $this->assertSame(OneWeekOjtDemoService::INSIGHTS, $journal['learnings']);
        $this->assertSame('August 24–28, 2026', Fo31JournalPresenter::dateRange($journal['date'], $journal['end_date']));
    }

    public function test_supervisor_feedback_is_visible_only_to_authorized_roles(): void
    {
        $party = $this->party();
        Sanctum::actingAs($party['supervisor']);
        $this->postJson('/api/v1/supervisor/feedback/'.$party['internship']->id, [
            'feedback' => OneWeekOjtDemoService::FEEDBACK,
        ])->assertOk();

        Sanctum::actingAs($party['student']);
        $this->getJson('/api/v1/student/supervisor-feedback')->assertOk()
            ->assertJsonPath('intern_feedback.feedback', OneWeekOjtDemoService::FEEDBACK);

        Sanctum::actingAs($party['faculty']);
        $faculty = $this->getJson('/api/v1/faculty/supervisor-feedback')->assertOk();
        $this->assertSame(OneWeekOjtDemoService::FEEDBACK, $faculty->json('data.0.feedback'));

        Sanctum::actingAs($party['coordinator']);
        $coord = $this->getJson('/api/v1/coordinator/supervisor-feedback')->assertOk();
        $this->assertSame(OneWeekOjtDemoService::FEEDBACK, $coord->json('data.0.feedback'));

        Sanctum::actingAs($party['otherSupervisor']);
        $hidden = $this->getJson('/api/v1/supervisor/feedback')->assertOk();
        $this->assertSame([], $hidden->json('data'));

        Sanctum::actingAs($party['otherStudent']);
        $other = $this->getJson('/api/v1/student/supervisor-feedback');
        if ($other->status() === 200) {
            $this->assertNull($other->json('intern_feedback'));
        }
    }

    public function test_portfolio_image_upload_replace_remove_and_preview_persistence(): void
    {
        Storage::fake('local');
        $party = $this->party();
        Sanctum::actingAs($party['student']);

        $first = $this->post('/api/v1/student/portfolio/photos', [
            'type' => 'company_logo',
            'file' => UploadedFile::fake()->image('logo-a.png', 40, 40),
        ], ['Accept' => 'application/json'])->assertCreated();
        $pathA = $first->json('document.file_path');

        $second = $this->post('/api/v1/student/portfolio/photos', [
            'type' => 'company_logo',
            'file' => UploadedFile::fake()->image('logo-b.png', 40, 40),
        ], ['Accept' => 'application/json'])->assertCreated();
        $pathB = $second->json('document.file_path');
        $this->assertNotSame($pathA, $pathB);

        $preview = $this->getJson('/api/v1/student/portfolio')->assertOk()->json();
        $this->assertSame($pathB, $preview['internship']['portfolio']['company_logo_path']);

        $this->postJson('/api/v1/student/portfolio', [
            'assessment_ethical' => OneWeekOjtDemoService::CHAPTER3['assessment_ethical'],
            'things_learned' => OneWeekOjtDemoService::CHAPTER3['assessment_learnings'],
            'experience_with_people' => OneWeekOjtDemoService::CHAPTER3['assessment_experience'],
            'industry_best_practices' => OneWeekOjtDemoService::CHAPTER3['assessment_standards'],
            'recommendations' => OneWeekOjtDemoService::CHAPTER3['assessment_recommendations'],
            'advice' => OneWeekOjtDemoService::CHAPTER3['assessment_advice'],
        ])->assertOk();

        $reload = $this->getJson('/api/v1/student/portfolio')->assertOk()->json();
        $this->assertSame(OneWeekOjtDemoService::CHAPTER3['assessment_ethical'], $reload['internship']['portfolio']['assessment_ethical']);
        $this->assertSame($pathB, $reload['internship']['portfolio']['company_logo_path']);

        $this->deleteJson('/api/v1/student/portfolio/photos/'.$second->json('document.id'))->assertOk();
        $afterDelete = $this->getJson('/api/v1/student/portfolio')->assertOk()->json();
        $this->assertEmpty($afterDelete['internship']['portfolio']['company_logo_path']);

        $this->post('/api/v1/student/portfolio/photos', [
            'type' => 'ojt_photo',
            'week_number' => 1,
            'file' => UploadedFile::fake()->image('week1.png', 40, 40),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->post('/api/v1/student/portfolio/photos', [
            'type' => 'ojt_photo',
            'week_number' => 1,
            'file' => UploadedFile::fake()->image('week1b.png', 40, 40),
        ], ['Accept' => 'application/json'])->assertCreated();

        $photos = collect($this->getJson('/api/v1/student/portfolio')->assertOk()->json('internship.portfolio.photos'));
        $this->assertGreaterThanOrEqual(2, $photos->where('type', 'ojt_photo')->count());
    }

    public function test_cross_student_portfolio_does_not_leak(): void
    {
        $party = $this->party();
        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/logbook', [
            'week_number' => 1,
            'date' => '2026-08-24',
            'end_date' => '2026-08-28',
            'activities_summary' => 'Student A journal only',
            'challenges' => 'A',
            'learnings' => 'A',
        ])->assertCreated();

        Sanctum::actingAs($party['otherStudent']);
        $other = $this->getJson('/api/v1/student/portfolio')->assertOk()->json();
        $summaries = collect($other['internship']['journals'] ?? [])->pluck('activities_summary');
        $this->assertFalse($summaries->contains('Student A journal only'));
        $this->assertNotSame('2300592', $other['identity']['student_number'] ?? null);
    }

    public function test_supervisor_evaluations_list_clarence_as_not_yet_eligible_without_fo24(): void
    {
        $party = $this->party();
        app(OneWeekOjtDemoService::class)->syncAttendance($party['internship'], $party['supervisor']);
        InternshipProgressService::synchronize($party['internship']);

        Sanctum::actingAs($party['supervisor']);
        $evals = $this->getJson('/api/v1/supervisor/evaluations')->assertOk();
        $pending = collect($evals->json('data.pending') ?? []);
        $row = $pending->first(fn ($item) => (int) ($item['id'] ?? 0) === (int) $party['internship']->id);
        $this->assertNotNull($row, $evals->getContent());
        $this->assertSame('Not Yet Eligible', $row['evaluation_eligibility']['label']);
        $this->assertFalse(Evaluation::where('internship_id', $party['internship']->id)->where('form_type', 'FO-24')->exists());
    }

    public function test_academic_journals_are_not_supervisor_notes(): void
    {
        $this->assertSame(0, JournalEntry::query()->where('status', SupervisorFeedbackService::NOTE_STATUS)->where('week_number', 1)->count());
        $this->assertSame(1, substr_count(
            file_get_contents(base_path('resources/views/pdf/form31_journal.blade.php')),
            'PNC:AA-FO-31 rev.0 02012023'
        ));
    }
}
