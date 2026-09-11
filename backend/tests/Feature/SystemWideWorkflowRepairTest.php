<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\SupervisorProfile;
use App\Models\User;
use App\Support\LoginUsername;
use App\Support\OrganizationTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

/**
 * System-wide workflow coverage for the Sep 2026 repair pass.
 */
class SystemWideWorkflowRepairTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    public function test_login_username_normalizes_and_rejects_duplicates(): void
    {
        User::factory()->create([
            'role' => 'supervisor',
            'login_username' => 'adrian.reyes',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->assertSame('adrian.reyes', LoginUsername::normalize('Adrian.Reyes'));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        LoginUsername::validateOrFail('adrian.reyes', ignoreUserId: null);
    }

    public function test_supervisor_can_login_with_login_username(): void
    {
        $user = User::factory()->create([
            'role' => 'supervisor',
            'faculty_number' => 'SUP-9001',
            'login_username' => 'hte.supervisor1',
            'email' => 'hte.supervisor1@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        SupervisorProfile::create([
            'user_id' => $user->id,
            'first_name' => 'Hte',
            'last_name' => 'Supervisor',
            'email' => $user->email,
            'contact_number' => '09171234567',
            'position' => 'Supervisor',
        ]);

        $byUsername = $this->postJson('/api/v1/auth/login', [
            'username' => 'hte.supervisor1',
            'password' => 'password123',
        ])->assertOk();

        $payload = $byUsername->json('user');
        $this->assertTrue(
            ($payload['login_username'] ?? null) === 'hte.supervisor1'
            || ($payload['username'] ?? null) === 'hte.supervisor1'
            || ($payload['faculty_number'] ?? null) === 'SUP-9001'
        );

        $this->postJson('/api/v1/auth/login', [
            'username' => 'SUP-9001',
            'password' => 'password123',
        ])->assertOk();
    }

    public function test_journal_date_ranges_cannot_overlap(): void
    {
        $student = $this->makeStudentWithSection();
        $faculty = $this->makeUser('faculty', 'FAC-1001');
        $this->mapFacultyForSection($faculty);
        $supervisor = $this->makeUser('supervisor', 'SUP-9002');
        SupervisorProfile::create([
            'user_id' => $supervisor->id,
            'first_name' => 'Overlap',
            'last_name' => 'Tester',
            'email' => $supervisor->email,
            'position' => 'Industry Supervisor',
        ]);
        $company = $this->makeEligibleCompany();
        $coordinator = $this->makeUser('coordinator', 'COR-CCS-001');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        JournalEntry::create([
            'internship_id' => $internship->id,
            'entry_number' => 1,
            'week_number' => 1,
            'date' => '2026-08-01',
            'end_date' => '2026-08-05',
            'activities_summary' => 'Week 1',
            'challenges' => 'x',
            'learnings' => 'y',
            'status' => 'submitted',
        ]);

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/v1/student/logbook', [
                'week_number' => 2,
                'date' => '2026-08-04',
                'end_date' => '2026-08-08',
                'activities_summary' => 'Overlap',
                'challenges' => 'x',
                'learnings' => 'y',
            ])
            ->assertStatus(422);
    }

    public function test_organization_types_sanitize(): void
    {
        $this->assertSame('hospital', OrganizationTypes::sanitize('Hospital'));
        $this->assertSame('Software Consulting Firm', OrganizationTypes::sanitize('Software Consulting Firm'));
        $this->assertNull(OrganizationTypes::sanitize('other'));
        $this->assertNull(OrganizationTypes::sanitize('Specify Organization Type'));
        $this->assertNull(OrganizationTypes::sanitize('specify'));
        $this->assertSame('Hospital', OrganizationTypes::label('hospital'));
        $this->assertSame('Needs Specification', OrganizationTypes::label('other'));
        $this->assertSame('Software Consulting Firm', OrganizationTypes::label('Software Consulting Firm'));

        $custom = OrganizationTypes::resolveForStorage('Accounting Firm');
        $this->assertTrue($custom['ok']);
        $this->assertSame('Accounting Firm', $custom['value']);

        $other = OrganizationTypes::resolveForStorage('Other');
        $this->assertFalse($other['ok']);

        $specify = OrganizationTypes::resolveForStorage('specify');
        $this->assertFalse($specify['ok']);
    }

    public function test_supervisor_journal_generate_route_removed(): void
    {
        $supervisor = User::factory()->create([
            'role' => 'supervisor',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/v1/supervisor/journal/generate')
            ->assertNotFound();
    }
}
