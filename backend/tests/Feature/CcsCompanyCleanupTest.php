<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\HteRequest;
use App\Models\Internship;
use App\Models\InternshipApplication;
use App\Models\StudentProfile;
use App\Models\SupervisorProfile;
use App\Models\User;
use App\Services\AbsorptionService;
use App\Services\CcsCompanyCleanupService;
use App\Support\CcsCompanyDirectory;
use App\Support\CompanyNameNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * COMPANY-01..10 and CLR-01..08: the `interntrack:cleanup-ccs-companies`
 * merge/cleanup, run against a freshly seeded controlled CCS dataset into
 * which the legacy (pre-cleanup) state is injected:
 *
 *  - three "Accenture PH" duplicate rows carrying real dependent records,
 *  - the retired TechCorp PH / Asia Brewery / Yakult demo companies,
 *  - a stale approved Yakult application and an orphan supervisor request
 *    for the Student who is authoritatively placed at Infor.
 *
 * Duplicate rows are written with a distinct normalized_name, exactly like
 * rows that pre-date the unique identity index, so the fixture can exist
 * next to that index. Runs against interntrack_testing — never the dev DB.
 */
class CcsCompanyCleanupTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<int> */
    private array $duplicateIds = [];

    private int $techCorpId;

    private int $asiaBreweryId;

    private int $yakultId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Artisan::call('interntrack:seed-ccs-demo');
        $this->injectLegacyState();
    }

    // ── Fixture ────────────────────────────────────────────────────────

    private function studentId(string $studentNumber): int
    {
        return StudentProfile::where('student_number', $studentNumber)->firstOrFail()->user_id;
    }

    private function legacyCompany(string $name, string $legacyKey, int $slots = 15): int
    {
        return DB::table('companies')->insertGetId([
            'company_name' => $name,
            'normalized_name' => $legacyKey,
            'organization_type' => 'Private',
            'moa_status' => 'active',
            'is_active' => 1,
            'slots_available' => $slots,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function injectLegacyState(): void
    {
        foreach (['Accenture PH', 'ACCENTURE  PH', 'accenture ph'] as $i => $name) {
            $this->duplicateIds[] = $this->legacyCompany($name, "legacy-accenture-ph-{$i}");
        }
        $this->techCorpId = $this->legacyCompany('TechCorp PH', 'techcorp ph', 10);
        $this->asiaBreweryId = $this->legacyCompany('Asia Brewery', 'asia brewery', 30);
        $this->yakultId = $this->legacyCompany('Yakult', 'yakult', 10);

        $canonicalId = Company::where('company_name', 'Accenture Philippines')->value('id');
        $angel = $this->studentId('2300590');   // already applied to the canonical row
        $fresh1 = $this->studentId('2300500');
        $fresh2 = $this->studentId('2300501');
        $clarence = $this->studentId('2300592');

        // Angel also has applications on two duplicates (collides with the canonical
        // (student, company) unique key on merge); a fresh student has a unique one.
        InternshipApplication::create(['student_id' => $angel, 'company_id' => $this->duplicateIds[0], 'status' => 'approved']);
        InternshipApplication::create(['student_id' => $angel, 'company_id' => $this->duplicateIds[1], 'status' => 'approved']);
        InternshipApplication::create(['student_id' => $fresh1, 'company_id' => $this->duplicateIds[2], 'status' => 'pending']);

        // A real occupying internship on a duplicate row (consumes one slot after the merge).
        DB::table('internships')->where('student_id', $fresh2)->update([
            'company_id' => $this->duplicateIds[1],
            'status' => 'active',
        ]);

        // Supervisor + supervisor request that point at duplicates.
        SupervisorProfile::query()->whereNotNull('company_id')->first()?->update(['company_id' => $this->duplicateIds[0]]);
        DB::table('supervisor_invite_tokens')->insert([
            'internship_id' => Internship::where('student_id', $fresh1)->value('id'),
            'student_id' => $fresh1,
            'token' => str_repeat('a', 64),
            'status' => 'expired',
            'company_id' => $this->duplicateIds[2],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Stale demo state on Clarence: an approved Yakult application, plus an
        // old supervisor request for an internship that no longer exists.
        InternshipApplication::create(['student_id' => $clarence, 'company_id' => $this->yakultId, 'status' => 'approved']);
        DB::table('supervisor_invite_tokens')->insert([
            'internship_id' => 987654,
            'student_id' => $clarence,
            'token' => str_repeat('b', 64),
            'status' => 'approved',
            'company_id' => $canonicalId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('supervisor_invite_tokens')->insert([
            'internship_id' => Internship::where('student_id', $fresh1)->value('id'),
            'student_id' => $fresh1,
            'token' => str_repeat('c', 64),
            'status' => 'declined',
            'company_id' => $this->asiaBreweryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function cleanup(): int
    {
        return Artisan::call('interntrack:cleanup-ccs-companies');
    }

    /** @return list<int> */
    private function accentureFamilyIds(): array
    {
        return Company::query()->get()
            ->filter(fn (Company $c) => CompanyNameNormalizer::key($c->company_name) === CompanyNameNormalizer::key('Accenture Philippines'))
            ->pluck('id')->all();
    }

    // ── COMPANY-01/02/03: retired demo companies ───────────────────────

    public function test_retired_demo_companies_are_removed_and_absent_from_eligible_list(): void
    {
        $this->assertSame(0, $this->cleanup());

        foreach (['TechCorp PH', 'Asia Brewery', 'Yakult'] as $name) {
            $this->assertSame(0, Company::withTrashed()->where('company_name', $name)->count(), "{$name} must be gone");
        }

        Sanctum::actingAs(User::findOrFail($this->studentId('2300501')));
        $names = collect($this->getJson('/api/v1/student/companies')->assertOk()->json('companies'))->pluck('company_name');
        foreach (['TechCorp PH', 'Asia Brewery', 'Yakult'] as $name) {
            $this->assertNotContains($name, $names->all());
        }
    }

    // ── COMPANY-04/05: exactly one canonical Accenture ─────────────────

    public function test_exactly_one_canonical_accenture_and_no_accenture_ph_variant_remains(): void
    {
        $this->assertCount(1 + count($this->duplicateIds), $this->accentureFamilyIds());

        $this->cleanup();

        $this->assertSame(1, Company::where('company_name', 'Accenture Philippines')->count());
        $this->assertCount(1, $this->accentureFamilyIds());
        $this->assertSame(0, Company::withTrashed()->whereIn('id', $this->duplicateIds)->count());
        $this->assertSame(0, Company::whereRaw("LOWER(TRIM(company_name)) LIKE 'accenture ph'")->count());
    }

    // ── COMPANY-06: the controlled list is the 10 canonical companies ──

    public function test_controlled_ccs_eligible_list_is_the_ten_canonical_companies(): void
    {
        $this->cleanup();

        Sanctum::actingAs(User::findOrFail($this->studentId('2300502')));
        $names = collect($this->getJson('/api/v1/student/companies')->assertOk()->json('companies'))->pluck('company_name');

        $this->assertCount(10, $names, 'Count must come from real eligible rows.');
        $this->assertSame(collect(CcsCompanyDirectory::names())->sort()->values()->all(), $names->sort()->values()->all());
        $this->assertSame($names->count(), $names->unique()->count(), 'No company may appear twice.');
    }

    // ── COMPANY-07: duplicate HTE creation/request is refused ──────────

    public function test_duplicate_hte_request_and_director_create_do_not_create_another_company(): void
    {
        $this->cleanup();
        $before = Company::count();

        $student = User::findOrFail($this->studentId('2300502'));
        Sanctum::actingAs($student);
        foreach (['Accenture Philippines', 'Accenture PH', '  accenture   PHILIPPINES  ', 'ACCENTURE PH'] as $variant) {
            $this->postJson('/api/v1/student/hte-requests', [
                'company_name' => $variant,
                'address' => 'BGC, Taguig',
                'organization_type' => 'Private',
                'contact_person' => 'HR',
                'contact_email' => 'hr@accenture.example',
                'contact_number' => '09171234567',
            ])->assertStatus(422)->assertJsonPath('company.company_name', 'Accenture Philippines');
        }
        $this->assertSame(0, HteRequest::count());

        Sanctum::actingAs(User::where('role', 'director')->firstOrFail());
        foreach (['Accenture PH', 'accenture   philippines'] as $variant) {
            $this->postJson('/api/v1/director/companies', ['company_name' => $variant, 'moa_status' => 'active'])
                ->assertStatus(422);
        }

        // Renaming another company onto an existing identity is refused too.
        $infor = Company::where('company_name', 'Infor')->firstOrFail();
        $this->putJson("/api/v1/director/companies/{$infor->id}", ['company_name' => 'Accenture PH'])->assertStatus(422);

        $this->assertSame($before, Company::count());
    }

    public function test_unrelated_company_names_are_not_treated_as_duplicates(): void
    {
        $this->cleanup();

        Sanctum::actingAs(User::where('role', 'director')->firstOrFail());
        $this->postJson('/api/v1/director/companies', ['company_name' => 'Accenture Solutions Corp', 'moa_status' => 'active'])
            ->assertCreated();
        $this->assertSame(1, Company::where('company_name', 'Accenture Philippines')->count());
    }

    public function test_database_unique_index_rejects_an_equivalent_live_company(): void
    {
        $this->cleanup();

        $this->expectException(QueryException::class);
        Company::create(['company_name' => 'ACCENTURE   Philippines', 'moa_status' => 'active', 'is_active' => true, 'slots_available' => 1]);
    }

    public function test_database_unique_index_allows_reusing_a_soft_deleted_name(): void
    {
        $this->cleanup();

        Company::where('company_name', 'Wipro Philippines')->firstOrFail()->delete();
        $again = Company::create(['company_name' => 'Wipro Philippines', 'moa_status' => 'active', 'is_active' => true, 'slots_available' => 1]);

        $this->assertNotNull($again->id);
    }

    // ── COMPANY-08: dependents migrated to the canonical company ───────

    public function test_dependent_records_are_repointed_to_canonical_accenture(): void
    {
        $canonicalId = Company::where('company_name', 'Accenture Philippines')->value('id');
        $angel = $this->studentId('2300590');
        $fresh1 = $this->studentId('2300500');
        $fresh2 = $this->studentId('2300501');

        $this->cleanup();

        // Fresh student's only application was on a duplicate → now on canonical, still pending.
        $moved = InternshipApplication::where('student_id', $fresh1)->get();
        $this->assertCount(1, $moved);
        $this->assertSame($canonicalId, (int) $moved->first()->company_id);
        $this->assertSame('pending', $moved->first()->status);

        // Angel keeps exactly ONE application to Accenture (unique key), approved.
        $angelApps = InternshipApplication::where('student_id', $angel)->get();
        $this->assertCount(1, $angelApps);
        $this->assertSame($canonicalId, (int) $angelApps->first()->company_id);
        $this->assertSame('approved', $angelApps->first()->status);

        // Internship, supervisor, supervisor request were repointed.
        $this->assertSame($canonicalId, (int) Internship::where('student_id', $fresh2)->value('company_id'));
        $this->assertSame(0, SupervisorProfile::whereIn('company_id', $this->duplicateIds)->count());
        $this->assertGreaterThan(0, SupervisorProfile::where('company_id', $canonicalId)->count());
        $this->assertSame($canonicalId, (int) DB::table('supervisor_invite_tokens')->where('token', str_repeat('a', 64))->value('company_id'));
    }

    public function test_slots_are_recalculated_from_authoritative_placements_after_merge(): void
    {
        $this->cleanup();

        $accenture = Company::where('company_name', 'Accenture Philippines')->firstOrFail();
        // 1 active internship on a merged duplicate; Angel's completed internship frees its slot.
        $this->assertSame(1, Internship::where('company_id', $accenture->id)->whereIn('status', ['active', 'ongoing', 'placed', 'for_evaluation', 'suspended'])->count());
        $this->assertSame(72 - 1, (int) $accenture->slots_available);

        foreach (app(CcsCompanyCleanupService::class)->slotReport() as $row) {
            $this->assertSame($row['expected_capacity'], $row['capacity'], "{$row['company']} capacity = available + used must equal its baseline");
            $this->assertGreaterThanOrEqual(0, $row['available']);
        }
    }

    // ── COMPANY-09: no orphans ─────────────────────────────────────────

    public function test_no_orphan_company_references_remain(): void
    {
        $this->cleanup();

        $orphans = array_filter(app(CcsCompanyCleanupService::class)->orphanCompanyReferences());
        $this->assertSame([], $orphans);

        // Orphan supervisor request (internship no longer exists) is gone.
        $this->assertSame(0, DB::table('supervisor_invite_tokens')->where('token', str_repeat('b', 64))->count());
        // Request that pointed at a retired company keeps its row but loses the link.
        $this->assertNull(DB::table('supervisor_invite_tokens')->where('token', str_repeat('c', 64))->value('company_id'));
    }

    // ── COMPANY-10: idempotent / dry-run safe ──────────────────────────

    public function test_cleanup_is_idempotent(): void
    {
        $this->assertSame(0, $this->cleanup());
        $snapshot = [
            Company::pluck('company_name', 'id')->all(),
            InternshipApplication::orderBy('id')->get(['student_id', 'company_id', 'status'])->toArray(),
            Company::orderBy('id')->pluck('slots_available', 'id')->all(),
        ];

        $second = app(CcsCompanyCleanupService::class)->run();
        $this->assertSame(0, Artisan::call('interntrack:cleanup-ccs-companies'));

        $this->assertSame([], $second['applications_deleted']);
        $this->assertSame([], $second['orphans_deleted']);
        $this->assertSame([], $second['blocked']);
        $this->assertSame($snapshot, [
            Company::pluck('company_name', 'id')->all(),
            InternshipApplication::orderBy('id')->get(['student_id', 'company_id', 'status'])->toArray(),
            Company::orderBy('id')->pluck('slots_available', 'id')->all(),
        ]);
    }

    public function test_dry_run_reports_but_changes_nothing(): void
    {
        $before = Company::count();

        $report = app(CcsCompanyCleanupService::class)->run(true);

        $this->assertTrue($report['dry_run']);
        $this->assertNotEmpty($report['companies']);
        $this->assertSame($before, Company::count());
        $this->assertSame(1 + count($this->duplicateIds), count($this->accentureFamilyIds()));
        $this->assertSame(1, Company::where('company_name', 'Yakult')->count());
    }

    public function test_retired_company_that_still_owns_an_internship_is_blocked_not_deleted(): void
    {
        DB::table('internships')->where('student_id', $this->studentId('2300502'))->update(['company_id' => $this->yakultId, 'status' => 'active']);

        $exit = $this->cleanup();

        $this->assertSame(1, $exit, 'The command must fail loudly when a retired company cannot be removed safely.');
        $report = app(CcsCompanyCleanupService::class)->run();
        $this->assertCount(1, $report['blocked']);
        $this->assertSame($this->yakultId, $report['blocked'][0]['id']);
        $this->assertSame(1, Company::where('company_name', 'Yakult')->count(), 'A company with a real internship must never be deleted blindly.');
        $this->assertSame(1, Internship::where('company_id', $this->yakultId)->count());
    }

    // ── CLR-01..08: Clarence (2300592) resolves only to Infor ──────────

    public function test_clarence_resolves_only_to_infor_across_modules_after_cleanup(): void
    {
        $this->cleanup();

        $userId = $this->studentId('2300592');
        $user = User::findOrFail($userId);
        $inforId = Company::where('company_name', 'Infor')->value('id');

        // CLR-01: authoritative placement.
        $internship = Internship::where('student_id', $userId)->orderByDesc('id')->firstOrFail();
        $this->assertSame($inforId, (int) $internship->company_id);

        // CLR-02/03/04: no Yakult / TechCorp / Asia Brewery relationship remains.
        $applications = InternshipApplication::where('student_id', $userId)->with('company')->get();
        $this->assertCount(1, $applications);
        $this->assertSame('Infor', $applications->first()->company->company_name);
        $this->assertSame(0, DB::table('supervisor_invite_tokens')->where('student_id', $userId)->whereNotNull('company_id')->where('company_id', '!=', $inforId)->count());
        $this->assertSame(0, Company::whereIn('company_name', CcsCompanyDirectory::OBSOLETE)->count());

        Sanctum::actingAs($user);

        // CLR-05: dashboard.
        $this->assertSame('Infor', $this->getJson('/api/v1/student/dashboard')->assertOk()->json('internship.company_name'));

        // My Applications / Placement Hub view of the same student.
        $apps = $this->getJson('/api/v1/student/applications')->assertOk();
        $this->assertSame(['Infor'], collect($apps->json('applications'))->pluck('company_name')->unique()->values()->all());

        // CLR-06: portfolio snapshot.
        $this->assertSame('Infor', DB::table('student_portfolios')->where('internship_id', $internship->id)->value('company_name'));

        // CLR-07: coordinator report rows for this student show Infor only.
        Sanctum::actingAs(User::where('faculty_number', 'COR-CCS-001')->firstOrFail());
        $report = $this->getJson('/api/v1/coordinator/reports/student-summary')->assertOk()->getContent();
        foreach (['Yakult', 'TechCorp PH', 'Asia Brewery', 'Accenture PH'] as $stale) {
            $this->assertStringNotContainsString($stale, $report);
        }

        // CLR-08: absorption / completion analytics attribute Clarence to Infor.
        $byCompany = collect(AbsorptionService::analytics()['by_company'])->pluck('company')->all();
        $this->assertContains('Infor', $byCompany);
        foreach (['Yakult', 'TechCorp PH', 'Asia Brewery', 'Accenture PH'] as $stale) {
            $this->assertNotContains($stale, $byCompany);
        }
        $this->assertSame('Infor', AbsorptionService::completedList()->firstWhere('student_id', $userId)?->company?->company_name);
    }
}
