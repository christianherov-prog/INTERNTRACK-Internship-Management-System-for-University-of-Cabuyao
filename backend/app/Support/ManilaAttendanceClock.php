<?php

namespace App\Support;

use App\Models\AttendanceLog;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Convert Asia/Manila wall-clock times to the TIME values InternTrack stores
 * (app timezone = UTC), matching live clock-in via now().
 *
 * Credited hours for a standard OJT day are AM session + PM session so a
 * 12:00–13:00 lunch gap is not counted. The system does not auto-subtract lunch
 * from a single 08:00–17:00 span.
 *
 * FO-30 AM/PM columns are derived from authoritative attendance events
 * (clock in / break / resume / clock out) classified in Asia/Manila —
 * never from punch sequence into am_time_* storage slots alone.
 */
final class ManilaAttendanceClock
{
    public const AM_ABSENT_LABEL = 'Absent';

    public const AM_ABSENT_OUT_PLACEHOLDER = '—';

    public static function storedTime(string $manilaClock): string
    {
        return Carbon::parse('2000-06-15 '.self::normalize($manilaClock), ManilaTime::TZ)
            ->timezone(config('app.timezone', 'UTC'))
            ->format('H:i:s');
    }

    /**
     * A Manila calendar date + Manila wall-clock time as a stored instant
     * (app timezone), e.g. 2026-06-03 12:00 Manila → 2026-06-03 04:00:00 UTC.
     * Used for break_start / break_end, which are datetime columns.
     */
    public static function storedInstant(mixed $manilaDate, string $manilaClock): Carbon
    {
        $day = Carbon::parse($manilaDate)->toDateString();

        return Carbon::parse($day.' '.self::normalize($manilaClock), ManilaTime::TZ)
            ->timezone(config('app.timezone', 'UTC'));
    }

    public static function minutesBetweenClocks(string $from, string $to): int
    {
        $start = Carbon::parse('2000-06-15 '.self::normalize($from), ManilaTime::TZ);
        $end = Carbon::parse('2000-06-15 '.self::normalize($to), ManilaTime::TZ);
        if ($end->lessThan($start)) {
            $end->addDay();
        }

        return (int) max(0, round($start->diffInMinutes($end, false)));
    }

    public static function creditedHours(string $amIn, string $amOut, string $pmIn, string $pmOut): float
    {
        $minutes = self::minutesBetweenClocks($amIn, $amOut) + self::minutesBetweenClocks($pmIn, $pmOut);

        return round($minutes / 60, 2);
    }

    public static function normalize(string $clock): string
    {
        $clock = trim($clock);
        if (preg_match('/^\d{1,2}:\d{2}$/', $clock)) {
            $clock .= ':00';
        }

        return $clock;
    }

    /**
     * Map an attendance log onto FO-30 AM/PM columns using Asia/Manila.
     *
     * AM = 00:00:00–11:59:59, PM = 12:00:00–23:59:59.
     * No noon split is invented. Afternoon-only attendance leaves AM blank
     * (dashes in the form) — it is NOT marked Absent.
     *
     * @return array{
     *   am_time_in: ?string,
     *   am_time_out: ?string,
     *   pm_time_in: ?string,
     *   pm_time_out: ?string,
     *   am_absent: bool,
     *   day_absent: bool
     * }
     */
    public static function fo30Columns(AttendanceLog $log): array
    {
        $events = self::attendanceEvents($log);
        $amIn = null;
        $amOut = null;
        $pmIn = null;
        $pmOut = null;

        // A day split by a break is a morning session and an afternoon
        // session: the break start closes the morning (AM Time Out) even when
        // it falls on or just after 12:00, and the resume opens the afternoon.
        $sessions = self::sessions($events);
        if (count($sessions) >= 2 && $sessions[0]['in'] && self::isAm($sessions[0]['in']) && $sessions[0]['out']) {
            $last = $sessions[count($sessions) - 1];

            return [
                'am_time_in' => ManilaTime::clockHm($sessions[0]['in']),
                'am_time_out' => ManilaTime::clockHm($sessions[0]['out']),
                'pm_time_in' => ManilaTime::clockHm($sessions[1]['in']),
                'pm_time_out' => ManilaTime::clockHm($last['out']),
                'am_absent' => false,
                'day_absent' => false,
            ];
        }

        foreach ($events as $event) {
            /** @var CarbonInterface $at */
            $at = $event['at'];
            $role = $event['role'];
            $hm = ManilaTime::clockHm($at);
            if ($hm === null) {
                continue;
            }

            if (self::isAm($at)) {
                if ($role === 'in' && $amIn === null) {
                    $amIn = $hm;
                }
                if ($role === 'out') {
                    $amOut = $hm;
                }
            } else {
                if ($role === 'in' && $pmIn === null) {
                    $pmIn = $hm;
                }
                if ($role === 'out') {
                    $pmOut = $hm;
                }
            }
        }

        return [
            'am_time_in' => $amIn,
            'am_time_out' => $amOut,
            'pm_time_in' => $pmIn,
            'pm_time_out' => $pmOut,
            'am_absent' => false,
            'day_absent' => false,
        ];
    }

