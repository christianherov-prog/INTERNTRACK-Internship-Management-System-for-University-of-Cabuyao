<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLog;
use App\Models\DtrRequestAudit;
use App\Models\OvertimeEntry;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class DtrWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    private function setupParty(): array
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        return compact('coordinator', 'faculty', 'supervisor', 'student', 'company', 'internship');
    }

    public function test_student_proposes_schedule_and_supervisor_approves(): void
    {
        $party = $this->setupParty();
        Sanctum::actingAs($party['student']);

        $this->postJson('/api/v1/student/attendance/schedules', [
            'start_time' => '07:00',
            'end_time' => '17:00',
        ])->assertCreated()->assertJsonPath('schedule.status', 'pending');

        $this->postJson('/api/v1/student/attendance/schedules', [
            'start_time' => '08:00',
            'end_time' => '17:00',
        ])->assertStatus(422);

        $scheduleId = WorkSchedule::first()->id;

        Sanctum::actingAs($party['supervisor']);
        $this->patchJson("/api/v1/supervisor/dtr/schedules/{$scheduleId}", [
            'action' => 'approved',
        ])->assertOk()->assertJsonPath('schedule.status', 'approved');

        $this->assertDatabaseHas('work_schedules', [
            'id' => $scheduleId,
            'status' => 'approved',
        ]);
    }

    public function test_rejected_schedule_notifies_student_and_allows_new_proposal(): void
    {
        $party = $this->setupParty();
        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/schedules', [
            'start_time' => '07:00',
            'end_time' => '17:00',
        ])->assertCreated();

        $scheduleId = WorkSchedule::first()->id;
        Sanctum::actingAs($party['supervisor']);
        $this->patchJson("/api/v1/supervisor/dtr/schedules/{$scheduleId}", [
            'action' => 'rejected',
            'remarks' => 'Too early.',
        ])->assertOk();

        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/schedules', [
            'start_time' => '08:00',
            'end_time' => '17:00',
        ])->assertCreated();
    }

    public function test_previous_active_schedule_stays_until_new_proposal_is_approved(): void
    {
        $party = $this->setupParty();
        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/schedules', [
            'start_time' => '07:00',
            'end_time' => '17:00',
        ])->assertCreated();

        $firstId = WorkSchedule::first()->id;
        Sanctum::actingAs($party['supervisor']);
        $this->patchJson("/api/v1/supervisor/dtr/schedules/{$firstId}", ['action' => 'approved'])->assertOk();

        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/schedules', [
            'start_time' => '09:00',
            'end_time' => '18:00',
        ])->assertCreated();

        $active = $this->getJson('/api/v1/student/attendance/schedules')
            ->assertOk()
            ->json('active_schedule');

        $this->assertSame('07:00:00', $active['start_time']);
        $this->assertSame('pending', $this->getJson('/api/v1/student/attendance/schedules')->json('pending_schedule.status'));
    }

    public function test_clock_out_confirm_path_supports_grace_undo(): void
    {
        $party = $this->setupParty();
        Carbon::setTestNow(Carbon::parse('2026-09-05 08:00:00'));
        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();

        Carbon::setTestNow(Carbon::parse('2026-09-05 17:00:00'));
        $this->postJson('/api/v1/student/attendance/clock-out')
            ->assertOk()
            ->assertJsonPath('can_undo_clock_out', true);

        Carbon::setTestNow(Carbon::parse('2026-09-05 17:03:00'));
        $this->postJson('/api/v1/student/attendance/undo-clock-out')
            ->assertOk()
            ->assertJsonPath('today_status', 'clocked_in');

        $this->assertDatabaseHas('attendance_logs', [
            'internship_id' => $party['internship']->id,
            'clock_out' => null,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-05 18:00:00'));
        $this->postJson('/api/v1/student/attendance/clock-out')->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-09-05 18:06:00'));
        $this->postJson('/api/v1/student/attendance/undo-clock-out')->assertStatus(422);

        Carbon::setTestNow();
    }

    public function test_overtime_requires_student_opt_in_and_supervisor_approval(): void
    {
        $party = $this->setupParty();
        Carbon::setTestNow(Carbon::parse('2026-09-05 07:00:00'));
        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/schedules', [
            'start_time' => '07:00',
            'end_time' => '17:00',
        ])->assertCreated();

        Sanctum::actingAs($party['supervisor']);
        $this->patchJson('/api/v1/supervisor/dtr/schedules/'.WorkSchedule::first()->id, [
            'action' => 'approved',
        ])->assertOk();

        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();

        Carbon::setTestNow(Carbon::parse('2026-09-05 18:00:00'));
        $out = $this->postJson('/api/v1/student/attendance/clock-out')
            ->assertOk()
            ->assertJsonPath('overtime_detected', true);

        $logId = $out->json('record.id');
        $this->assertEquals(10, (float) $out->json('record.hours_rendered'));

        $this->postJson('/api/v1/student/attendance/overtime-decision', [
            'attendance_log_id' => $logId,
            'accept' => true,
        ])->assertOk();

        $entry = OvertimeEntry::first();
        $this->assertSame('pending', $entry->status);
        $this->assertEquals(10, (float) AttendanceLog::find($logId)->hours_rendered);

        Sanctum::actingAs($party['supervisor']);
        $this->patchJson("/api/v1/supervisor/dtr/overtime/{$entry->id}", [
            'action' => 'approved',
        ])->assertOk();

        $log = AttendanceLog::find($logId);
        $this->assertEquals(11, (float) $log->hours_rendered);
        $this->assertEquals(1, (float) $log->overtime_hours);
        $this->assertGreaterThan(0, DtrRequestAudit::where('auditable_id', $entry->id)->count());

        Carbon::setTestNow();
    }

    public function test_declining_overtime_does_not_record_excess(): void
    {
        $party = $this->setupParty();
        Carbon::setTestNow(Carbon::parse('2026-09-05 07:00:00'));
        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/schedules', [
            'start_time' => '07:00',
            'end_time' => '17:00',
        ])->assertCreated();
        Sanctum::actingAs($party['supervisor']);
        $this->patchJson('/api/v1/supervisor/dtr/schedules/'.WorkSchedule::first()->id, ['action' => 'approved'])->assertOk();

        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
        Carbon::setTestNow(Carbon::parse('2026-09-05 18:00:00'));
        $logId = $this->postJson('/api/v1/student/attendance/clock-out')->json('record.id');

        $this->postJson('/api/v1/student/attendance/overtime-decision', [
            'attendance_log_id' => $logId,
            'accept' => false,
        ])->assertOk();

        $this->assertSame('declined', OvertimeEntry::first()->status);
        $this->assertEquals(10, (float) AttendanceLog::find($logId)->hours_rendered);
        $this->assertEquals(0, (float) AttendanceLog::find($logId)->overtime_hours);

        Carbon::setTestNow();
    }

    public function test_correction_requires_supervisor_then_faculty_and_does_not_edit_until_both_approve(): void
    {
        $party = $this->setupParty();
        Carbon::setTestNow(Carbon::parse('2026-09-05 12:00:00'));
        Sanctum::actingAs($party['student']);

        $yesterday = Carbon::parse('2026-09-04')->toDateString();
        $this->postJson('/api/v1/student/attendance/corrections', [
            'date' => $yesterday,
            'requested_clock_in' => '08:00',
            'requested_clock_out' => '17:00',
            'reason' => 'Forgot to clock.',
        ])->assertCreated()->assertJsonPath('correction.status', 'pending_supervisor');

        $requestId = AttendanceCorrectionRequest::first()->id;

        $this->assertDatabaseMissing('attendance_logs', [
            'internship_id' => $party['internship']->id,
            'date' => $yesterday,
        ]);

        Sanctum::actingAs($party['faculty']);
        $this->patchJson("/api/v1/faculty/dtr/corrections/{$requestId}", [
            'action' => 'approved',
        ])->assertStatus(422);

        Sanctum::actingAs($party['supervisor']);
        $this->patchJson("/api/v1/supervisor/dtr/corrections/{$requestId}", [
            'action' => 'approved',
        ])->assertOk()->assertJsonPath('correction.status', 'pending_faculty');

        $this->assertDatabaseMissing('attendance_logs', [
            'internship_id' => $party['internship']->id,
            'date' => $yesterday,
        ]);

        Sanctum::actingAs($party['faculty']);
        $this->patchJson("/api/v1/faculty/dtr/corrections/{$requestId}", [
            'action' => 'approved',
        ])->assertOk()->assertJsonPath('correction.status', 'approved');

        $this->assertDatabaseHas('attendance_logs', [
            'internship_id' => $party['internship']->id,
            'date' => $yesterday,
            'clock_in' => '08:00:00',
            'clock_out' => '17:00:00',
        ]);

        $correction = AttendanceCorrectionRequest::find($requestId);
        $this->assertNull($correction->original_clock_in);
        $this->assertNull($correction->original_clock_out);
        $this->assertSame('08:00:00', $correction->applied_clock_in);
        $this->assertSame('17:00:00', $correction->applied_clock_out);
        $this->assertGreaterThan(1, DtrRequestAudit::where('auditable_id', $requestId)->count());

        Carbon::setTestNow();
    }

    public function test_correction_cannot_be_filed_beyond_three_days(): void
    {
        $party = $this->setupParty();
        Carbon::setTestNow(Carbon::parse('2026-09-05 12:00:00'));
        Sanctum::actingAs($party['student']);

        $this->postJson('/api/v1/student/attendance/corrections', [
            'date' => '2026-09-01',
            'requested_clock_in' => '08:00',
            'requested_clock_out' => '17:00',
        ])->assertStatus(422);

        Carbon::setTestNow();
    }

    public function test_supervisor_and_faculty_history_include_entry_status(): void
    {
        $party = $this->setupParty();
        Carbon::setTestNow(Carbon::parse('2026-09-05 08:00:00'));
        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
        Carbon::setTestNow(Carbon::parse('2026-09-05 17:00:00'));
        $this->postJson('/api/v1/student/attendance/clock-out')->assertOk();

        Sanctum::actingAs($party['supervisor']);
        $history = $this->getJson('/api/v1/supervisor/dtr/history')->assertOk();
        $this->assertNotEmpty($history->json('data'));
        $this->assertArrayHasKey('dtr_entry_kind', $history->json('data.0'));
        $this->assertArrayHasKey('overtime_status', $history->json('data.0'));

        Sanctum::actingAs($party['faculty']);
        $this->getJson('/api/v1/faculty/dtr/history')
            ->assertOk()
            ->assertJsonPath('data.0.dtr_entry_kind', 'normal');

        Carbon::setTestNow();
    }
}
