<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Official PNC:AA-FO-31 date/week labels shared by the HTML journal PDF
 * and automated layout tests. Calendar dates are formatted in Asia/Manila.
 */
final class Fo31JournalPresenter
{
    public static function dateRange(mixed $start, mixed $end = null): string
    {
        $startAt = self::calendar($start);
        $endAt = self::calendar($end);
        if (! $startAt && ! $endAt) {
            return '';
        }
        if (! $endAt || ($startAt && $startAt->equalTo($endAt))) {
            return ($startAt ?? $endAt)->format('F j, Y');
        }
        if ($startAt->year === $endAt->year && $startAt->month === $endAt->month) {
            return $startAt->format('F j').'–'.$endAt->format('j, Y');
        }
        if ($startAt->year === $endAt->year) {
            return $startAt->format('F j').'–'.$endAt->format('F j, Y');
        }

        return $startAt->format('F j, Y').'–'.$endAt->format('F j, Y');
    }

    public static function weekLabel(mixed $weekNumber): string
    {
        if ($weekNumber === null || $weekNumber === '') {
            return '';
        }

        return 'Week '.(int) $weekNumber;
    }

    private static function calendar(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        try {
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
                return Carbon::createFromDate((int) $m[1], (int) $m[2], (int) $m[3], ManilaTime::TZ)->startOfDay();
            }

            return Carbon::parse($raw, ManilaTime::TZ)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