    /**
     * Chronological events paired into work sessions (in → out).
     *
     * @param  list<array{role: 'in'|'out', at: CarbonInterface}>  $events
     * @return list<array{in: ?CarbonInterface, out: ?CarbonInterface}>
     */
    private static function sessions(array $events): array
    {
        $sessions = [];
        $open = null;
        foreach ($events as $event) {
            if ($event['role'] === 'in') {
                $open ??= ['in' => $event['at'], 'out' => null];

                continue;
            }
            if ($open === null) {
                $sessions[] = ['in' => null, 'out' => $event['at']];

                continue;
            }
            $open['out'] = $event['at'];
            $sessions[] = $open;
            $open = null;
        }
        if ($open !== null) {
            $sessions[] = $open;
        }

        return $sessions;
    }

    public static function isAm(CarbonInterface $at): bool
    {
        return (int) $at->timezone(ManilaTime::TZ)->hour < 12;
    }

    public static function isPm(CarbonInterface $at): bool
    {
        return ! self::isAm($at);
    }

    /**
     * Authoritative session events in chronological order.
     *
     * @return list<array{role: 'in'|'out', at: CarbonInterface}>
     */
    public static function attendanceEvents(AttendanceLog $log): array
    {
        $inRaw = $log->clock_in ?: $log->am_time_in;
        $outRaw = $log->clock_out ?: $log->pm_time_out ?: $log->am_time_out;

        $candidates = [
            ['role' => 'in', 'at' => self::resolveEventAt($log->date, $inRaw)],
            ['role' => 'out', 'at' => self::resolveEventAt($log->date, $log->break_start)],
            ['role' => 'in', 'at' => self::resolveEventAt($log->date, $log->break_end)],
            ['role' => 'out', 'at' => self::resolveEventAt($log->date, $outRaw)],
        ];

        $events = [];
        foreach ($candidates as $candidate) {
            if ($candidate['at'] instanceof CarbonInterface) {
                $events[] = $candidate;
            }
        }

        usort($events, function (array $a, array $b): int {
            $cmp = $a['at']->timestamp <=> $b['at']->timestamp;
            if ($cmp !== 0) {
                return $cmp;
            }

            // Same instant: prefer in before out so a resume+end edge stays stable.
            return ($a['role'] === 'in' ? 0 : 1) <=> ($b['role'] === 'in' ? 0 : 1);
        });

        return $events;
    }

    public static function resolveEventAt(mixed $date, mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value->copy()->timezone(ManilaTime::TZ);
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::parse($value)->timezone(ManilaTime::TZ);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            try {
                return Carbon::parse($raw, config('app.timezone', 'UTC'))->timezone(ManilaTime::TZ);
            } catch (\Throwable) {
                return null;
            }
        }

        return ManilaTime::fromStoredDateAndTime($date, $raw);
    }
}
