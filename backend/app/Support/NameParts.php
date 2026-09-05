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
