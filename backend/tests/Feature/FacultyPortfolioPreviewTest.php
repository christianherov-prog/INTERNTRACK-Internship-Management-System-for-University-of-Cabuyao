<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\JournalEntry;
use App\Models\StudentPortfolio;
use App\Models\SupervisorProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

/**
 * Faculty "Preview Portfolio" on Assigned Students.
 *
 * Every account in these tests is created fresh by the fixtures, so the cases
 * also prove the feature works for new Faculty and Student accounts without any
 * account-specific code.
 */
class FacultyPortfolioPreviewTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    private function party(string $section = '4ITD', bool $mapFaculty = false, string $companyName = 'TechCorp PH'): array
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        if ($mapFaculty) {
            $this->mapFacultyForSection($faculty, $section);
        }
        $supervisor = $this->makeUser('supervisor');
        SupervisorProfile::create([
            'user_id' => $supervisor->id,
            'first_name' => 'Industry',
            'last_name' => 'Magtibay',
            'position' => 'IT Supervisor',
            'email' => $supervisor->email,
        ]);
        $student = $this->makeStudentWithSection($section);
        $company = $this->makeEligibleCompany(['company_name' => $companyName, 'address' => 'Cabuyao, Laguna']);
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->update([
            'start_date' => '2026-06-01',
            'end_date' => '2026-09-30',
            'school_year' => '2025-2026',
            'semester' => '2nd Semester',
        ]);

        return compact('coordinator', 'faculty', 'supervisor', 'student', 'company', 'internship');
    }

    private function portfolioUrl(User $student): string
    {
        return '/api/v1/faculty/students/'.$student->id.'/portfolio';
    }

    private function journal(array $party, int $week, string $status, string $summary): JournalEntry
    {
        return JournalEntry::create([
            'internship_id' => $party['internship']->id,
            'week_number' => $week,
            'entry_number' => $week,
            'date' => Carbon::parse('2026-06-01')->addWeeks($week - 1)->toDateString(),
            'end_date' => Carbon::parse('2026-06-05')->addWeeks($week - 1)->toDateString(),
            'activities_summary' => $summary,
            'status' => $status,
        ]);
    }

    /** FAC-PORT-01 */
    public function test_assigned_faculty_can_retrieve_assigned_student_portfolio(): void
    {
        $party = $this->party();
        Sanctum::actingAs($party['faculty']);

        $rows = collect($this->getJson('/api/v1/faculty/assigned-students')->assertOk()->json('data'));
        $row = $rows->firstWhere('student_id', $party['student']->id);
        $this->assertNotNull($row, 'Assigned student must appear on the roster.');
        $this->assertTrue($row['can_preview_portfolio']);
        $this->assertSame($party['internship']->id, $row['id']);

        $payload = $this->getJson($this->portfolioUrl($party['student']))->assertOk()->json();

        $this->assertTrue($payload['read_only']);
        $this->assertSame($party['student']->id, $payload['user']['id']);
        $this->assertSame($party['internship']->id, $payload['internship']['id']);
        $this->assertSame($party['student']->student_number, $payload['identity']['student_number']);
        $this->assertSame('TechCorp PH', $payload['identity']['company_name']);
        $this->assertSame('Bachelor of Science in Information Technology', $payload['identity']['program']);
    }

    /** FAC-PORT-02 */
    public function test_unassigned_faculty_cannot_retrieve_student_portfolio(): void
    {
        $party = $this->party();
        $otherFaculty = $this->makeUser('faculty');
        Sanctum::actingAs($otherFaculty);

        $this->getJson($this->portfolioUrl($party['student']))->assertForbidden();

        $rows = collect($this->getJson('/api/v1/faculty/assigned-students')->assertOk()->json('data'));
        $this->assertNull($rows->firstWhere('student_id', $party['student']->id));
    }

    /** FAC-PORT-03 */
    public function test_coordinator_not_assigned_as_faculty_is_denied(): void
    {
        $party = $this->party();
        Sanctum::actingAs($party['coordinator']);

        $this->getJson($this->portfolioUrl($party['student']))->assertForbidden();
    }

    /** FAC-PORT-04 */
    public function test_supervisor_cannot_use_faculty_portfolio_endpoint(): void
    {
        $party = $this->party();

        Sanctum::actingAs($party['supervisor']);
        $this->getJson($this->portfolioUrl($party['student']))->assertForbidden();

        Sanctum::actingAs($party['student']);
        $this->getJson($this->portfolioUrl($party['student']))->assertForbidden();
    }

    /** FAC-PORT-05 */
    public function test_student_a_data_never_returned_for_student_b(): void
    {
        $faculty = $this->makeUser('faculty');
        $partyA = $this->party('4ITD', false, 'HTE Alpha');
        $partyB = $this->party('4ITA', false, 'HTE Bravo');
        $partyA['internship']->update(['faculty_id' => $faculty->id]);
        $partyB['internship']->update(['faculty_id' => $faculty->id]);
        $this->journal($partyA, 1, 'approved', 'Student A only journal');
        $this->journal($partyB, 1, 'approved', 'Student B journal');

        Sanctum::actingAs($faculty);
        $b = $this->getJson($this->portfolioUrl($partyB['student']))->assertOk()->json();

        $this->assertSame($partyB['student']->id, $b['user']['id']);
        $this->assertSame($partyB['internship']->id, $b['internship']['id']);
        $this->assertSame('HTE Bravo', $b['identity']['company_name']);
        $summaries = collect($b['internship']['journals'])->pluck('activities_summary');
        $this->assertTrue($summaries->contains('Student B journal'));
        $this->assertFalse($summaries->contains('Student A only journal'));
        $this->assertStringNotContainsString('HTE Alpha', json_encode($b));
    }

    /** FAC-PORT-06 */
    public function test_new_student_with_minimal_data_opens_safely_without_side_effects(): void
    {
        $faculty = $this->makeUser('faculty');
        $student = $this->makeStudentWithSection('4ITB');
        $internship = $this->makePendingInternship($student);
        $internship->forceFill(['faculty_id' => $faculty->id])->save();

        Sanctum::actingAs($faculty);
        $payload = $this->getJson($this->portfolioUrl($student))->assertOk()->json();

        $this->assertSame($student->id, $payload['user']['id']);
        $this->assertSame([], $payload['internship']['journals']);
        $this->assertSame([], $payload['internship']['attendance']);
        $this->assertSame([], $payload['internship']['evaluations']);
        $this->assertSame('', $payload['internship']['portfolio']['company_vision']);
        $this->assertSame('', $payload['internship']['portfolio']['assessment_ethical']);
        $this->assertNull($payload['identity']['company_name']);
        $this->assertSame(0, StudentPortfolio::where('internship_id', $internship->id)->count(),
            'The preview GET must not create portfolio rows.');
    }

    /** FAC-PORT-07 and FAC-PORT-08 */
    public function test_only_approved_journals_appear_in_chronological_order(): void
    {
        $party = $this->party();
        $this->journal($party, 3, 'approved', 'Week three approved');
        $this->journal($party, 1, 'approved', 'Week one approved');
        $this->journal($party, 2, 'submitted', 'Week two still submitted');
        $this->journal($party, 4, 'needs_revision', 'Week four needs revision');

        Sanctum::actingAs($party['faculty']);
        $journals = collect($this->getJson($this->portfolioUrl($party['student']))->assertOk()->json('internship.journals'));

        $this->assertSame(['Week one approved', 'Week three approved'], $journals->pluck('activities_summary')->all());
        $this->assertSame([1, 3], $journals->pluck('week_number')->map(fn ($w) => (int) $w)->all());

        Sanctum::actingAs($party['student']);
        $studentJournals = collect($this->getJson('/api/v1/student/portfolio')->assertOk()->json('internship.journals'));
        $this->assertSame($studentJournals->pluck('id')->all(), $journals->pluck('id')->all());
    }

    /** FAC-PORT-09 */
    public function test_fo30_attendance_matches_the_student_portfolio(): void
    {
        $party = $this->party();
        Carbon::setTestNow(Carbon::parse('2026-09-09 00:01:00', 'UTC'));
        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
        Carbon::setTestNow(Carbon::parse('2026-09-09 09:02:00', 'UTC'));
        $this->postJson('/api/v1/student/attendance/clock-out')->assertOk();

        $studentDtr = $this->getJson('/api/v1/student/portfolio')->assertOk()->json('internship.attendance');

        Sanctum::actingAs($party['faculty']);
        $facultyDtr = $this->getJson($this->portfolioUrl($party['student']))->assertOk()->json('internship.attendance');
        Carbon::setTestNow();

        $this->assertCount(1, $facultyDtr);
        $this->assertSame('2026-09-09', $facultyDtr[0]['date']);
        $this->assertSame('08:01', $facultyDtr[0]['am_time_in']);
        $this->assertSame('17:02', $facultyDtr[0]['pm_time_out']);
        $this->assertEquals($studentDtr, $facultyDtr);
    }

    /** FAC-PORT-10 and FAC-PORT-11 */
    public function test_evaluation_forms_appear_and_faculty_evaluation_is_excluded(): void
    {
        $party = $this->party();
        Storage::disk('local')->put('signatures/'.$party['supervisor']->id.'_processed.png', $this->png());
        Storage::disk('local')->put('signatures/'.$party['faculty']->id.'_processed.png', $this->png());
        $this->approveEvaluationPeriod($party['internship'], $party['faculty']);

        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/evaluations', [
            'evaluation_period' => 'final',
            'form_type' => 'FO-22',
            'responses' => ['q1' => 5, 'q2' => 5, 'q3' => 4, 'q4' => 4, 'q5' => 5, 'q6' => 4, 'q7' => 5, 'recommend' => 'yes'],
        ])->assertCreated();
        $this->postJson('/api/v1/student/evaluations', [
            'evaluation_period' => 'final',
            'form_type' => 'FO-23',
            'responses' => ['q1' => 5, 'q2' => 4],
        ])->assertCreated();

        Sanctum::actingAs($party['supervisor']);
        $this->postJson('/api/v1/supervisor/evaluations/'.$party['internship']->id, [
            'evaluation_period' => 'final',
            'form_type' => 'FO-24',
            'responses' => [
                'c1' => 100, 'c2' => 80, 'c3' => 80, 'c4' => 80, 'c5' => 80,
                'c6' => 80, 'c7' => 80, 'c8' => 80, 'c9' => 80, 'c10' => 80,
            ],
        ])->assertCreated();
        $this->postJson('/api/v1/supervisor/evaluations/'.$party['internship']->id, [
            'evaluation_period' => 'final',
            'form_type' => 'FO-03',
            'responses' => ['crit_0' => 5, 'would_hire' => 'yes'],
        ])->assertCreated();

        Sanctum::actingAs($party['faculty']);
        $this->postJson('/api/v1/faculty/evaluations/'.$party['internship']->id, [
            'evaluation_period' => 'final',
            'overall_score' => 88,
            'general_comments' => 'Faculty remarks',
        ])->assertCreated();

        $evals = collect($this->getJson($this->portfolioUrl($party['student']))->assertOk()->json('internship.evaluations'));

        foreach (['FO-24', 'FO-03', 'FO-22', 'FO-23'] as $form) {
            $this->assertNotNull($evals->firstWhere('form_type', $form), "{$form} must appear in the Faculty preview.");
        }
        $this->assertFalse($evals->contains(fn ($e) => ($e['form_type'] ?? '') === 'faculty_eval'));
        $this->assertStringNotContainsString('Faculty remarks', json_encode($evals));
    }

    /** FAC-PORT-12 */
    public function test_cross_faculty_id_manipulation_is_denied(): void
    {
        $partyA = $this->party('4ITD');
        $partyB = $this->party('4ITA');

        Sanctum::actingAs($partyA['faculty']);
        $this->getJson($this->portfolioUrl($partyA['student']))->assertOk();
        $this->getJson($this->portfolioUrl($partyB['student']))->assertForbidden();
        $this->getJson($this->portfolioUrl($partyB['faculty']))->assertForbidden();
        $this->getJson('/api/v1/faculty/students/999999/portfolio')->assertForbidden();
        $this->getJson('/api/v1/student/portfolio?internship_id='.$partyB['internship']->id)->assertForbidden();
    }

    /**
     * A new section mapping syncs internships.faculty_id, so the mapped faculty
     * gains preview access through the normal workflow. If the internship is
     * later handled by someone else, the section listing alone does not grant it.
     */
    /**
     * The actual adviser (internship faculty) decides roster membership and
     * preview access. Mapping a section to someone else neither takes over a
     * Student who already has an adviser nor lists them on that roster.
     */
    public function test_section_mapping_does_not_override_actual_adviser_or_grant_preview(): void
    {
        $party = $this->party('4ITC');
        $sectionFaculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($sectionFaculty, '4ITC');
        $this->assertSame($party['faculty']->id, (int) $party['internship']->fresh()->faculty_id);

        Sanctum::actingAs($sectionFaculty);
        $row = collect($this->getJson('/api/v1/faculty/assigned-students')->assertOk()->json('data'))
            ->firstWhere('student_id', $party['student']->id);
        $this->assertNull($row, 'A section mapping alone does not list a Student advised by someone else.');
        $this->getJson($this->portfolioUrl($party['student']))->assertForbidden();

        Sanctum::actingAs($party['faculty']);
        $row = collect($this->getJson('/api/v1/faculty/assigned-students')->assertOk()->json('data'))
            ->firstWhere('student_id', $party['student']->id);
        $this->assertNotNull($row);
        $this->assertTrue($row['can_preview_portfolio']);

        // Deliberate reassignment to the section faculty moves roster and access.
        Internship::whereKey($party['internship']->id)->update(['faculty_id' => $sectionFaculty->id]);

        Sanctum::actingAs($sectionFaculty);
        $row = collect($this->getJson('/api/v1/faculty/assigned-students')->assertOk()->json('data'))
            ->firstWhere('student_id', $party['student']->id);
        $this->assertNotNull($row);
        $this->assertTrue($row['can_preview_portfolio']);
        $this->getJson($this->portfolioUrl($party['student']))->assertOk();

        Sanctum::actingAs($party['faculty']);
        $this->getJson($this->portfolioUrl($party['student']))->assertForbidden();
    }

    /** Reassignment is picked up dynamically from the internship record. */
    public function test_new_faculty_gains_access_after_reassignment_and_old_faculty_loses_it(): void
    {
        $party = $this->party();
        $newFaculty = $this->makeUser('faculty');

        Sanctum::actingAs($newFaculty);
        $this->getJson($this->portfolioUrl($party['student']))->assertForbidden();

        $party['internship']->update(['faculty_id' => $newFaculty->id]);

        $this->getJson($this->portfolioUrl($party['student']))->assertOk()
            ->assertJsonPath('user.id', $party['student']->id);
        Sanctum::actingAs($party['faculty']);
        $this->getJson($this->portfolioUrl($party['student']))->assertForbidden();
    }

    /** Student Portfolio behavior is unchanged by the Faculty preview. */
    public function test_student_portfolio_still_editable_and_previewable(): void
    {
        $party = $this->party();
        Sanctum::actingAs($party['student']);

        $this->postJson('/api/v1/student/portfolio', ['company_vision' => 'Student vision'])->assertOk();
        $this->getJson('/api/v1/student/portfolio')->assertOk()
            ->assertJsonPath('internship.portfolio.company_vision', 'Student vision');

        Sanctum::actingAs($party['faculty']);
        $this->getJson($this->portfolioUrl($party['student']))->assertOk()
            ->assertJsonPath('internship.portfolio.company_vision', 'Student vision');
        $this->postJson($this->portfolioUrl($party['student']), ['company_vision' => 'Faculty edit'])
            ->assertStatus(405);
        $this->postJson('/api/v1/student/portfolio', ['company_vision' => 'Faculty edit'])->assertForbidden();

        $this->assertSame('Student vision', StudentPortfolio::where('internship_id', $party['internship']->id)->value('company_vision'));
    }
}
