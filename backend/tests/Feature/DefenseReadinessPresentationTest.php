<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\FacultyProfile;
use App\Services\MisdIntegrationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

/**
 * Presentation-facing data: truthful iEnroll wording (no development labels,
 * no claim of a live external link), the controlled ADMIN-MISD-001 identity
 * coming from data, Asia/Manila audit times, and date-only MOA fields.
 */
class DefenseReadinessPresentationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_dashboard_describes_the_local_directory_without_development_labels(): void
    {
        Sanctum::actingAs($this->makeUser('admin', 'ADMIN-MISD-001'));

        $response = $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $status = $response->json('misd_status');

        $this->assertSame('local', $status['mode']);
        $this->assertSame('Local Directory', $status['mode_label']);
        $this->assertNull($status['base_url']);
        $this->assertTrue($status['reachable']);
        // Truthful: explicitly not an external connection.
        $this->assertStringContainsString('No external iEnroll connection', $status['note']);

        // Displayed values only: the internal `use_mock` key is kept for API
        // compatibility and is never rendered.
        $payload = json_encode(array_values($status));
        foreach (['Mock', 'mock', 'MockMisdRepository', 'in-process://', 'sample', 'Live'] as $term) {
            $this->assertStringNotContainsString($term, $payload, "misd_status exposes '{$term}'");
        }
    }

    public function test_provisioning_log_uses_neutral_directory_wording(): void
    {
        $admin = $this->makeUser('admin', 'ADMIN-MISD-001');
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'misd.directory_fetched',
            'new_values' => ['type' => 'students', 'count' => 9],
        ]);
        Sanctum::actingAs($admin);

        $entries = implode("\n", $this->getJson('/api/v1/admin/provisioning-log')->assertOk()->json('data'));
        $this->assertStringContainsString('from the iEnroll directory', $entries);
        $this->assertStringNotContainsStringIgnoringCase('mock', $entries);
    }

    public function test_admin_identity_comes_from_the_directory_record_and_survives_login_sync(): void
    {
        $directory = app(MisdIntegrationService::class)->fetchFaculty('ADMIN-MISD-001');
        $this->assertSame('Alon Isagani', $directory['first_name']);
        $this->assertSame('Dimaculangan', $directory['last_name']);

        // An account still holding the old generic name is corrected by login sync.
        $admin = $this->makeUser('admin', 'ADMIN-MISD-001');
        FacultyProfile::where('user_id', $admin->id)->update(['first_name' => 'UC', 'last_name' => 'Admin']);
        app(MisdIntegrationService::class)->syncFaculty($admin->fresh('facultyProfile'));

        Sanctum::actingAs($admin->fresh());
        $user = $this->getJson('/api/v1/auth/user')->assertOk()->json('user');
        $this->assertSame('Alon Isagani', $user['first_name']);
        $this->assertSame('Dimaculangan', $user['last_name']);
        $this->assertStringContainsString('Alon Isagani', $user['name']);
        $this->assertStringContainsString('Dimaculangan', $user['name']);
    }

    public function test_dashboard_activity_uses_asia_manila_time_and_calendar_day(): void
    {
        $admin = $this->makeUser('admin', 'ADMIN-MISD-001');
        // 02:00 UTC on Sep 26 = 10:00 Manila on Sep 26.
        Carbon::setTestNow(Carbon::parse('2026-09-26 02:00:00', 'UTC'));

        // 17:30 UTC Sep 25 = 01:30 Manila Sep 26 → today in Manila.
        AuditLog::create(['user_id' => $admin->id, 'action' => 'login', 'created_at' => Carbon::parse('2026-09-25 17:30:00', 'UTC')]);
        // 15:00 UTC Sep 25 = 23:00 Manila Sep 25 → yesterday in Manila.
        AuditLog::create(['user_id' => $admin->id, 'action' => 'logout', 'created_at' => Carbon::parse('2026-09-25 15:00:00', 'UTC')]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/v1/admin/dashboard')->assertOk();

        $this->assertSame(1, $response->json('activities_today'));
        $latest = collect($response->json('recent_activity'))->firstWhere('action', 'login');
        $this->assertSame('Sep 26, 2026 1:30 AM', $latest['created_at_display']);
        $this->assertStringEndsWith('+08:00', $latest['created_at']);
    }

    // DATE-UI-01 (API side): date-only MOA fields serialize without a time/zone.
    public function test_moa_dates_serialize_as_plain_dates(): void
    {
        $company = $this->makeEligibleCompany(['moa_start_date' => '2026-01-05', 'moa_expiry_date' => '2028-01-05']);

        $this->assertSame('2028-01-05', $company->fresh()->toArray()['moa_expiry_date']);
        $this->assertSame('2026-01-05', $company->fresh()->toArray()['moa_start_date']);
        // Date arithmetic still works on the Carbon cast.
        $this->assertTrue($company->fresh()->moa_expiry_date->isFuture());

        Sanctum::actingAs($this->makeUser('director', 'DIR-TEST-1'));
        $row = collect($this->getJson('/api/v1/director/companies')->assertOk()->json('data'))
            ->firstWhere('id', $company->id);
        $this->assertSame('2028-01-05', $row['moa_expiry_date']);
        $this->assertDoesNotMatchRegularExpression('/T\d{2}:\d{2}/', (string) $row['moa_expiry_date']);
    }

    public function test_report_generated_at_is_an_offset_timestamp(): void
    {
        Sanctum::actingAs($this->makeUser('faculty', 'FAC-TEST-1'));

        $generated = $this->getJson('/api/v1/faculty/reports/student-summary')->assertOk()->json('generated_at');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', (string) $generated);
    }
}
