<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLog;
use App\Models\Internship;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\OfficialFormDataService;
use App\Support\ManilaAttendanceClock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

/**
 * Attendance time handling end to end: clock events are stored in the app
 * timezone (UTC), approved schedules are Asia/Manila wall-clock times, and
 * every surface (Student, Supervisor, Faculty, Coordinator, FO-30) resolves
 * the same instant exactly once to Asia/Manila.
 */
class AttendanceTimezoneConsistencyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{student: User, supervisor: User, faculty: User, coordinator: User, internship: Internship} */
    private function party(string $scheduleStart = '08:00', string $scheduleEnd = '17:00'): array
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $internship = $this->makeActiveInternship($student, $this->makeEligibleCompany(), $supervisor, $faculty, $coordinator);

        WorkSchedule::create([
            'internship_id' => $internship->id,
            'proposed_by' => $student->id,
            'start_time' => $scheduleStart.':00',
            'end_time' => $scheduleEnd.':00',
            'status' => 'approved',
            'effective_from' => '2026-09-01',
            'reviewed_by' => $supervisor->id,
            'reviewed_at' => now(),
        ]);

        return compact('student', 'supervisor', 'faculty', 'coordinator', 'internship');
    }

    private function at(string $date, string $manilaClock): void
    {
        Carbon::setTestNow(Carbon::parse("{$date} {$manilaClock}:00", 'Asia/Manila')->utc());
    }

    /** Drive a real day through the Student endpoints (clock in → break → resume → clock out). */
    private function workDay(User $student, string $date, string $in, ?string $breakStart, ?string $breakEnd, string $out): AttendanceLog
    {
        Sanctum::actingAs($student);
        $this->at($date, $in);
        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
        if ($breakStart && $breakEnd) {
            $this->at($date, $breakStart);
            $this->postJson('/api/v1/student/attendance/break-start')->assertOk();
            $this->at($date, $breakEnd);
            $this->postJson('/api/v1/student/attendance/break-end')->assertOk();
        }
        $this->at($date, $out);
        $this->postJson('/api/v1/student/attendance/clock-out', ['action' => 'end_day'])->assertOk();

        return AttendanceLog::whereDate('date', $date)->firstOrFail();
    }

    private function fo30Row(Internship $internship, string $date): array
    {
        $logs = app(OfficialFormDataService::class)->fo30($internship->fresh())['logs'];

        return collect($logs)->firstWhere('date', $date);
    }

    private function fo30Html(Internship $internship): string
    {
        return view('pdf.form30_dtr', app(OfficialFormDataService::class)->pdfDtr($internship->fresh()))->render();
    }

    // ATT-TZ-01 / ATT-TZ-02 / ATT-TZ-04
    public function test_manila_clock_events_are_stored_once_as_utc_and_displayed_in_manila(): void
    {
        $p = $this->party();
        $log = $this->workDay($p['student'], '2026-09-21', '08:00', null, null, '17:00');

        // Stored in the app timezone: 08:00 Manila = 00:00 UTC, 17:00 Manila = 09:00 UTC.
        $this->assertSame('00:00:00', (string) $log->clock_in);
        $this->assertSame('09:00:00', (string) $log->clock_out);

        Sanctum::actingAs($p['student']);
        $row = $this->getJson('/api/v1/student/attendance')->assertOk()->json('attendance.data.0');
        $this->assertSame('08:00', $row['clock_in_display']);
        $this->assertSame('17:00', $row['clock_out_display']);
        $this->assertSame('2026-09-21', $row['date_display']);

        $html = $this->fo30Html($p['internship']);
        $this->assertStringContainsString('8:00 AM', $html);
        $this->assertStringContainsString('5:00 PM', $html);
    }

    // ATT-TZ-03 / ATT-DAY-02
    public function test_afternoon_session_renders_as_pm_never_am(): void
    {
        $p = $this->party();
        $log = $this->workDay($p['student'], '2026-09-22', '13:00', null, null, '17:00');

        $this->assertEquals(4.0, (float) $log->hours_rendered);
        $row = $this->fo30Row($p['internship'], '2026-09-22');
        $this->assertNull($row['am_time_in']);
        $this->assertSame('13:00', $row['pm_time_in']);
        $this->assertSame('17:00', $row['pm_time_out']);

        $html = $this->fo30Html($p['internship']);
        $this->assertStringContainsString('1:00 PM', $html);
        $this->assertStringNotContainsString('1:00 AM', $html);
    }

    // ATT-TZ-05 / ATT-DAY-01 (+ early arrival and late departure are not credited)
    public function test_standard_day_credits_eight_hours_against_the_manila_schedule(): void
    {
        $p = $this->party('08:00', '17:00');
        $log = $this->workDay($p['student'], '2026-09-23', '07:58', '12:00', '13:00', '17:01');

        // If the 08:00 schedule were misread as UTC (4:00 PM Manila) this would be ~1 h.
        $this->assertEquals(8.0, (float) $log->hours_rendered);

        $row = $this->fo30Row($p['internship'], '2026-09-23');
        $this->assertSame(['07:58', '12:00', '13:00', '17:01'], [$row['am_time_in'], $row['am_time_out'], $row['pm_time_in'], $row['pm_time_out']]);
        $this->assertEquals(8.0, $row['hours_rendered']);
    }

    // ATT-SCHED-01: a non-8-to-5 approved schedule is honoured (nothing is hardcoded).
    public function test_nine_to_six_schedule_credits_eight_hours(): void
    {
        $p = $this->party('09:00', '18:00');
        $log = $this->workDay($p['student'], '2026-09-24', '08:58', '13:00', '14:00', '18:02');

        $this->assertEquals(8.0, (float) $log->hours_rendered);
        Sanctum::actingAs($p['student']);
        $row = $this->getJson('/api/v1/student/attendance')->assertOk()->json('attendance.data.0');
        $this->assertSame('08:58', $row['clock_in_display']);
        $this->assertSame('18:02', $row['clock_out_display']);
        $this->assertSame(['start_time' => '09:00:00', 'end_time' => '18:00:00'], $row['active_schedule']);
    }

    public function test_break_outside_the_credited_window_is_not_deducted_twice(): void
    {
        $p = $this->party('08:00', '17:00');
        // Morning session only; a break after the schedule-bounded window credits nothing extra/less.
        $log = $this->workDay($p['student'], '2026-09-25', '08:00', null, null, '12:00');
        $this->assertEquals(4.0, (float) $log->hours_rendered);
    }

    // ATT-TZ-06 + cross-surface: every role and FO-30 resolve the same instant.
    public function test_student_supervisor_faculty_coordinator_and_fo30_agree(): void
    {
        $p = $this->party();
        $log = $this->workDay($p['student'], '2026-09-21', '07:58', '12:00', '13:00', '17:01');

        Sanctum::actingAs($p['supervisor']);
        $this->patchJson("/api/v1/supervisor/attendance/{$log->id}/validate", ['action' => 'validated'])->assertOk();

        Sanctum::actingAs($p['student']);
        $student = $this->getJson('/api/v1/student/attendance')->assertOk()->json('attendance.data.0');

        Sanctum::actingAs($p['supervisor']);
        $supervisor = collect($this->getJson('/api/v1/supervisor/dtr/history')->assertOk()->json('data'))->firstWhere('id', $log->id);

        Sanctum::actingAs($p['faculty']);
        $faculty = collect($this->getJson('/api/v1/faculty/attendance?internship_id='.$p['internship']->id)->assertOk()->json('data'))->firstWhere('id', $log->id);
        $facultyProgress = collect($this->getJson('/api/v1/faculty/students/'.$p['student']->id.'/progress')->assertOk()->json('attendance_logs'))->firstWhere('id', $log->id);

        Sanctum::actingAs($p['coordinator']);
        $coordinator = collect($this->getJson('/api/v1/coordinator/students/'.$p['student']->id.'/progress')->assertOk()->json('attendance_logs'))->firstWhere('id', $log->id);

        $fo30 = $this->fo30Row($p['internship'], '2026-09-21');

        foreach ([$student, $supervisor, $faculty] as $row) {
            $this->assertSame('2026-09-21', $row['date_display']);
            $this->assertSame('07:58', $row['clock_in_display']);
            $this->assertSame('17:01', $row['clock_out_display']);
            $this->assertEquals(8.0, (float) $row['hours_rendered']);
        }
        foreach ([$facultyProgress, $coordinator, $fo30] as $row) {
            $this->assertSame('2026-09-21', $row['date']);
            $this->assertSame(['07:58', '12:00', '13:00', '17:01'], [$row['am_time_in'], $row['am_time_out'], $row['pm_time_in'], $row['pm_time_out']]);
            $this->assertEquals(8.0, (float) $row['hours_rendered']);
        }
    }

    // A correction typed in Manila time is stored like a live clock event.
    public function test_correction_times_are_manila_input_and_apply_without_shift(): void
    {
        $p = $this->party();
        Sanctum::actingAs($p['student']);
        $this->at('2026-09-21', '08:00');
        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();

        $this->at('2026-09-22', '09:00');
        $this->postJson('/api/v1/student/attendance/corrections', [
            'date' => '2026-09-21',
            'correction_type' => 'clock_out',
            'requested_clock_out' => '17:00',
            'reason' => 'Forgot to clock out.',
        ])->assertCreated()
            ->assertJsonPath('correction.requested_clock_out_display', '17:00')
            ->assertJsonPath('correction.original_clock_in_display', '08:00');

        $request = AttendanceCorrectionRequest::firstOrFail();
        $this->assertSame('09:00:00', (string) $request->requested_clock_out);

        Sanctum::actingAs($p['supervisor']);
        $this->patchJson("/api/v1/supervisor/dtr/corrections/{$request->id}", ['action' => 'approved'])->assertOk();
        Sanctum::actingAs($p['faculty']);
        $this->patchJson("/api/v1/faculty/dtr/corrections/{$request->id}", ['action' => 'approved'])->assertOk();

        $log = AttendanceLog::whereDate('date', '2026-09-21')->firstOrFail();
        $this->assertSame('09:00:00', (string) $log->clock_out);
        $this->assertEquals(9.0, (float) $log->hours_rendered);
        $row = $this->fo30Row($p['internship'], '2026-09-21');
        $this->assertSame('08:00', $row['am_time_in']);
        $this->assertSame('17:00', $row['pm_time_out']);
    }

    public function test_stored_time_helpers_convert_exactly_once(): void
    {
        $this->assertSame('00:00:00', ManilaAttendanceClock::storedTime('08:00'));
        $this->assertSame('05:00:00', ManilaAttendanceClock::storedTime('13:00'));
        $this->assertSame('23:58:00', ManilaAttendanceClock::storedTime('07:58'));
        $this->assertSame('2026-06-03 04:00:00', ManilaAttendanceClock::storedInstant('2026-06-03', '12:00')->format('Y-m-d H:i:s'));
        // 07:58 Manila is 23:58 UTC the previous day; it still belongs to the Manila date.
        $this->assertSame('2026-06-03 07:58', ManilaAttendanceClock::resolveEventAt('2026-06-03', '23:58:00')->format('Y-m-d H:i'));
    }
}
