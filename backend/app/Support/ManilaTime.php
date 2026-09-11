<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Portfolio timestamps are always presented in Asia/Manila.
 * Source values are stored in the application timezone (UTC).
 */
final class ManilaTime
{
    public const TZ = 'Asia/Manila';

    public static function zone(): string
    {
        return self::TZ;
    }

    public static function now(): CarbonInterface
    {
        return Carbon::now(self::TZ);
    }

    /**
     * Current calendar date in Asia/Manila (authoritative "today" for attendance).
     */
    public static function todayDateString(?CarbonInterface $at = null): string
    {
        return ($at ?? self::now())->timezone(self::TZ)->toDateString();
    }

    /**
     * Combine a calendar date + clock time stored in app TZ and convert to Manila.
     */
    public static function fromStoredDateAndTime(mixed $date, ?string $time): ?CarbonInterface
    {
        $time = trim((string) $time);
        if ($time === '') {
            return null;
        }

        $datePart = self::dateString($date);
        if ($datePart === null) {
            return null;
        }

        $timePart = strlen($time) >= 8 ? substr($time, 0, 8) : $time;
        if (preg_match('/^\d{1,2}:\d{2}$/', $timePart)) {
            $timePart .= ':00';
        }

        try {
            return Carbon::parse($datePart.' '.$timePart, config('app.timezone', 'UTC'))
                ->timezone(self::TZ);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function clockHm(?CarbonInterface $at): ?string
    {
        return $at?->format('H:i');
    }

    public static function dateString(mixed $date): ?string
    {
        if ($date instanceof CarbonInterface) {
            return $date->timezone(config('app.timezone', 'UTC'))->toDateString();
        }

        $raw = trim((string) $date);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw, config('app.timezone', 'UTC'))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function manilaDateString(mixed $date, ?string $time = null): ?string
    {
        if ($time) {
            return self::fromStoredDateAndTime($date, $time)?->toDateString();
        }

        if ($date instanceof CarbonInterface) {
            return $date->copy()->timezone(self::TZ)->toDateString();
        }

        $raw = trim((string) $date);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw, config('app.timezone', 'UTC'))->timezone(self::TZ)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function isoNow(): string
    {
        return self::now()->toIso8601String();
    }

    public static function clockDisplay(?CarbonInterface $at = null): string
    {
        return ($at ?? self::now())->timezone(self::TZ)->format('g:i A');
    }

    public static function datetimeDisplay(?CarbonInterface $at): ?string
    {
        if (! $at) {
            return null;
        }

        return $at->copy()->timezone(self::TZ)->format('M j, Y g:i A');
    }
}
