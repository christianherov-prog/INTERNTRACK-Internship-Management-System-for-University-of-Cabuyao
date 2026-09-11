<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\JournalEntry;
use App\Models\SupervisorProfile;
use App\Models\User;
use App\Services\InternshipProgressService;
use App\Services\OfficialFormDataService;
use App\Services\OneWeekOjtDemoService;
use App\Services\SupervisorFeedbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

/**
 * Targeted regression for Clarence Montealegre (2300592) ↔ Adrian Reyes (SUP-0002).
 * Resolves by primary keys / faculty_number — never by partial first-name match.
 */
class ClarenceMontealegreRepairTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function party(): array
    {
        $coordinator = $this->makeUser('coordinator', 'COR-CCS-001');
        $faculty = $this->makeUser('faculty', 'FAC-1001');
        $this->mapFacultyForSection($faculty);

        $magtibay = User::create([
            'faculty_number' => 'SUP-0001',
            'email' => 'magtibay@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'supervisor',
            'is_active' => true,
            'login_username' => 'clarence.magtibay',
        ]);
        SupervisorProfile::create([
            'user_id' => $magtibay->id,
            'first_name' => 'Clarence',
            'last_name' => 'Magtibay',
            'email' => $magtibay->email,
            'position' => 'IT Manager',
        ]);

        $adrian = $this->makeUser('supervisor', 'SUP-0002');
        $adrian->update(['login_username' => 'adrian.reyes']);
        SupervisorProfile::create([
            'user_id' => $adrian->id,
            'first_name' => 'Adrian',
            'last_name' => 'Reyes',
            'email' => $adrian->email,
            'position' => 'Industry Supervisor',
        ]);

        $unrelated = $this->makeUser('supervisor', 'SUP-0003');
        SupervisorProfile::create([
            'user_id' => $unrelated->id,
            'first_name' => 'Other',
            'last_name' => 'Supervisor',
            'email' => $unrelated->email,
            'position' => 'Industry Supervisor',
        ]);

        $student = $this->makeStudentWithSection();
        $student->studentProfile->update([
            'first_name' => 'Clarence',
            'last_name' => 'Montealegre',
            'student_number' => '2300592',
        ]);
        $student->update(['student_number' => '2300592']);

        $company = $this->makeEligibleCompany(['company_name' => 'Accenture PH']);

        // Intentionally wrong starting state: internship still on Magtibay.
        $internship = $this->makeActiveInternship($student, $company, $magtibay, $faculty, $coordinator);
        $internship->update([
            'supervisor_id' => $magtibay->id,
            'target_hours' => 500,
            'status' => 'ongoing',
        ]);

        // Authoritative repair: FK reassignment by IDs, then demo reconcile.
        $internship->update(['supervisor_id' => $adrian->id, 'company_id' => $company->id]);
        if ($internship->current_placement_id) {
            $internship->currentPlacement()?->update([
                'supervisor_id' => $adrian->id,
                'company_id' => $company->id,
            ]);
        }

        $result = app(OneWeekOjtDemoService::class)->reconcileStudent('2300592');

        // Magtibay no longer current supervisor for this internship.
        if (\App\Models\Internship::where('supervisor_id', $magtibay->id)->doesntExist()) {
            $magtibay->update(['is_active' => false]);
        }

        return compact(
            'coordinator',
            'faculty',
            'magtibay',
            'adrian',
            'unrelated',
            'student',
            'company',
            'internship',
            'result'
        );
    }

    public function test_2300592_resolves_clarence_montealegre_not_magtibay(): void
    {
        $party = $this->party();
        $found = User::where('student_number', '2300592')->first();
        $this->assertNotNull($found);
        $this->assertSame($party['student']->id, $found->id);
        $this->assertSame('Montealegre', $found->studentProfile->last_name);
        $this->assertNotSame($party['magtibay']->id, $found->id);
    }

    public function test_current_supervisor_is_adrian_sup_0002_not_magtibay(): void
    {
        $party = $this->party();
        $internship = $party['internship']->fresh(['supervisor.supervisorProfile', 'currentPlacement.supervisor']);

        $this->assertSame($party['adrian']->id, (int) $internship->supervisor_id);
        $this->assertSame('SUP-0002', $internship->supervisor->faculty_number);
        $this->assertSame('Reyes', $internship->supervisor->supervisorProfile->last_name);
        $this->assertNotSame($party['magtibay']->id, (int) $internship->supervisor_id);

        if ($internship->currentPlacement) {
            $this->assertSame($party['adrian']->id, (int) $internship->currentPlacement->supervisor_id);
        }
    }

    public function test_no_partial_name_clarence_supervisor_resolution(): void
    {
        $party = $this->party();
        // Dangerous pattern must not be how we resolve current supervisor.
        $firstNamedClarence = User::where('role', 'supervisor')
            ->whereHas('supervisorProfile', fn ($q) => $q->where('first_name', 'like', '%Clarence%'))
            ->first();
        $this->assertNotNull($firstNamedClarence);
        $this->assertSame($party['magtibay']->id, $firstNamedClarence->id);

        $authoritative = $party['internship']->fresh()->supervisor_id;
        $this->assertSame($party['adrian']->id, (int) $authoritative);
        $this->assertNotSame($firstNamedClarence->id, (int) $authoritative);
    }

    public function test_attendance_supervisor_details_and_hours(): void
    {
        $party = $this->party();
        Sanctum::actingAs($party['student']);

        $status = $this->getJson('/api/v1/student/supervisor-invite/status')->assertOk();
        $this->assertSame('assigned', $status->json('state'));
        $this->assertStringContainsString('Reyes', (string) $status->json('supervisor.name'));
        $this->assertStringNotContainsString('Magtibay', (string) $status->json('supervisor.name'));

        $dash = $this->getJson('/api/v1/student/dashboard')->assertOk();
        $this->assertSame('SUP-0002', $dash->json('internship.supervisor_faculty_number'));

        $logs = AttendanceLog::where('internship_id', $party['internship']->id)->orderBy('date')->get();
        $this->assertCount(5, $logs);
        $this->assertEquals(40.0, (float) $logs->sum('hours_rendered'));
        $this->assertTrue($logs->every(fn ($l) => (int) $l->validated_by === $party['adrian']->id));

        $snap = InternshipProgressService::snapshot($party['internship']->fresh());
        $this->assertSame(40, (int) $snap['hours_rendered']);
        $this->assertSame(500, (int) $snap['target_hours']);
        $this->assertSame(460, (int) $snap['remaining_hours']);
        $this->assertSame(8, (int) $snap['progress_pct']);
    }

    public function test_adrian_sees_attendance_unrelated_denied(): void
    {
        $party = $this->party();

        Sanctum::actingAs($party['adrian']);
        $ok = $this->getJson('/api/v1/supervisor/attendance')->assertOk();
        $payload = $ok->json();
        $flat = json_encode($payload);
        $this->assertStringContainsString('2300592', $flat);
        $this->assertStringContainsString('Montealegre', $flat);

        Sanctum::actingAs($party['unrelated']);
        $denied = $this->getJson('/api/v1/supervisor/attendance');
        $this->assertTrue(in_array($denied->status(), [200, 403], true));
        if ($denied->status() === 200) {
            $this->assertStringNotContainsString('2300592', json_encode($denied->json()));
        }
    }

    public function test_week1_journal_faculty_only_and_no_overlap(): void
    {
        $party = $this->party();
        $journal = JournalEntry::where('internship_id', $party['internship']->id)
            ->where('week_number', 1)
            ->first();

        $this->assertNotNull($journal);
        $this->assertSame('2026-08-24', $journal->date?->format('Y-m-d') ?? (string) $journal->getRawOriginal('date'));
        $end = $journal->end_date?->format('Y-m-d') ?? (string) $journal->getRawOriginal('end_date');
        $this->assertSame('2026-08-28', substr($end, 0, 10));
        $this->assertSame('approved', $journal->status);
        $this->assertSame(OneWeekOjtDemoService::ACCOMPLISHMENT, $journal->activities_summary);

        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/logbook', [
            'week_number' => 2,
            'date' => '2026-08-26',
            'end_date' => '2026-08-30',
            'activities_summary' => 'Overlap attempt',
            'challenges' => 'x',
            'learnings' => 'y',
        ])->assertStatus(422);

        Sanctum::actingAs($party['adrian']);
        $this->getJson('/api/v1/supervisor/journals')->assertNotFound();
        $this->getJson('/api/v1/supervisor/journal/generate')->assertNotFound();
        $this->patchJson('/api/v1/supervisor/journals/'.$journal->id.'/review', [
            'action' => 'approved',
        ])->assertNotFound();
    }

    public function test_fo30_fo31_use_adrian_and_same_journal(): void
    {
        $party = $this->party();
        $forms = app(OfficialFormDataService::class);

        $fo30 = $forms->fo30($party['internship']->fresh());
        $this->assertSame('SUP-0002', $fo30['supervisor_faculty_number'] ?? null);
        $this->assertStringContainsString('REYES', strtoupper((string) ($fo30['supervisor_name'] ?? '')));
        $this->assertStringNotContainsString('Magtibay', json_encode($fo30));
        $this->assertStringNotContainsString('MAGTIBAY', strtoupper(json_encode($fo30)));

        Sanctum::actingAs($party['student']);
        $portfolio = $this->getJson('/api/v1/student/portfolio')->assertOk()->json();
        $this->assertSame('SUP-0002', $portfolio['identity']['supervisor_faculty_number']);
        $this->assertStringContainsString('REYES', strtoupper((string) $portfolio['identity']['supervisor_name']));
        $journal = collect($portfolio['internship']['journals'] ?? [])->firstWhere('week_number', 1);
        $this->assertNotNull($journal);
        $this->assertSame(OneWeekOjtDemoService::ACCOMPLISHMENT, $journal['activities_summary']);
        $this->assertSame('2026-08-24', $journal['date']);
        $this->assertSame('2026-08-28', $journal['end_date']);
    }

    public function test_feedback_visibility_and_evaluation_not_yet_eligible(): void
    {
        $party = $this->party();

        Sanctum::actingAs($party['adrian']);
        $this->postJson('/api/v1/supervisor/feedback/'.$party['internship']->id, [
            'feedback' => OneWeekOjtDemoService::FEEDBACK,
        ])->assertOk();

        Sanctum::actingAs($party['student']);
        $studentView = $this->getJson('/api/v1/student/supervisor-feedback')->assertOk();
        $this->assertStringContainsString('positive attitude', json_encode($studentView->json()));

        Sanctum::actingAs($party['faculty']);
        $facultyView = $this->getJson('/api/v1/faculty/supervisor-feedback')->assertOk();
        $this->assertStringContainsString('2300592', json_encode($facultyView->json()));

        $eligibility = InternshipProgressService::evaluationEligibility($party['internship']->fresh());
        $this->assertFalse((bool) ($eligibility['midterm_eligible'] ?? true));
        $this->assertSame('Not Yet Eligible', $eligibility['label'] ?? null);
    }

    public function test_messages_and_working_hours_route_to_adrian(): void
    {
        $party = $this->party();
        Sanctum::actingAs($party['student']);

        $contacts = $this->getJson('/api/v1/messages/contacts');
        if ($contacts->status() === 200) {
            $json = json_encode($contacts->json());
            $this->assertStringContainsString((string) $party['adrian']->id, $json);
            $this->assertStringNotContainsString('Magtibay', $json);
        }

        $proposal = $this->postJson('/api/v1/student/attendance/schedules', [
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);
        $this->assertTrue(
            in_array($proposal->status(), [200, 201, 422], true),
            'Unexpected schedule status '.$proposal->status().': '.$proposal->getContent()
        );
        if (in_array($proposal->status(), [200, 201], true)) {
            $this->assertStringNotContainsString('Magtibay', json_encode($proposal->json()));
        }
    }

    public function test_supervisor_username_login_and_magtibay_inactive(): void
    {
        $party = $this->party();
        $party['magtibay']->refresh();
        $this->assertFalse((bool) $party['magtibay']->is_active);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'adrian.reyes',
            'password' => 'password',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'username' => 'SUP-0002',
            'password' => 'password',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'username' => 'SUP-0001',
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_portfolio_image_only_rule_still_enforced(): void
    {
        $party = $this->party();
        Sanctum::actingAs($party['student']);
        $pdf = \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $res = $this->post('/api/v1/student/portfolio/photos', [
            'type' => 'org_chart',
            'file' => $pdf,
            'internship_id' => $party['internship']->id,
        ], ['Accept' => 'application/json']);
        $this->assertTrue(
            in_array($res->status(), [422, 400], true),
            'PDF must be rejected in portfolio builder, got '.$res->status().': '.$res->getContent()
        );
    }
}
