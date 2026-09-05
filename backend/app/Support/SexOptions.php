<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Official sex values for INTERNTRACK profiles.
 * University roles sync from iEnroll; only supervisors may edit in-app.
 */
class SexOptions
{
    public const VALUES = ['Male', 'Female'];

    public static function isValid(?string $sex): bool
    {
        return $sex !== null && in_array($sex, self::VALUES, true);
    }

    /** Normalize MISD/iEnroll payload; invalid → null. */
    public static function sanitize(mixed $sex): ?string
    {
        if ($sex === null || $sex === '') {
            return null;
        }

        $normalized = ucfirst(strtolower(trim((string) $sex)));

        return self::isValid($normalized) ? $normalized : null;
    }

    public static function isEditableRole(string $role): bool
    {
        return $role === 'supervisor';
    }

    public static function validationRule(bool $required = false): array
    {
        $rule = $required ? ['required'] : ['sometimes', 'nullable'];
        $rule[] = Rule::in(self::VALUES);

        return $rule;
    }
}
