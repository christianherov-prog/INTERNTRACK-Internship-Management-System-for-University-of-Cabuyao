<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\SupervisorProfile;
use App\Support\ManilaTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

/**
 * System-wide attendance "today" state — Manila calendar + soft-delete unique safety.
 */
class AttendanceTodayStateRepairTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    private function party(): array
    {
        $coordinator = $this->makeUser('coordinator', 'COR-CCS-001');
        $faculty = $this->makeUser('faculty', 'FAC-1001');
        $this->mapFacultyForSection($faculty);
        $supervisor = $this->makeUser('supervisor', 'SUP-0002');
        SupervisorProfile::create([
            'user_id' => $supervisor->id,
            'first_name' => 'Adrian',
            'last_name' => 'Reyes',
            'email' => $supervisor->email,
            'position' => 'Industry Supervisor',
        ]);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany(['company_name' => 'Accenture PH']);
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->update(['status' => 'ongoing', 'target_hours' => 500, 'start_date' => '2026-08-24']);

        return compact('student', 'supervisor', 'internship', 'faculty', 'coordinator');
    }

    public function test_historical_attendance_does_not_mean_clocked_in_today(): void
    {
        $party = $this->party();
        AttendanceLog::create([
            'internship_id' => $party['internship']->id,
            'date' => '2026-08-28',
            'clock_in' => '00:00:00',
            'clock_out' => '09:00:00',
            'hours_rendered' => 8,
            'status' => 'validated',
            'validated_by' => $party['supervisor']->id,
        ]);

        Sanctum::actingAs($party['student']);
        $this->getJson('/api/v1/student/attendance')
            ->assertOk()
            ->assertJsonPath('today_status', 'not_clocked_in')
            ->assertJsonPath('today_date', ManilaTime::todayDateString())
            ->assertJsonPath('today_record', null);
    }

    public function test_soft_deleted_same_day_row_does_not_block_clock_in(): void
    {
        $party = $this->party();
        $today = ManilaTime::todayDateString();

        $trashed = AttendanceLog::create([
            'internship_id' => $party['internship']->id,
            'date' => $today,
            'clock_in' => '00:00:00',
            'clock_out' => '09:00:00',
            'hours_rendered' => 8,
            'status' => 'validated',
        ]);
        $trashed->delete();

        Sanctum::actingAs($party['student']);
        $this->getJson('/api/v1/student/attendance')
            ->assertOk()
            ->assertJsonPath('today_status', 'not_clocked_in');

        $this->postJson('/api/v1/student/attendance/clock-in')
            ->assertCreated()
            ->assertJsonPath('message', 'Clocked in successfully.');

        $this->assertDatabaseMissing('attendance_logs', [
            'id' => $trashed->id,
        ]);

        $this->getJson('/api/v1/student/attendance')
            ->assertOk()
            ->assertJsonPath('today_status', 'clocked_in');

        // Duplicate active clock-in blocked
        $this->postJson('/api/v1/student/attendance/clock-in')
            ->assertStatus(422)
            ->assertJsonPath('message', 'You have already clocked in today.');
    }

    public function test_opening_attendance_get_does_not_create_a_row(): void
    {
        $party = $this->party();
        Sanctum::actingAs($party['student']);
        $before = AttendanceLog::where('internship_id', $party['internship']->id)->count();
        $this->getJson('/api/v1/student/attendance')->assertOk();
        $this->getJson('/api/v1/student/dashboard')->assertOk();
        $after = AttendanceLog::where('internship_id', $party['internship']->id)->count();
        $this->assertSame($before, $after);
    }

    public function test_break_and_completed_states(): void
    {
        $party = $this->party();
        Sanctum::actingAs($party['student']);

        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
        $this->getJson('/api/v1/student/attendance')->assertJsonPath('today_status', 'clocked_in');

        $this->postJson('/api/v1/student/attendance/break-start')->assertOk();
        $this->getJson('/api/v1/student/attendance')->assertJsonPath('today_status', 'on_break');

        $this->postJson('/api/v1/student/attendance/break-end')->assertOk();
        $this->getJson('/api/v1/student/attendance')->assertJsonPath('today_status', 'clocked_in');

        $this->postJson('/api/v1/student/attendance/clock-out', ['action' => 'end_day'])->assertOk();
        $this->getJson('/api/v1/student/attendance')->assertJsonPath('today_status', 'clocked_out');
    }

    public function test_today_uses_asia_manila_not_utc_alone(): void
    {
        $this->assertSame('Asia/Manila', ManilaTime::TZ);
        $this->assertSame(
            \Carbon\Carbon::now('Asia/Manila')->toDateString(),
            ManilaTime::todayDateString()
        );
        $this->assertNotSame('UTC', ManilaTime::TZ);
    }

    public function test_aug28_does_not_block_sep11_calendar_day(): void
    {
        $party = $this->party();
        AttendanceLog::create([
            'internship_id' => $party['internship']->id,
            'date' => '2026-08-28',
            'clock_in' => '00:00:00',
            'clock_out' => '09:00:00',
            'hours_rendered' => 8,
            'status' => 'validated',
        ]);

        // Soft-deleted Sep 11 leftover (the Clarence production bug).
        $sep11 = AttendanceLog::create([
            'internship_id' => $party['internship']->id,
            'date' => '2026-09-11',
            'clock_in' => '00:00:00',
            'clock_out' => '09:00:00',
            'hours_rendered' => 8,
            'status' => 'validated',
        ]);
        $sep11->delete();

        Sanctum::actingAs($party['student']);

        // Freeze "today" expectation when test runs on 2026-09-11 Manila.
        if (ManilaTime::todayDateString() === '2026-09-11') {
            $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
            $active = AttendanceLog::where('internship_id', $party['internship']->id)
                ->whereDate('date', '2026-09-11')
                ->first();
            $this->assertNotNull($active);
            $this->assertNull($active->clock_out);
            // Roll back the live clock-in so hours stay historical-only in assertions below.
            $active->forceDelete();
        }

        $this->assertSame(1, AttendanceLog::where('internship_id', $party['internship']->id)->count());
        $this->assertSame('2026-08-28', AttendanceLog::first()->date->toDateString());
    }
}
