<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class ClockAttendanceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    private function setupParty(): array
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $otherSupervisor = $this->makeUser('supervisor', 'SUP-OTHER');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        return compact('coordinator', 'faculty', 'supervisor', 'otherSupervisor', 'student', 'company', 'internship');
    }

    public function test_student_can_clock_in_and_out(): void
    {
        $party = $this->setupParty();
        Sanctum::actingAs($party['student']);

        $this->postJson('/api/v1/student/attendance/clock-in')
            ->assertCreated()
            ->assertJsonPath('record.status', 'pending');

        $this->postJson('/api/v1/student/attendance/clock-out')
            ->assertOk();

        $this->assertDatabaseHas('attendance_logs', [
            'internship_id' => $party['internship']->id,
            'status' => 'pending',
        ]);
    }

    public function test_double_clock_in_returns_422(): void
    {
        $party = $this->setupParty();
        Sanctum::actingAs($party['student']);

        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
        $this->postJson('/api/v1/student/attendance/clock-in')->assertStatus(422);
    }

    public function test_duplicate_clock_out_is_rejected_and_keeps_first_timeout(): void
    {
        $party = $this->setupParty();
        Sanctum::actingAs($party['student']);
        Carbon::setTestNow(Carbon::parse('2026-09-05 08:07:00'));
        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();

        Carbon::setTestNow(Carbon::parse('2026-09-05 17:03:00'));
        $first = $this->postJson('/api/v1/student/attendance/clock-out')->assertOk();
        $stored = $first->json('record.clock_out');

        Carbon::setTestNow(Carbon::parse('2026-09-05 17:10:00'));
        $this->postJson('/api/v1/student/attendance/clock-out')->assertStatus(422);

        $log = AttendanceLog::first();
        $this->assertSame($stored, $log->clock_out);
        $this->assertStringStartsWith('17:03', (string) $log->clock_out);

        Carbon::setTestNow();
    }

    public function test_clock_in_before_and_after_scheduled_start_stores_actual_time(): void
    {
        $party = $this->setupParty();
        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/schedules', [
            'start_time' => '08:00',
            'end_time' => '17:00',
        ])->assertCreated();
        Sanctum::actingAs($party['supervisor']);
        $this->patchJson('/api/v1/supervisor/dtr/schedules/'.WorkSchedule::first()->id, [
            'action' => 'approved',
        ])->assertOk();

        Sanctum::actingAs($party['student']);
        Carbon::setTestNow(Carbon::parse('2026-09-07 07:53:00'));
        $early = $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
        $this->assertStringStartsWith('07:53', (string) $early->json('record.clock_in'));
        $this->assertStringStartsWith('07:53', (string) AttendanceLog::first()->clock_in);

        Carbon::setTestNow(Carbon::parse('2026-09-08 08:07:00'));
        $late = $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
        $this->assertStringStartsWith('08:07', (string) $late->json('record.clock_in'));
        $this->assertStringStartsWith('08:07', (string) AttendanceLog::query()->latest('id')->value('clock_in'));

        Carbon::setTestNow(Carbon::parse('2026-09-08 17:12:00'));
        $out = $this->postJson('/api/v1/student/attendance/clock-out')->assertOk();
        $this->assertStringStartsWith('17:12', (string) $out->json('record.clock_out'));
        $this->assertStringStartsWith('17:12', (string) AttendanceLog::query()->latest('id')->value('clock_out'));

        Carbon::setTestNow();
    }

    public function test_attendance_get_includes_manila_server_time(): void
    {
        $party = $this->setupParty();
        Sanctum::actingAs($party['student']);
        $this->getJson('/api/v1/student/attendance')
            ->assertOk()
            ->assertJsonPath('server_timezone', 'Asia/Manila')
            ->assertJsonStructure(['server_now', 'server_now_display']);
    }

    public function test_supervisor_can_validate_clocked_out_record(): void
    {
        $party = $this->setupParty();
        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
        $out = $this->postJson('/api/v1/student/attendance/clock-out')->assertOk();
        $logId = $out->json('record.id');

        Sanctum::actingAs($party['supervisor']);
        $this->patchJson("/api/v1/supervisor/attendance/{$logId}/validate", [
            'action' => 'validated',
        ])->assertOk()->assertJsonPath('record.status', 'validated');
    }

    public function test_wrong_supervisor_gets_403(): void
    {
        $party = $this->setupParty();
        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
        $out = $this->postJson('/api/v1/student/attendance/clock-out')->assertOk();
        $logId = $out->json('record.id');

        Sanctum::actingAs($party['otherSupervisor']);
        $this->patchJson("/api/v1/supervisor/attendance/{$logId}/validate", [
            'action' => 'validated',
        ])->assertForbidden();
    }
}
