<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Internship;
use App\Models\WorkSchedule;
use App\Support\ManilaAttendanceClock;
use Carbon\Carbon;

/**
 * Writes attendance for the controlled demonstration dataset exactly the way
 * live attendance is stored: Asia/Manila wall-clock times are converted once
 * to the stored (app-timezone) representation, the lunch break is recorded as
 * real break instants, and credited hours come from the same calculation used
 * at clock-out (DtrWorkflowService::creditedHoursFor) against the internship's
 * approved schedule. Times vary realistically but deterministically (derived
 * from internship + date), so re-running produces identical rows.
 *
 * Production attendance never passes through this class.
 */
final class ControlledAttendanceWriter
{
    public const STANDARD_START = '08:00';

    public const STANDARD_END = '17:00';

    public const LUNCH_START = '12:00';

    public const LUNCH_MINUTES = 60;

    public function __construct(private DtrWorkflowService $dtr) {}

    /** Approved Manila 08:00–17:00 schedule from the internship start (idempotent). */
    public function ensureStandardSchedule(Internship $internship, int $supervisorId, string $effectiveFrom): WorkSchedule
    {
        $existing = WorkSchedule::query()
            ->where('internship_id', $internship->id)
            ->where('status', 'approved')
            ->orderBy('id')
            ->first();

        if ($existing) {
            if ($existing->effective_from && $existing->effective_from->toDateString() > $effectiveFrom) {
                $existing->forceFill(['effective_from' => $effectiveFrom])->save();
            }

            return $existing;
        }

        return WorkSchedule::create([
            'internship_id' => $internship->id,
            'proposed_by' => $internship->student_id,
            'start_time' => self::STANDARD_START.':00',
            'end_time' => self::STANDARD_END.':00',
            'status' => 'approved',
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'reviewed_by' => $supervisorId,
            'reviewed_at' => Carbon::parse($effectiveFrom, 'Asia/Manila')->setTime(8, 0)->utc(),
            'review_remarks' => 'Standard HTE working hours.',
        ]);
    }

    /**
     * Realistic Manila clock times for one day. A full day arrives up to eight
     * minutes early and leaves up to five minutes late (neither is credited);
     * a partial day is a morning session ending at noon.
     *
     * @return array{in: string, out: string, break_start: ?string, break_end: ?string}
     */
    public function dayTimes(int $internshipId, string $date, bool $partialMorning): array
    {
        $seed = crc32($internshipId.'|'.$date);
        $in = Carbon::parse('2000-01-01 '.self::STANDARD_START)->subMinutes($seed % 9)->format('H:i');

        if ($partialMorning) {
            return ['in' => $in, 'out' => self::LUNCH_START, 'break_start' => null, 'break_end' => null];
        }

        $out = Carbon::parse('2000-01-01 '.self::STANDARD_END)->addMinutes(intdiv($seed, 9) % 6)->format('H:i');
        $breakStart = Carbon::parse('2000-01-01 '.self::LUNCH_START)->addMinutes(intdiv($seed, 54) % 3);

        return [
            'in' => $in,
            'out' => $out,
            'break_start' => $breakStart->format('H:i'),
            'break_end' => $breakStart->copy()->addMinutes(self::LUNCH_MINUTES)->format('H:i'),
        ];
    }

    /**
     * Write (or rewrite) one validated day from Manila wall-clock times.
     * Status, validator and signature fields of an existing row are kept.
     */
    public function writeDay(Internship $internship, string $date, array $times, int $supervisorId): AttendanceLog
    {
        $breakStart = $times['break_start'] ? ManilaAttendanceClock::storedInstant($date, $times['break_start']) : null;
        $breakEnd = $times['break_end'] ? ManilaAttendanceClock::storedInstant($date, $times['break_end']) : null;
        $in = ManilaAttendanceClock::storedTime($times['in']);
        $out = ManilaAttendanceClock::storedTime($times['out']);

        $payload = [
            'clock_in' => $in,
            'clock_out' => $out,
            'break_start' => $breakStart,
            'break_end' => $breakEnd,
            'on_break' => false,
            // Session slots mirror the real sessions (FO-30 still derives its
            // AM/PM columns from the events in Asia/Manila).
            'am_time_in' => $in,
            'am_time_out' => $breakStart ? $breakStart->format('H:i:s') : $out,
            'pm_time_in' => $breakEnd?->format('H:i:s'),
            'pm_time_out' => $breakEnd ? $out : null,
            'hours_rendered' => $this->dtr->creditedHoursFor($internship, $date, $in, $out, $breakStart, $breakEnd),
            'overtime_hours' => 0,
        ];

        $log = AttendanceLog::withTrashed()
            ->where('internship_id', $internship->id)
            ->whereDate('date', $date)
            ->first();

        if ($log) {
            if ($log->trashed()) {
                $log->restore();
            }
            $log->update($payload);

            return $log->fresh();
        }

        return AttendanceLog::create(array_merge($payload, [
            'internship_id' => $internship->id,
            'placement_id' => $internship->current_placement_id,
            'date' => $date,
            'status' => 'validated',
            'validated_by' => $supervisorId,
            'validated_at' => Carbon::parse($date, 'Asia/Manila')->addDay()->setTime(9, 0)->utc(),
        ]));
    }

    /**
     * Rows written by the earlier controlled-data generator stored Manila
     * wall-clock times as if they were app-timezone times (so 08:00–17:00
     * displayed as 4:00 PM–1:00 AM) and carried no break. They are
     * recognisable because live attendance always fills am_time_in at
     * clock-in. Rewrites them in place, keeping date, status and validator;
     * a 4-hour row stays a morning session. Returns the number repaired.
     */
    public function repairLegacyRows(Internship $internship, int $supervisorId): int
    {
        $legacy = AttendanceLog::query()
            ->where('internship_id', $internship->id)
            ->whereNull('am_time_in')
            ->whereNull('break_start')
            ->whereNotNull('clock_in')
            ->whereNotNull('clock_out')
            ->orderBy('date')
            ->get();

        foreach ($legacy as $log) {
            $date = Carbon::parse($log->date)->toDateString();
            $partial = (float) $log->hours_rendered <= 4.0;
            $this->writeDay($internship, $date, $this->dayTimes($internship->id, $date, $partial), $supervisorId);
        }

        return $legacy->count();
    }
}
