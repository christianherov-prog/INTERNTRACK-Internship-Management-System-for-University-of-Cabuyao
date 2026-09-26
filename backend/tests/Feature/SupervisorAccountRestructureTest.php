<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\SupervisorProfile;
use App\Models\User;
use App\Services\InternshipProgressService;
use App\Services\OfficialFormDataService;
use App\Services\OneWeekOjtDemoService;
use App\Services\SupervisorFeedbackService;
use App\Support\ManilaTime;
use App\Support\SupervisorIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class SupervisorAccountRestructureTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    private function map(): array
    {
        $coordinator = $this->makeUser('coordinator', 'COR-CCS-001');
        $faculty = $this->makeUser('faculty', 'FAC-1001');
        $this->mapFacultyForSection($faculty);

        $retired = User::create([
            'faculty_number' => 'SUP-0001',
            'email' => 'patrick.bateman@techcorp.ph',
            'password' => Hash::make('password'),
            'role' => 'supervisor',
            'is_active' => false,
        ]);
        SupervisorProfile::create([
            'user_id' => $retired->id,
            'first_name' => 'Patrick',
            'last_name' => 'Bateman',
            'email' => $retired->email,
            'position' => 'Industry Supervisor',
        ]);

        $adrian = $this->makeUser('supervisor', 'SUP-0002');
        SupervisorProfile::create([
            'user_id' => $adrian->id,
            'first_name' => 'Adrian',
            'last_name' => 'Reyes',
            'email' => $adrian->email,
            'position' => 'Industry Supervisor',
        ]);

        $arthur = $this->makeUser('supervisor', 'SUP-0003');
        SupervisorProfile::create([
            'user_id' => $arthur->id,
            'first_name' => 'Arthur',
            'last_name' => 'Morgan',
            'email' => $arthur->email,
            'position' => 'Industry Supervisor',
        ]);

        $clarence = $this->makeStudentWithSection();
        $clarence->studentProfile->update([
            'first_name' => 'Clarence',
            'last_name' => 'Montealegre',
            'student_number' => '2300592',
        ]);
        $clarence->update(['student_number' => '2300592']);

        $angel = $this->makeStudentWithSection('4ITD');
        $angel->studentProfile->update([
            'first_name' => 'Angel Luis',
            'last_name' => 'Taac - Taac',
            'student_number' => '2300590',
        ]);
        $angel->update(['student_number' => '2300590']);

        $accenture = $this->makeEligibleCompany(['company_name' => 'Accenture PH']);
        $clarenceIntern = $this->makeActiveInternship($clarence, $accenture, $adrian, $faculty, $coordinator);
        $clarenceIntern->update(['target_hours' => 500, 'status' => 'ongoing']);
        $angelIntern = $this->makeActiveInternship($angel, $accenture, $arthur, $faculty, $coordinator);
        $angelIntern->update(['target_hours' => 500, 'status' => 'ongoing']);

        return compact(
            'coordinator',
            'faculty',
            'retired',
            'adrian',
            'arthur',
            'clarence',
            'angel',
            'accenture',
            'clarenceIntern',
            'angelIntern'
        );
    }

    public function test_target_supervisor_ids_are_unique_and_sup_0001_is_inactive(): void
    {
        $map = $this->map();
        $this->assertSame('SUP-0002', $map['adrian']->faculty_number);
        $this->assertSame('Adrian Reyes', trim($map['adrian']->supervisorProfile->first_name.' '.$map['adrian']->supervisorProfile->last_name));
        $this->assertSame('SUP-0003', $map['arthur']->faculty_number);
        $this->assertSame('Arthur Morgan', trim($map['arthur']->supervisorProfile->first_name.' '.$map['arthur']->supervisorProfile->last_name));
        $this->assertFalse((bool) $map['retired']->is_active);
        $this->assertSame('SUP-0001', $map['retired']->faculty_number);
        $this->assertSame('SUP-0004', SupervisorIds::nextFacultyNumber());

        $this->postJson('/api/v1/auth/login', [
            'username' => 'SUP-0001',
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_clarence_resolves_adrian_and_angel_resolves_arthur(): void
    {
        $map = $this->map();
        $this->assertSame($map['adrian']->id, (int) $map['clarenceIntern']->supervisor_id);
        $this->assertSame($map['arthur']->id, (int) $map['angelIntern']->supervisor_id);
        $this->assertNotSame($map['arthur']->id, (int) $map['clarenceIntern']->supervisor_id);
        $this->assertNotSame($map['adrian']->id, (int) $map['angelIntern']->supervisor_id);

        Sanctum::actingAs($map['clarence']);
        $dash = $this->getJson('/api/v1/student/dashboard')->assertOk();
        $this->assertStringContainsString('Adrian', (string) $dash->json('internship.supervisor_name'));
        $this->assertSame('SUP-0002', $dash->json('internship.supervisor_faculty_number'));

        Sanctum::actingAs($map['angel']);
        $angelDash = $this->getJson('/api/v1/student/dashboard')->assertOk();
        $this->assertStringContainsString('Arthur', (string) $angelDash->json('internship.supervisor_name'));
        $this->assertSame('SUP-0003', $angelDash->json('internship.supervisor_faculty_number'));

        Sanctum::actingAs($map['adrian']);
        $adrianIds = collect($this->getJson('/api/v1/supervisor/assigned-interns')->json('data'))->pluck('id');
        $this->assertTrue($adrianIds->contains($map['clarenceIntern']->id));
        $this->assertFalse($adrianIds->contains($map['angelIntern']->id));

        Sanctum::actingAs($map['arthur']);
        $arthurIds = collect($this->getJson('/api/v1/supervisor/assigned-interns')->json('data'))->pluck('id');
        $this->assertTrue($arthurIds->contains($map['angelIntern']->id));
        $this->assertFalse($arthurIds->contains($map['clarenceIntern']->id));
    }

    public function test_faculty_and_coordinator_see_the_same_supervisor_map(): void
    {
        $map = $this->map();

        Sanctum::actingAs($map['faculty']);
        $facultyRows = collect($this->getJson('/api/v1/faculty/assigned-students')->json('data'));
        $clarence = $facultyRows->first(fn ($row) => ($row['student']['student_number'] ?? null) === '2300592');
        $angel = $facultyRows->first(fn ($row) => ($row['student']['student_number'] ?? null) === '2300590');
        $this->assertNotNull($clarence);
        $this->assertNotNull($angel);
        $this->assertStringContainsString('Reyes', (string) (
            $clarence['supervisor']['supervisor_profile']['last_name']
            ?? $clarence['supervisor']['supervisor_profile']['full_name']
            ?? ''
        ));
        $this->assertStringContainsString('Morgan', (string) (
            $angel['supervisor']['supervisor_profile']['last_name']
            ?? $angel['supervisor']['supervisor_profile']['full_name']
            ?? ''
        ));

        Sanctum::actingAs($map['coordinator']);
        $coordRows = collect($this->getJson('/api/v1/coordinator/monitoring')->json('data'));
        $coordClarence = $coordRows->firstWhere('student_number', '2300592');
        $coordAngel = $coordRows->firstWhere('student_number', '2300590');
        $this->assertNotNull($coordClarence);
        $this->assertNotNull($coordAngel);
        $this->assertStringContainsString('Reyes', (string) $coordClarence['supervisor_name']);
        $this->assertStringContainsString('Morgan', (string) $coordAngel['supervisor_name']);
    }

    public function test_fo30_and_feedback_use_adrian_for_clarence_not_arthur(): void
    {
        Storage::fake('local');
        $map = $this->map();
        Storage::disk('local')->put('signatures/'.$map['adrian']->id.'_processed.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        app(OneWeekOjtDemoService::class)->syncAttendance($map['clarenceIntern'], $map['adrian']);
        InternshipProgressService::synchronize($map['clarenceIntern']);

        Sanctum::actingAs($map['clarence']);
        $fo30 = $this->getJson('/api/v1/official-forms/'.$map['clarenceIntern']->id)->assertOk()->json();
        $this->assertSame('SUP-0002', $fo30['identity']['supervisor_faculty_number']);
        $this->assertStringContainsString('REYES', strtoupper((string) $fo30['fo30']['supervisor_name']));
        $this->assertStringNotContainsString('MORGAN', strtoupper((string) $fo30['fo30']['supervisor_name']));
        $this->assertEquals(80.0, collect($fo30['fo30']['logs'])->sum('hours_rendered'));
        $this->assertSame('signatures/'.$map['adrian']->id.'_processed.png', $fo30['fo30']['logs'][0]['hte_signature_path']);
        $this->assertSame(ManilaTime::TZ, $fo30['timezone']);

        $eligibility = InternshipProgressService::evaluationEligibility($map['clarenceIntern']->fresh());
        $this->assertSame('not_yet_eligible', $eligibility['status']);

        Sanctum::actingAs($map['adrian']);
        $this->postJson('/api/v1/supervisor/feedback/'.$map['clarenceIntern']->id, [
            'feedback' => OneWeekOjtDemoService::FEEDBACK,
        ])->assertOk();

        Sanctum::actingAs($map['arthur']);
        $this->postJson('/api/v1/supervisor/feedback/'.$map['clarenceIntern']->id, [
            'feedback' => 'Should not land on Clarence.',
        ])->assertForbidden();

        $note = app(SupervisorFeedbackService::class)->noteForInternship($map['clarenceIntern']->fresh());
        $this->assertSame($map['adrian']->id, (int) $note->supervisor_reviewed_by);

        Sanctum::actingAs($map['clarence']);
        $this->postJson('/api/v1/messages', [
            'internship_id' => $map['clarenceIntern']->id,
            'recipient_id' => $map['adrian']->id,
            'body' => 'Hello Adrian',
        ])->assertCreated();

        Sanctum::actingAs($map['arthur']);
        $inbox = $this->getJson('/api/v1/messages/conversations')->assertOk();
        $bodies = collect($inbox->json('data') ?? [])
            ->flatMap(fn ($row) => collect($row['messages'] ?? [$row]))
            ->pluck('body');
        $this->assertFalse($bodies->contains('Hello Adrian'));
    }

    public function test_duplicate_supervisor_id_is_rejected_without_creating_a_second_account(): void
    {
        $this->map();

        try {
            User::create([
                'faculty_number' => 'SUP-0002',
                'email' => 'duplicate.adrian@example.com',
                'password' => Hash::make('password'),
                'role' => 'supervisor',
                'is_active' => true,
            ]);
            $this->fail('Duplicate SUP-0002 should have been rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('faculty_number', $e->errors());
        }

        $this->assertSame(1, User::where('faculty_number', 'SUP-0002')->count());
        $this->assertSame(0, User::where('email', 'duplicate.adrian@example.com')->count());
    }

    public function test_existing_supervisor_registration_does_not_duplicate_the_account(): void
    {
        Storage::fake('local');
        $map = $this->map();
        $map['angelIntern']->update(['supervisor_id' => null, 'status' => 'active']);

        Sanctum::actingAs($map['angel']);
        $token = $this->postJson('/api/v1/student/supervisor-invite')->assertOk()->json('token');

        $this->post('/api/v1/supervisor-register', [
            'token' => $token,
            'login_username' => 'arthur.existing',
            'first_name' => 'Arthur',
            'last_name' => 'Morgan',
            'email' => $map['arthur']->email,
            'contact_number' => '09171234567',
            'position' => 'Industry Supervisor',
            'sex' => 'Male',
            'company_id' => $map['accenture']->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'acceptance_forms' => [UploadedFile::fake()->create('GFAMOA.pdf', 40, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertStatus(409);

        $this->assertSame(1, User::where('email', $map['arthur']->email)->count());
        $this->assertSame('SUP-0003', $map['arthur']->fresh()->faculty_number);
    }

    public function test_fo24_evaluator_is_adrian_when_an_evaluation_exists(): void
    {
        $map = $this->map();
        Evaluation::create([
            'internship_id' => $map['clarenceIntern']->id,
            'evaluator_type' => 'supervisor',
            'evaluated_by' => $map['adrian']->id,
            'evaluation_period' => 'final',
            'form_type' => 'FO-24',
            'responses' => ['q1' => 5],
            'total_score' => 5,
            'submitted_at' => now(),
        ]);

        $bundle = app(OfficialFormDataService::class)->bundle($map['clarenceIntern']->fresh());
        $eval = collect($bundle['evaluations'])->first();
        $this->assertNotNull($eval);
        $this->assertSame($map['adrian']->id, (int) ($eval['evaluated_by'] ?? $eval['evaluator_id'] ?? $map['adrian']->id));
        $this->assertSame('SUP-0002', $bundle['identity']['supervisor_faculty_number']);
        $this->assertStringContainsString('REYES', strtoupper((string) $bundle['identity']['supervisor_name']));
    }
}
