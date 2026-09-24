<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Internship;
use App\Models\WorkSchedule;
use App\Support\ManilaAttendanceClock;
use App\Support\ManilaTime;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Canonical schedule-aware attendance day resolution for FO-30 / DTR.
 *
 * Afternoon clock-in is present (not absent). Full-day Absent only when an
 * expected scheduled working day has ended with no valid work-start event.
 */
class AttendanceDayResolver
{
    public function __construct(
        protected DtrWorkflowService $dtr,
    ) {}

    public function hasValidWorkStart(?AttendanceLog $log): bool
    {
        if (! $log) {
            return false;
        }

        foreach (ManilaAttendanceClock::attendanceEvents($log) as $event) {
            if (($event['role'] ?? null) === 'in') {
                return true;
            }
        }

        return filled($log->clock_in) || filled($log->am_time_in) || filled($log->pm_time_in);
    }

    /**
     * Mon–Fri covered by an approved schedule (OJT default workweek).
     */
    public function isExpectedWorkingDay(Internship $internship, string $date): bool
    {
        $ymd = substr($date, 0, 10);
        $dow = (int) Carbon::parse($ymd.' 12:00:00', 'UTC')->dayOfWeek; // 0=Sun … 6=Sat
        if ($dow === 0 || $dow === 6) {
            return false;
        }

        if ($internship->start_date && $ymd < Carbon::parse($internship->start_date)->toDateString()) {
            return false;
        }
        if ($internship->end_date && $ymd > Carbon::parse($internship->end_date)->toDateString()) {
            return false;
        }

        return $this->dtr->activeScheduleFor($internship, $ymd) instanceof WorkSchedule;
    }

    public function scheduleEndAt(Internship $internship, string $date): ?CarbonInterface
    {
        $schedule = $this->dtr->activeScheduleFor($internship, $date);
        if (! $schedule || ! $schedule->end_time) {
            return null;
        }

        // Working-hour proposals are Manila wall-clock times (e.g. 17:00 = 5 PM local).
        $time = ManilaAttendanceClock::normalize((string) $schedule->end_time);

        try {
            return Carbon::parse(substr($date, 0, 10).' '.$time, ManilaTime::TZ);
        } catch (\Throwable) {
            return null;
        }
    }

    public function shiftHasEnded(Internship $internship, string $date, ?CarbonInterface $now = null): bool
    {
        $endAt = $this->scheduleEndAt($internship, $date);
        if (! $endAt) {
            return false;
        }

        $now = ($now ?? ManilaTime::now())->copy()->timezone(ManilaTime::TZ);

        return $now->greaterThanOrEqualTo($endAt);
    }

    /**
     * Full-day Absent: expected workday + shift ended + no valid work-start.
     */
    public function isFullDayAbsent(
        Internship $internship,
        string $date,
        ?AttendanceLog $log = null,
        ?CarbonInterface $now = null
    ): bool {
        $ymd = substr($date, 0, 10);
        $now = ($now ?? ManilaTime::now())->copy()->timezone(ManilaTime::TZ);

        if ($ymd > $now->toDateString()) {
            return false;
        }

        if (! $this->isExpectedWorkingDay($internship, $ymd)) {
            return false;
        }

        if ($this->hasValidWorkStart($log)) {
            return false;
        }

        return $this->shiftHasEnded($internship, $ymd, $now);
    }

    /**
     * @return array<string, mixed>
     */
    public function absentFo30Row(string $date): array
    {
        return [
            'id' => null,
            'date' => substr($date, 0, 10),
            'timezone' => ManilaTime::TZ,
            'am_time_in' => ManilaAttendanceClock::AM_ABSENT_LABEL,
            'am_time_out' => ManilaAttendanceClock::AM_ABSENT_OUT_PLACEHOLDER,
            'pm_time_in' => ManilaAttendanceClock::AM_ABSENT_OUT_PLACEHOLDER,
            'pm_time_out' => ManilaAttendanceClock::AM_ABSENT_OUT_PLACEHOLDER,
            'am_absent' => false,
            'day_absent' => true,
            'hours_rendered' => 0.0,
            'status' => 'absent',
            'validated' => false,
            'validated_at' => null,
            'hte_signature_path' => null,
            'student_signature_path' => null,
            'hte_signed_name' => null,
            'student_signed_name' => null,
        ];
    }

    /**
     * Merge real attendance rows with derived full-day absences through "today"
     * (Asia/Manila), without persisting absence records.
     *
     * @param  list<array<string, mixed>>  $serializedLogs
     * @param  \Illuminate\Support\Collection<int, AttendanceLog>|iterable<AttendanceLog>  $rawLogs
     * @return list<array<string, mixed>>
     */
    public function mergeFo30Attendance(
        Internship $internship,
        array $serializedLogs,
        iterable $rawLogs,
        ?CarbonInterface $now = null
    ): array {
        $now = ($now ?? ManilaTime::now())->copy()->timezone(ManilaTime::TZ);
        $today = $now->toDateString();

        $byDate = [];
        foreach ($serializedLogs as $row) {
            $d = substr((string) ($row['date'] ?? ''), 0, 10);
            if ($d !== '') {
                $byDate[$d] = $row;
            }
        }

        $rawByDate = [];
        foreach ($rawLogs as $log) {
            $d = ManilaTime::dateString($log->date) ?: ManilaTime::manilaDateString($log->date);
            if ($d) {
                $rawByDate[$d] = $log;
            }
        }

        $dates = array_keys($byDate);
        $scheduleStart = WorkSchedule::query()
            ->where('internship_id', $internship->id)
            ->whereIn('status', ['approved', 'superseded'])
            ->min('effective_from');
        if ($scheduleStart) {
            $scheduleStart = substr((string) $scheduleStart, 0, 10);
            if ($internship->start_date) {
                $scheduleStart = max($scheduleStart, $internship->start_date->toDateString());
            }
            if ($scheduleStart <= $today) {
                $dates[] = $scheduleStart;
            }
        }
        sort($dates);
        if ($dates === []) {
            return [];
        }

        $start = $dates[0];
        $end = max(end($dates), min($today, $internship->end_date
            ? Carbon::parse($internship->end_date)->toDateString()
            : $today));

        $cursor = Carbon::parse($start.' 00:00:00', 'UTC');
        $endAt = Carbon::parse($end.' 00:00:00', 'UTC');
        $merged = [];

        while ($cursor->lte($endAt)) {
            $ymd = $cursor->toDateString();
            if (isset($byDate[$ymd])) {
                $merged[] = $byDate[$ymd];
            } elseif ($this->isFullDayAbsent($internship, $ymd, $rawByDate[$ymd] ?? null, $now)) {
                $merged[] = $this->absentFo30Row($ymd);
            }
            $cursor->addDay();
        }

        return $merged;
    }
}
