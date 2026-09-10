<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Convert Asia/Manila wall-clock times to the TIME values InternTrack stores
 * (app timezone = UTC), matching live clock-in via now().
 *
 * Credited hours for a standard OJT day are AM session + PM session so a
 * 12:00–13:00 lunch gap is not counted. The system does not auto-subtract lunch
 * from a single 08:00–17:00 span.
 */
final class ManilaAttendanceClock
{
    public static function storedTime(string $manilaClock): string
    {
        return Carbon::parse('2000-06-15 '.self::normalize($manilaClock), ManilaTime::TZ)
            ->timezone(config('app.timezone', 'UTC'))
            ->format('H:i:s');
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
}
