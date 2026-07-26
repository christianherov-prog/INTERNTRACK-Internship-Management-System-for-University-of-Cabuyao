<?php

namespace App\Support;

/**
 * iEnroll / MISD is the source of truth for university account identity.
 * INTERNTRACK must not accept Settings/self-profile writes for these fields.
 * Supervisors are not in iEnroll and remain fully editable.
 */
class IenrollProfileLock
{
    public const IENROLL_ROLES = ['student', 'faculty', 'coordinator', 'director', 'admin'];

    /**
     * Request keys accepted by PUT /auth/profile that must never be written
     * for iEnroll roles.
     */
    public const LOCKED_REQUEST_KEYS = [
        'first_name',
        'last_name',
        'middle_name',
        'name',
        'email',
        'program',
        'department',
        'position',
        'company',
        'sex',
        'suffix',
        'section',
        'college',
        'year_level',
        'course_name',
        'academic_year',
        'semester',
        'enrollment_status',
        'employee_number',
        'employment_status',
        'birthday',
    ];

    public static function isIenrollRole(string $role): bool
    {
        return in_array($role, self::IENROLL_ROLES, true);
    }

    public static function isProfileEditable(string $role): bool
    {
        return !self::isIenrollRole($role);
    }

    /** @return list<string> */
    public static function lockedFields(string $role): array
    {
        if (!self::isIenrollRole($role)) {
            return [];
        }

        return self::LOCKED_REQUEST_KEYS;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function stripLocked(array $data, string $role): array
    {
        if (!self::isIenrollRole($role)) {
            return $data;
        }

        foreach (self::LOCKED_REQUEST_KEYS as $key) {
            unset($data[$key]);
        }

        return $data;
    }

    /**
     * True when the validated payload includes any identity field that iEnroll owns.
     *
     * @param  array<string, mixed>  $data
     */
    public static function containsLockedFields(array $data, string $role): bool
    {
        if (!self::isIenrollRole($role)) {
            return false;
        }

        foreach (self::LOCKED_REQUEST_KEYS as $key) {
            if (array_key_exists($key, $data)) {
                return true;
            }
        }

        return false;
    }

    public static function lockedSource(string $role): ?string
    {
        return self::isIenrollRole($role) ? 'ienroll' : null;
    }
}
