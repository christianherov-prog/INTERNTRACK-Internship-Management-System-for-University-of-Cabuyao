<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\Internship;
use App\Models\InternshipApplication;
use App\Models\JournalEntry;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\DocumentComplianceService;
use App\Services\OfficialFormDataService;
use App\Services\PortfolioDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Validates the controlled CCS demo dataset produced by
 * `php artisan interntrack:seed-ccs-demo` (see CCS-DEMO-01..27 in the
 * project brief). Runs against a freshly migrated + seeded test database
 * (interntrack_testing per phpunit.xml) — never the live dev database.
 */
class CcsDemoDatasetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Artisan::call('interntrack:seed-ccs-demo');
    }

    private const FINISHED = ['2300592' => 'Infor', '2300590' => 'Accenture Philippines', '2300595' => 'Microsoft Philippines'];

    private const ONGOING = ['2300600' => ['Oracle Philippines', 240.0], '2300609' => ['IBM Philippines', 184.0], '2300610' => ['DXC Technology Philippines', 296.0]];

    private const FRESH = ['2300500', '2300501', '2300502'];

    private function internshipFor(string $studentNumber): Internship
    {
        $profile = StudentProfile::where('student_number', $studentNumber)->firstOrFail();

        return Internship::where('student_id', $profile->user_id)->orderByDesc('id')->firstOrFail();
    }

    /** CCS-DEMO-01, CCS-DEMO-02 */
    public function test_exactly_ten_ccs_companies_with_valid_capacity(): void
    {
        $names = [
            'Accenture Philippines', 'IBM Philippines', 'Infor', 'Oracle Philippines', 'Microsoft Philippines',
            'DXC Technology Philippines', 'NTT DATA Philippines', 'Cognizant Philippines', 'Wipro Philippines',
            'Tata Consultancy Services (TCS) Philippines',
        ];

        $companies = Company::whereIn('company_name', $names)->get();
        $this->assertCount(10, $companies);

        foreach ($companies as $company) {
            $this->assertGreaterThan(0, $company->slots_available, "{$company->company_name} must have >0 slots");
            $this->assertLessThan(100, $company->slots_available, "{$company->company_name} must have <100 slots");
        }
    }

    /** CCS-DEMO-03..06 */
    public function test_exactly_nine_ccs_students_with_correct_status_breakdown(): void
    {
        $numbers = array_merge(array_keys(self::FINISHED), array_keys(self::ONGOING), self::FRESH);
        $this->assertCount(9, array_unique($numbers));

        foreach (self::FINISHED as $num => $company) {
            $this->assertSame('completed', $this->internshipFor($num)->status, "{$num} should be completed");
        }
        foreach (self::ONGOING as $num => [$company, $hours]) {
            $this->assertSame('active', $this->internshipFor($num)->status, "{$num} should be active/ongoing");
        }
        foreach (self::FRESH as $num) {
            $this->assertSame('pending_placement', $this->internshipFor($num)->status, "{$num} should be pending_placement");
        }
    }

    /** CCS-DEMO-07, CCS-DEMO-08, CCS-DEMO-09 */
    public function test_finished_students_have_500_hours_and_correct_company(): void
    {
        foreach (self::FINISHED as $num => $companyName) {
            $internship = $this->internshipFor($num)->load('company');
            $this->assertEquals(500.0, (float) $internship->total_hours_rendered, "{$num} must have 500 hours");
            $this->assertSame($companyName, $internship->company->company_name);
            $this->assertSame('completed', $internship->status);

            // Hours must come from real attendance, not just a cached field.
            $attendanceSum = (float) $internship->attendance()->where('status', 'validated')->sum('hours_rendered');
            $this->assertEquals(500.0, $attendanceSum, "{$num} attendance must sum to 500");
        }
    }

    /** CCS-DEMO-10 */
    public function test_christian_has_exactly_240_hours(): void
    {
        $internship = $this->internshipFor('2300600')->load('company');
        $this->assertEquals(240.0, (float) $internship->total_hours_rendered);
        $this->assertSame('Oracle Philippines', $internship->company->company_name);
        $this->assertSame('active', $internship->status);
    }

    /** CCS-DEMO-11 */
    public function test_ongoing_students_have_distinct_valid_totals_below_500(): void
    {
        $hours = [];
        foreach (self::ONGOING as $num => [$companyName, $expected]) {
            $internship = $this->internshipFor($num);
            $this->assertEquals($expected, (float) $internship->total_hours_rendered);
            $this->assertLessThan(500.0, (float) $internship->total_hours_rendered);
            $hours[] = (float) $internship->total_hours_rendered;
        }
        $this->assertCount(3, array_unique($hours), 'Ongoing students must have distinct hour totals');
    }

    /** CCS-DEMO-12 */
    public function test_fresh_students_have_zero_hours_and_no_company(): void
    {
        foreach (self::FRESH as $num) {
            $internship = $this->internshipFor($num);
            $this->assertEquals(0.0, (float) $internship->total_hours_rendered);
            $this->assertNull($internship->company_id);
            $this->assertNull($internship->supervisor_id);
        }
    }

    /** CCS-DEMO-13, CCS-DEMO-14 */
    public function test_finished_and_ongoing_students_have_supervisors_fresh_do_not(): void
    {
        foreach (array_merge(array_keys(self::FINISHED), array_keys(self::ONGOING)) as $num) {
            $this->assertNotNull($this->internshipFor($num)->supervisor_id, "{$num} must have a supervisor");
        }
        foreach (self::FRESH as $num) {
            $this->assertNull($this->internshipFor($num)->supervisor_id, "{$num} must NOT have a supervisor");
        }
    }

    /** CCS-DEMO-15 */
    public function test_finished_student_journals_are_all_approved(): void
    {
        foreach (array_keys(self::FINISHED) as $num) {
            $internship = $this->internshipFor($num);
            $journals = JournalEntry::where('internship_id', $internship->id)->get();
            $this->assertGreaterThan(0, $journals->count(), "{$num} must have journal entries");
            foreach ($journals as $journal) {
                $this->assertSame('approved', $journal->status, "{$num} week {$journal->week_number} must be approved");
                $this->assertNotNull($journal->faculty_reviewed_by);
            }
        }
    }

    /** CCS-DEMO-16 */
    public function test_finished_student_requirements_are_all_approved(): void
    {
        $svc = app(DocumentComplianceService::class);
        foreach (array_keys(self::FINISHED) as $num) {
            $profile = StudentProfile::where('student_number', $num)->firstOrFail();
            $user = User::findOrFail($profile->user_id);
            $result = $svc->evaluateStudent($user, $this->internshipFor($num));

            $this->assertGreaterThan(0, $result['required_count']);
            $this->assertSame($result['required_count'], $result['satisfied_count'], "{$num} must have 100% approved requirements");
            $this->assertSame(100, $result['compliance_pct']);
        }
    }

    /** CCS-DEMO-17, CCS-DEMO-18 (FO-24 is a 65-100 scale; 9/10 maps to 90/100 per criterion) */
    public function test_clarence_and_angel_fo24_criteria_are_90_of_100(): void
    {
        foreach (['2300592', '2300590'] as $num) {
            $internship = $this->internshipFor($num);
            $evaluation = Evaluation::where('internship_id', $internship->id)->where('form_type', 'FO-24')->firstOrFail();
            foreach (['c1', 'c2', 'c3', 'c4', 'c5', 'c6', 'c7', 'c8', 'c9', 'c10'] as $criterion) {
                $this->assertEquals(90, (float) $evaluation->responses[$criterion], "{$num} {$criterion} must be 90/100 (9/10)");
            }
            $this->assertSame('Very Good', $evaluation->rating);
        }
    }

    /** CCS-DEMO-19 */
    public function test_accepted_placement_consumes_company_slot(): void
    {
        $infor = Company::where('company_name', 'Infor')->firstOrFail();
        // Baseline capacity is 38; Clarence's completed internship should have
        // released the slot back to full (completion is a freeing transition).
        $this->assertSame(38, $infor->slots_available);

        $oracle = Company::where('company_name', 'Oracle Philippines')->firstOrFail();
        // Baseline capacity is 55; Christian's active internship still occupies one slot.
        $this->assertSame(54, $oracle->slots_available);
    }

    /** CCS-DEMO-20 (an ONGOING/active placement locks re-application; a
     *  completed one does not — 'completed' is not in PlacementEligibility's
     *  PLACED_STATUSES, which is real, intentional system behavior). */
    public function test_accepted_students_cannot_submit_additional_application(): void
    {
        $profile = StudentProfile::where('student_number', '2300600')->firstOrFail();
        $student = User::findOrFail($profile->user_id);
        $otherCompany = Company::where('company_name', 'Wipro Philippines')->firstOrFail();

        Sanctum::actingAs($student);
        $response = $this->postJson('/api/v1/student/applications', ['company_id' => $otherCompany->id]);
        $response->assertStatus(409);
    }

    /** CCS-DEMO-21 */
    public function test_fresh_students_remain_eligible_to_apply(): void
    {
        $profile = StudentProfile::where('student_number', '2300500')->firstOrFail();
        $student = User::findOrFail($profile->user_id);
        $company = Company::where('company_name', 'NTT DATA Philippines')->firstOrFail();

        Sanctum::actingAs($student);
        $response = $this->postJson('/api/v1/student/applications', ['company_id' => $company->id]);
        $response->assertSuccessful();

        $this->assertDatabaseHas('internship_applications', [
            'student_id' => $student->id,
            'company_id' => $company->id,
            'status' => 'pending',
        ]);
    }

    /** CCS-DEMO-22 */
    public function test_faculty_assignments_resolve_correctly(): void
    {
        $marvin = User::where('faculty_number', 'FAC-1001')->firstOrFail();
        $arcelito = User::where('faculty_number', 'COR-CCS-001')->firstOrFail();

        foreach (['2300592', '2300595', '2300600', '2300610', '2300500'] as $num) {
            $this->assertSame($marvin->id, $this->internshipFor($num)->faculty_id, "{$num} should be advised by Marvin");
        }
        foreach (['2300590', '2300609', '2300501', '2300502'] as $num) {
            $this->assertSame($arcelito->id, $this->internshipFor($num)->faculty_id, "{$num} should be advised by Arcelito");
        }
    }

    /** CCS-DEMO-23 */
    public function test_arcelito_can_review_his_advisee_journal_but_not_marvins_advisee(): void
    {
        $arcelito = User::where('faculty_number', 'COR-CCS-001')->firstOrFail();
        $this->assertSame('coordinator', $arcelito->role);

        $angelInternship = $this->internshipFor('2300590'); // Arcelito's advisee
        $angelJournal = JournalEntry::where('internship_id', $angelInternship->id)->orderBy('week_number')->firstOrFail();

        Sanctum::actingAs($arcelito);
        $this->patchJson("/api/v1/faculty/journals/{$angelJournal->id}/review", [
            'action' => 'approved',
            'feedback' => 'Reviewed by CCS coordinator acting in his faculty-advisor capacity.',
        ])->assertOk();

        // Marvin's advisee (Clarence) must NOT be reviewable by Arcelito —
        // coordinator role alone does not grant faculty authority; only an
        // explicit internships.faculty_id assignment does.
        $clarenceInternship = $this->internshipFor('2300592');
        $clarenceJournal = JournalEntry::where('internship_id', $clarenceInternship->id)->orderBy('week_number')->firstOrFail();

        Sanctum::actingAs($arcelito);
        $this->patchJson("/api/v1/faculty/journals/{$clarenceJournal->id}/review", [
            'action' => 'approved',
            'feedback' => 'Should not be allowed.',
        ])->assertStatus(404);
    }

    /** CCS-DEMO-24 */
    public function test_fo30_totals_equal_authoritative_attendance(): void
    {
        $internship = $this->internshipFor('2300592');
        $fo30 = app(OfficialFormDataService::class)->fo30($internship);
        $attendanceHours = (float) $internship->attendance()->where('status', 'validated')->sum('hours_rendered');

        $totalFromForm = collect($fo30['logs'] ?? [])->sum(fn ($row) => (float) ($row['hours_rendered'] ?? 0));
        $this->assertEquals($attendanceHours, $totalFromForm);
        $this->assertEquals(500.0, $attendanceHours);
    }

    /** CCS-DEMO-25 */
    public function test_fo31_matches_approved_journals(): void
    {
        $internship = $this->internshipFor('2300592')->load('student');
        $payload = app(PortfolioDataService::class)->payload($internship, $internship->student);
        $approvedCount = JournalEntry::where('internship_id', $internship->id)->where('status', 'approved')->count();

        $this->assertCount($approvedCount, $payload['internship']['journals']);
    }

    /** CCS-DEMO-26 */
    public function test_reports_match_source_records(): void
    {
        $svc = app(DocumentComplianceService::class);
        foreach (array_keys(self::FINISHED) as $num) {
            $profile = StudentProfile::where('student_number', $num)->firstOrFail();
            $user = User::findOrFail($profile->user_id);
            $internship = $this->internshipFor($num);

            $summary = $svc->summaryForStudent($user, $internship);
            $full = $svc->evaluateStudent($user, $internship);
            $this->assertSame($full['compliance_pct'], $summary['pct'], "{$num} summary/full compliance must match");
            $this->assertEquals(500.0, (float) $internship->total_hours_rendered);
        }
    }

    /** CCS-DEMO-27 */
    public function test_finished_students_appear_in_absorption_tracking_with_valid_states(): void
    {
        $expected = ['2300592' => 'absorbed', '2300590' => 'pending', '2300595' => 'not_hired'];
        $validStates = ['pending', 'absorbed', 'not_hired'];

        foreach ($expected as $num => $status) {
            $internship = $this->internshipFor($num);
            $this->assertSame('completed', $internship->status);
            $this->assertContains($internship->absorption_status, $validStates);
            $this->assertSame($status, $internship->absorption_status, "{$num} should be {$status}");
        }

        // All three states are represented (variety), and only real supported states are used.
        $statuses = collect($expected)->values()->unique();
        $this->assertCount(3, $statuses);

        $completedInternshipIds = Internship::where('status', 'completed')
            ->whereIn('student_id', StudentProfile::whereIn('student_number', array_keys($expected))->pluck('user_id'))
            ->pluck('id');
        $this->assertCount(3, $completedInternshipIds);
    }

    /** Re-running the seeder must not create duplicate rows anywhere. */
    public function test_seeder_is_idempotent(): void
    {
        Artisan::call('interntrack:seed-ccs-demo');

        $names = [
            'Accenture Philippines', 'IBM Philippines', 'Infor', 'Oracle Philippines', 'Microsoft Philippines',
            'DXC Technology Philippines', 'NTT DATA Philippines', 'Cognizant Philippines', 'Wipro Philippines',
            'Tata Consultancy Services (TCS) Philippines',
        ];
        $this->assertSame(10, Company::whereIn('company_name', $names)->count());

        $numbers = array_merge(array_keys(self::FINISHED), array_keys(self::ONGOING), self::FRESH);
        foreach ($numbers as $num) {
            $this->assertSame(1, StudentProfile::where('student_number', $num)->count(), "{$num} must not be duplicated");
        }

        // Slots must not have moved further on a second, no-op run.
        $infor = Company::where('company_name', 'Infor')->firstOrFail();
        $this->assertSame(38, $infor->slots_available);
        $oracle = Company::where('company_name', 'Oracle Philippines')->firstOrFail();
        $this->assertSame(54, $oracle->slots_available);

        foreach (self::FINISHED as $num => $companyName) {
            $this->assertEquals(500.0, (float) $this->internshipFor($num)->total_hours_rendered);
        }
        foreach (self::ONGOING as $num => [$companyName, $expected]) {
            $this->assertEquals($expected, (float) $this->internshipFor($num)->total_hours_rendered);
        }

        $angelInternship = $this->internshipFor('2300590');
        $this->assertSame(1, InternshipApplication::where('student_id', $angelInternship->student_id)->count());
    }

    /** CCS-CLR-01, CCS-CLR-02, CCS-CLR-03, CCS-CLR-04 */
    public function test_clarence_authoritative_placement_is_infor_with_no_stale_company_applications(): void
    {
        $internship = $this->internshipFor('2300592')->load('company');
        $this->assertSame('Infor', $internship->company->company_name);

        $profile = StudentProfile::where('student_number', '2300592')->firstOrFail();
        $applications = InternshipApplication::where('student_id', $profile->user_id)->get();

        // Exactly one application, and it is the authoritative Infor one —
        // no stale Yakult / TechCorp PH / Asia Brewery rows shadow it.
        $this->assertCount(1, $applications, 'Clarence must have exactly one application record (Infor).');
        $staleNames = ['Yakult', 'TechCorp PH', 'Asia Brewery'];
        foreach ($applications as $application) {
            $companyName = $application->company?->company_name ?? Company::withTrashed()->find($application->company_id)?->company_name;
            $this->assertNotContains($companyName, $staleNames, "Found a stale application to {$companyName}");
        }
    }

    /** CCS-CLR-05, CCS-CLR-06, CCS-CLR-07 */
    public function test_clarence_dashboard_portfolio_and_reports_resolve_to_infor(): void
    {
        $profile = StudentProfile::where('student_number', '2300592')->firstOrFail();
        $user = User::findOrFail($profile->user_id);
        $internship = $this->internshipFor('2300592');

        Sanctum::actingAs($user);
        $dashboard = $this->getJson('/api/v1/student/dashboard')->assertOk();
        $this->assertSame('Infor', $dashboard->json('internship.company_name'));

        $portfolioPayload = app(PortfolioDataService::class)->payload($internship->load('student'), $internship->student);
        $this->assertSame('Infor', $portfolioPayload['internship']['company']['company_name'] ?? $portfolioPayload['identity']['company_name'] ?? null);

        // Reports (compliance/analytics) are keyed off the same authoritative internship->company relation.
        $this->assertSame('Infor', $internship->fresh()->company->company_name);
    }
}
