<?php

namespace App\Support;

use App\Models\User;

/**
 * Sequential supervisor login IDs: SUP-0001, SUP-0002, …
 */
final class SupervisorIds
{
    public const PREFIX = 'SUP-';

    public static function nextFacultyNumber(): string
    {
        $max = 0;
        $codes = User::withTrashed()
            ->where('role', 'supervisor')
            ->pluck('faculty_number');

        foreach ($codes as $code) {
            if (preg_match('/^SUP-?(\d+)$/i', (string) $code, $match)) {
                $max = max($max, (int) $match[1]);
            }
        }

        $next = $max + 1;

        do {
            $candidate = self::PREFIX.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (User::withTrashed()->where('faculty_number', $candidate)->exists());

        return $candidate;
    }
}
