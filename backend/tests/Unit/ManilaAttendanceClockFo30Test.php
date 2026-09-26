<?php

namespace Tests\Unit;

use App\Models\AttendanceLog;
use App\Support\ManilaAttendanceClock;
use App\Support\ManilaTime;
use Carbon\Carbon;
use Tests\Support\IsolatedTestCase;

class ManilaAttendanceClockFo30Test extends IsolatedTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function log(array $attrs): AttendanceLog
    {
        $log = new AttendanceLog;
        foreach ($attrs as $key => $value) {
            $log->setAttribute($key, $value);
        }

        return $log;
    }

    private function stored(string $manilaClock): string
    {
        return ManilaAttendanceClock::storedTime($manilaClock);
    }

    private function breakAt(string $date, string $manilaClock): Carbon
    {
        return Carbon::parse($date.' '.ManilaAttendanceClock::storedTime($manilaClock), config('app.timezone', 'UTC'));
    }

    public function test_eight_am_is_am_and_one_oh_three_pm_is_pm(): void
    {
        $am = ManilaTime::fromStoredDateAndTime('2026-09-09', $this->stored('08:00'));
        $pm = ManilaTime::fromStoredDateAndTime('2026-09-09', $this->stored('13:03'));
        $noon = ManilaTime::fromStoredDateAndTime('2026-09-09', $this->stored('12:00'));
        $elevenFiftyNine = ManilaTime::fromStoredDateAndTime('2026-09-09', $this->stored('11:59'));

        $this->assertTrue(ManilaAttendanceClock::isAm($am));
        $this->assertTrue(ManilaAttendanceClock::isAm($elevenFiftyNine));
        $this->assertTrue(ManilaAttendanceClock::isPm($noon));
        $this->assertTrue(ManilaAttendanceClock::isPm($pm));
    }

    public function test_early_morning_utc_clock_stays_on_the_manila_attendance_date(): void
    {
        $at = ManilaTime::fromStoredDateAndTime('2026-09-05', $this->stored('07:00'));

        $this->assertSame('2026-09-05 07:00', $at->format('Y-m-d H:i'));
        $this->assertSame('2026-09-04 23:00', $at->copy()->utc()->format('Y-m-d H:i'));
    }

    public function test_afternoon_only_leaves_am_blank_not_absent(): void
    {
        $cols = ManilaAttendanceClock::fo30Columns($this->log([
            'date' => '2026-09-09',
            'clock_in' => $this->stored('13:01'),
            'clock_out' => $this->stored('13:03'),
            'am_time_in' => $this->stored('13:01'),
            'am_time_out' => $this->stored('13:03'),
        ]));

        $this->assertFalse($cols['am_absent']);
        $this->assertFalse($cols['day_absent']);
        $this->assertNull($cols['am_time_in']);
        $this->assertNull($cols['am_time_out']);
        $this->assertSame('13:01', $cols['pm_time_in']);
        $this->assertSame('13:03', $cols['pm_time_out']);
    }

    public function test_pm_clock_out_never_appears_under_am_time_out(): void
    {
        $cols = ManilaAttendanceClock::fo30Columns($this->log([
            'date' => '2026-09-09',
            'clock_in' => $this->stored('13:01'),
            'clock_out' => $this->stored('17:00'),
            'am_time_in' => $this->stored('13:01'),
            'am_time_out' => $this->stored('17:00'),
        ]));

        $this->assertNull($cols['am_time_in']);
        $this->assertNull($cols['am_time_out']);
        $this->assertSame('13:01', $cols['pm_time_in']);
        $this->assertSame('17:00', $cols['pm_time_out']);
    }

    public function test_normal_full_day_with_break_resume(): void
    {
        $cols = ManilaAttendanceClock::fo30Columns($this->log([
            'date' => '2026-09-09',
            'clock_in' => $this->stored('08:02'),
            'break_start' => $this->breakAt('2026-09-09', '11:58'),
            'break_end' => $this->breakAt('2026-09-09', '13:01'),
            'clock_out' => $this->stored('17:04'),
            'am_time_in' => $this->stored('08:02'),
            'am_time_out' => $this->stored('17:04'),
        ]));

        $this->assertSame('08:02', $cols['am_time_in']);
        $this->assertSame('11:58', $cols['am_time_out']);
        $this->assertSame('13:01', $cols['pm_time_in']);
        $this->assertSame('17:04', $cols['pm_time_out']);
    }

    public function test_morning_only_does_not_fabricate_pm(): void
    {
        $cols = ManilaAttendanceClock::fo30Columns($this->log([
            'date' => '2026-09-09',
            'clock_in' => $this->stored('08:00'),
            'clock_out' => $this->stored('11:30'),
            'am_time_in' => $this->stored('08:00'),
            'am_time_out' => $this->stored('11:30'),
        ]));

        $this->assertSame('08:00', $cols['am_time_in']);
        $this->assertSame('11:30', $cols['am_time_out']);
        $this->assertNull($cols['pm_time_in']);
        $this->assertNull($cols['pm_time_out']);
    }

    public function test_cross_noon_without_break_does_not_fabricate_lunch(): void
    {
        $cols = ManilaAttendanceClock::fo30Columns($this->log([
            'date' => '2026-09-09',
            'clock_in' => $this->stored('11:30'),
            'clock_out' => $this->stored('13:30'),
            'am_time_in' => $this->stored('11:30'),
            'am_time_out' => $this->stored('13:30'),
        ]));

        $this->assertSame('11:30', $cols['am_time_in']);
        $this->assertNull($cols['am_time_out']);
        $this->assertNull($cols['pm_time_in']);
        $this->assertSame('13:30', $cols['pm_time_out']);
    }

    public function test_late_morning_clock_in_is_not_am_absent(): void
    {
        $cols = ManilaAttendanceClock::fo30Columns($this->log([
            'date' => '2026-09-09',
            'clock_in' => $this->stored('11:00'),
            'clock_out' => null,
            'am_time_in' => $this->stored('11:00'),
            'am_time_out' => null,
        ]));

        $this->assertFalse($cols['am_absent']);
        $this->assertSame('11:00', $cols['am_time_in']);
    }

    public function test_exact_noon_clock_in_is_pm_not_day_absent(): void
    {
        $cols = ManilaAttendanceClock::fo30Columns($this->log([
            'date' => '2026-09-09',
            'clock_in' => $this->stored('12:00'),
            'clock_out' => $this->stored('17:00'),
            'am_time_in' => $this->stored('12:00'),
            'am_time_out' => $this->stored('17:00'),
        ]));

        $this->assertFalse($cols['day_absent']);
        $this->assertNull($cols['am_time_in']);
        $this->assertSame('12:00', $cols['pm_time_in']);
        $this->assertSame('17:00', $cols['pm_time_out']);
    }

    public function test_hours_helper_unchanged_for_canonical_credit_math(): void
    {
        $this->assertSame(8.0, ManilaAttendanceClock::creditedHours('08:00', '12:00', '13:00', '17:00'));
    }
}
