<?php

namespace App\Support;

/**
 * Build display names from iEnroll-style name parts.
 */
class NameParts
{
    /**
     * Western/Filipino display: First Middle Last Suffix
     */
    public static function display(
        ?string $first = null,
        ?string $middle = null,
        ?string $last = null,
        ?string $suffix = null
    ): string {
        $parts = array_filter([
            trim((string) $first),
            trim((string) $middle),
            trim((string) $last),
            trim((string) $suffix),
        ], fn ($p) => $p !== '');

        return implode(' ', $parts);
    }

    /**
     * Official-form display: LAST, FIRST M.
     */
    public static function lastFirst(?object $profile): string
    {
        if (! $profile) {
            return '';
        }
        $last = trim((string) ($profile->last_name ?? ''));
        $first = trim((string) ($profile->first_name ?? ''));
        if ($last === '' && $first === '') {
            return '';
        }
        $mi = trim((string) ($profile->middle_name ?? ''));
        $middle = $mi !== '' ? ' '.mb_strtoupper(mb_substr($mi, 0, 1)).'.' : '';

        return mb_strtoupper($last).', '.mb_strtoupper($first).$middle;
    }

    public static function fromProfile(?object $profile): string
    {
        if (!$profile) {
            return '';
        }

        return self::display(
            $profile->first_name ?? null,
            $profile->middle_name ?? null,
            $profile->last_name ?? null,
            $profile->suffix ?? null
        );
    }
}
