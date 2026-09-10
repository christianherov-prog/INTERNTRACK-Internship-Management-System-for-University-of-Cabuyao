<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sequential supervisor login IDs: SUP-0001, SUP-0002, …
 *
 * Scans every users.faculty_number that looks like SUP-####, including inactive
 * and soft-deleted rows, so leftover codes cannot reset or recycle the sequence.
 */
final class SupervisorIds
{
    public const PREFIX = 'SUP-';

    public static function nextFacultyNumber(): string
    {
        return DB::transaction(function () {
            $locked = self::acquireAllocatorLock();

            try {
                return self::peekNext();
            } finally {
                if ($locked) {
                    self::releaseAllocatorLock();
                }
            }
        });
    }

    public static function ensureFor(User $user): string
    {
        if (filled($user->faculty_number)) {
            return (string) $user->faculty_number;
        }

        $locked = self::acquireAllocatorLock();

        try {
            $attempts = 0;
            while ($attempts < 25) {
                $code = self::peekNext();
                try {
                    $user->forceFill(['faculty_number' => $code])->save();

                    return $code;
                } catch (Throwable $e) {
                    $attempts++;
                    if ($attempts >= 25) {
                        throw $e;
                    }
                    $user->refresh();
                    if (filled($user->faculty_number)) {
                        return (string) $user->faculty_number;
                    }
                }
            }

            return (string) $user->faculty_number;
        } finally {
            if ($locked) {
                self::releaseAllocatorLock();
            }
        }
    }

    /**
     * Assign unique SUP-#### codes to supervisors that have a NULL faculty_number.
     * Never deletes accounts and never steals an ID that is already in use.
     *
     * @return list<array{user_id:int, email:?string, faculty_number:string}>
     */
    public static function backfillMissing(): array
    {
        $assigned = [];

        User::withTrashed()
            ->where('role', 'supervisor')
            ->whereNull('faculty_number')
            ->orderBy('id')
            ->get()
            ->each(function (User $user) use (&$assigned) {
                $code = self::ensureFor($user);
                $assigned[] = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'faculty_number' => $code,
                ];
            });

        return $assigned;
    }

    private static function peekNext(): string
    {
        $codes = User::withTrashed()
            ->whereNotNull('faculty_number')
            ->pluck('faculty_number');

        $max = 0;
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

    private static function acquireAllocatorLock(): bool
    {
        try {
            if (DB::getDriverName() === 'mysql') {
                $row = DB::select('SELECT GET_LOCK(?, 15) AS got', ['interntrack-supervisor-ids']);

                return (int) ($row[0]->got ?? 0) === 1;
            }

            User::withTrashed()
                ->whereNotNull('faculty_number')
                ->lockForUpdate()
                ->pluck('id');
        } catch (Throwable) {
            // Best-effort lock; collision loop still protects uniqueness.
        }

        return false;
    }

    private static function releaseAllocatorLock(): void
    {
        try {
            DB::select('SELECT RELEASE_LOCK(?)', ['interntrack-supervisor-ids']);
        } catch (Throwable) {
        }
    }
}
