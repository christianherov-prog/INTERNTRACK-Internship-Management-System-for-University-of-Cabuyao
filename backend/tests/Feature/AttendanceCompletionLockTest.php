<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLog;
use App\Models\Internship;
use App\Models\ProgramHteRequirement;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\DtrWorkflowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

/**
 * A completed internship (official status `completed`) keeps its attendance
 * history and FO-30 readable but refuses every new attendance entry — in the
 * API itself, not only in the UI. Target hours come from the program.
 */
class AttendanceCompletionLockTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // A weekday mid-shift in Asia/Manila (01:30 UTC = 09:30 Manila).
        Carbon::setTestNow(Carbon::parse('2026-09-24 01:30:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{student: User, supervisor: User, internship: Internship} */
    private function party(string $programCode, float $programHours, string $status, float $rendered): array
    {
        $programName = $programCode === 'BSCS'
            ? 'Bachelor of Science in Computer Science'
            : 'Bachelor of Science in Information Technology';
        $student = $this->makeStudentInCollege('CCS', $programName, $programCode, $programCode === 'BSCS' ? '4CSA' : '4ITD');
        ProgramHteRequirement::firstOrCreate(
            ['program_id' => $student->studentProfile->program_id, 'sequence_order' => 1],
            ['label' => 'HTE 1', 'required_hours' => $programHours]
        );

        $supervisor = $this->makeUser('supervisor');
        $internship = $this->makeActiveInternship(
            $student,
            $this->makeEligibleCompany(),
            $supervisor,
            $this->makeUser('faculty'),
            $this->makeUser('coordinator'),
        );
        $internship->update([
            'status' => $status,
            'target_hours' => $programHours,
            'total_hours_rendered' => $rendered,
            'program' => $programName,
        ]);

        return ['student' => $student, 'supervisor' => $supervisor, 'internship' => $internship->fresh()];
    }

    private function historicalLog(Internship $internship, string $date, array $overrides = []): AttendanceLog
    {
        return $internship->attendance()->create(array_merge([
            'date' => $date,
            'clock_in' => '00:00:00',
            'am_time_in' => '00:00:00',
            'clock_out' => '09:00:00',
            'hours_rendered' => 8,
            'status' => 'validated',
            'on_break' => false,
        ], $overrides));
    }

    // ATT-COMP-01
    public function test_ongoing_student_below_target_can_clock_in(): void
    {
        $p = $this->party('BSIT', 500, 'active', 499);
        Sanctum::actingAs($p['student']);

        $this->postJson('/api/v1/student/attendance/clock-in')
            ->assertCreated()
            ->assertJsonPath('record.status', 'pending');

        $this->getJson('/api/v1/student/attendance')
            ->assertOk()
            ->assertJsonPath('internship_completed', false)
            ->assertJsonPath('new_attendance_allowed', true);
    }

    // ATT-COMP-02
    public function test_completed_student_cannot_clock_in_and_no_row_is_created(): void
    {
        $p = $this->party('BSIT', 500, 'completed', 500);
        $before = AttendanceLog::count();
        Sanctum::actingAs($p['student']);

        $this->postJson('/api/v1/student/attendance/clock-in')
            ->assertStatus(409)
            ->assertJsonPath('code', 'internship_completed')
            ->assertJsonPath('message', Internship::ATTENDANCE_COMPLETED_MESSAGE);

        $this->assertSame($before, AttendanceLog::count());
    }

    // ATT-COMP-03
    public function test_completed_student_cannot_clock_out_and_open_record_is_not_mutated(): void
    {
        $p = $this->party('BSIT', 500, 'completed', 500);
        $open = $this->historicalLog($p['internship'], '2026-09-24', ['clock_out' => null, 'hours_rendered' => null, 'status' => 'pending']);
        Sanctum::actingAs($p['student']);

        $this->postJson('/api/v1/student/attendance/clock-out', ['action' => 'end_day'])->assertStatus(409);

        $fresh = $open->fresh();
        $this->assertNull($fresh->clock_out);
        $this->assertNull($fresh->hours_rendered);
        $this->assertSame('pending', $fresh->status);
    }

    // ATT-COMP-04
    public function test_completed_student_cannot_start_or_resume_a_break(): void
    {
        $p = $this->party('BSIT', 500, 'completed', 500);
        $open = $this->historicalLog($p['internship'], '2026-09-24', ['clock_out' => null, 'hours_rendered' => null, 'status' => 'pending']);
        Sanctum::actingAs($p['student']);

        $this->postJson('/api/v1/student/attendance/break-start')->assertStatus(409);
        $this->assertFalse((bool) $open->fresh()->on_break);
        $this->assertNull($open->fresh()->break_start);

        $open->forceFill(['on_break' => true, 'break_start' => now()])->save();
        $this->postJson('/api/v1/student/attendance/break-end')->assertStatus(409);
        $this->assertTrue((bool) $open->fresh()->on_break);
        $this->assertNull($open->fresh()->break_end);
    }

    // ATT-COMP-05
    public function test_direct_requests_to_every_new_attendance_endpoint_are_denied(): void
    {
        $p = $this->party('BSIT', 500, 'completed', 500);
        $closed = $this->historicalLog($p['internship'], '2026-09-24');
        Sanctum::actingAs($p['student']);

        $counts = fn () => [
            AttendanceLog::count(),
            WorkSchedule::count(),
            AttendanceCorrectionRequest::count(),
        ];
        $before = $counts();

        $this->postJson('/api/v1/student/attendance/clock-in')->assertStatus(409);
        $this->postJson('/api/v1/student/attendance/clock-out')->assertStatus(409);
        $this->postJson('/api/v1/student/attendance/break-start')->assertStatus(409);
        $this->postJson('/api/v1/student/attendance/break-end')->assertStatus(409);
        $this->postJson('/api/v1/student/attendance/undo-clock-out')->assertStatus(409);
        $this->postJson('/api/v1/student/attendance/overtime-decision', ['attendance_log_id' => $closed->id, 'accept' => true])->assertStatus(409);
        $this->postJson('/api/v1/student/attendance/schedules', ['start_time' => '08:00', 'end_time' => '17:00'])->assertStatus(409);
        $this->postJson('/api/v1/student/attendance/corrections', [
            'date' => '2026-09-23',
            'correction_type' => 'clock_in',
            'requested_clock_in' => '08:00',
            'reason' => 'Late tap',
        ])->assertStatus(409);

        $this->assertSame($before, $counts());
        $this->assertSame('09:00:00', (string) $closed->fresh()->clock_out);
    }

    // ATT-COMP-06
    public function test_completed_student_keeps_attendance_history(): void
    {
        $p = $this->party('BSIT', 500, 'completed', 500);
        $this->historicalLog($p['internship'], '2026-09-21');
        $this->historicalLog($p['internship'], '2026-09-22');
        Sanctum::actingAs($p['student']);

        $response = $this->getJson('/api/v1/student/attendance')
            ->assertOk()
            ->assertJsonPath('internship_completed', true)
            ->assertJsonPath('new_attendance_allowed', false)
            ->assertJsonPath('new_attendance_lock_reason', Internship::ATTENDANCE_COMPLETED_MESSAGE)
            ->assertJsonPath('internship_status', 'completed');

        $this->assertCount(2, $response->json('attendance.data'));
        $this->assertEquals(500.0, $response->json('hours_rendered'));

        $this->getJson('/api/v1/student/attendance/corrections')->assertOk();
        $this->getJson('/api/v1/student/attendance/schedules')->assertOk();
    }

    // ATT-COMP-07
    public function test_completed_student_can_still_open_fo30(): void
    {
        $p = $this->party('BSIT', 500, 'completed', 500);
        $this->historicalLog($p['internship'], '2026-09-21');
        Sanctum::actingAs($p['student']);

        $pdf = $this->get('/api/v1/official-forms/'.$p['internship']->id.'/dtr.pdf');
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    // ATT-COMP-08
    public function test_program_target_hours_are_respected_and_completion_follows_official_status(): void
    {
        // BSCS (300 h) formally completed at 300/300 → closed, target reported as 300.
        $bscs = $this->party('BSCS', 300, 'completed', 300);
        Sanctum::actingAs($bscs['student']);
        $this->getJson('/api/v1/student/attendance')
            ->assertOk()
            ->assertJsonPath('internship_completed', true);
        $this->assertEquals(300.0, $this->getJson('/api/v1/student/attendance')->json('target_hours'));
        $this->postJson('/api/v1/student/attendance/clock-in')->assertStatus(409);

        // BSIT (500 h) still active at 300 hours → open, target reported as 500.
        $bsit = $this->party('BSIT', 500, 'active', 300);
        Sanctum::actingAs($bsit['student']);
        $this->assertEquals(500.0, $this->getJson('/api/v1/student/attendance')->json('target_hours'));
        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
    }

    public function test_active_internship_at_target_hours_stays_open_until_formally_completed(): void
    {
        // Completion is the staff status workflow, not a derived hours check.
        $p = $this->party('BSIT', 500, 'active', 500);
        Sanctum::actingAs($p['student']);

        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();

        $p['internship']->update(['status' => 'completed']);
        $this->postJson('/api/v1/student/attendance/clock-out')->assertStatus(409);
    }

    public function test_fresh_student_without_placement_still_gets_the_placement_lock(): void
    {
        $student = $this->makeStudentWithSection();
        $this->makePendingInternship($student);
        Sanctum::actingAs($student);

        $this->postJson('/api/v1/student/attendance/clock-in')->assertStatus(403);
        $this->assertSame(0, AttendanceLog::count());
    }
}
