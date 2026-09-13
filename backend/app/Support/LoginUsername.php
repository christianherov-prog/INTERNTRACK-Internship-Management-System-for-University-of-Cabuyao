<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Supervisor-chosen login usernames (distinct from auto SUP-#### faculty_number).
 */
class LoginUsername
{
    public const MIN = 3;

    public const MAX = 40;

    /** Lowercase letters, digits, dot, underscore, hyphen. */
    public const PATTERN = '/^[a-z0-9][a-z0-9._-]{1,38}[a-z0-9]$|^[a-z0-9]{3,40}$/';

    public static function normalize(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        return strtolower($raw);
    }

    public static function validateOrFail(?string $value, ?int $ignoreUserId = null): string
    {
        $normalized = self::normalize($value);
        if ($normalized === null) {
            throw ValidationException::withMessages([
                'login_username' => ['Username is required.'],
            ]);
        }

        if (strlen($normalized) < self::MIN || strlen($normalized) > self::MAX) {
            throw ValidationException::withMessages([
                'login_username' => ['Username must be between '.self::MIN.' and '.self::MAX.' characters.'],
            ]);
        }

        if (! preg_match(self::PATTERN, $normalized)) {
            throw ValidationException::withMessages([
                'login_username' => ['Username may only contain letters, numbers, dots, underscores, and hyphens.'],
            ]);
        }

        // Block values that collide with student/employee ID shapes.
        if (preg_match('/^\d{6,}$/', $normalized) || preg_match('/^(fac|cor|dir|admin|sup)-/i', $normalized)) {
            throw ValidationException::withMessages([
                'login_username' => ['This username format is reserved. Choose a different username.'],
            ]);
        }

        $taken = User::withTrashed()
            ->whereRaw('LOWER(login_username) = ?', [$normalized])
            ->when($ignoreUserId, fn ($q) => $q->whereKeyNot($ignoreUserId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'login_username' => ['This username is already taken.'],
            ]);
        }

        return $normalized;
    }
}
