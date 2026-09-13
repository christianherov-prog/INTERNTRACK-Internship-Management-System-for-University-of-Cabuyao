<?php

namespace App\Support;

/**
 * Canonical company / HTE organization types.
 *
 * Predefined keys are stored as slug values (e.g. "hospital").
 * Custom types are stored as the meaningful free-text the user entered.
 * UI sentinel "specify" / "Specify Organization Type" is never persisted.
 * Legacy "other" is treated as needing specification — never newly stored.
 */
class OrganizationTypes
{
    public const UNSPECIFIED = 'unspecified';

    /** UI-only sentinel — never write this to the database. */
    public const SPECIFY = 'specify';

    /** Legacy vague value — never newly store; surface as Needs Specification. */
    public const LEGACY_OTHER = 'other';

    public const PREDEFINED = [
        self::UNSPECIFIED => 'Unspecified',
        'industry' => 'Industry',
        'school' => 'School',
        'hospital' => 'Hospital',
        'government_office' => 'Government Office',
        'private_office' => 'Private Office',
        'ngo' => 'NGO',
    ];

    /** @deprecated Use PREDEFINED + SPECIFY UI option instead */
    public const OPTIONS = [
        'industry' => 'Industry',
        'school' => 'School',
        'hospital' => 'Hospital',
        'government_office' => 'Government Office',
        'private_office' => 'Private Office',
        'ngo' => 'NGO',
        self::UNSPECIFIED => 'Unspecified',
    ];

    public static function predefinedKeys(): array
    {
        return array_keys(self::PREDEFINED);
    }

    public static function isPredefined(?string $value): bool
    {
        $key = strtolower(trim((string) $value));

        return $key !== '' && array_key_exists($key, self::PREDEFINED);
    }

    public static function isLegacyOther(?string $value): bool
    {
        return strtolower(trim((string) $value)) === self::LEGACY_OTHER;
    }

    public static function needsSpecification(?string $value): bool
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return false;
        }

        return self::isLegacyOther($raw)
            || strcasecmp($raw, 'Specify Organization Type') === 0
            || strtolower($raw) === self::SPECIFY;
    }

    /**
     * Normalize an incoming organization_type for persistence.
     * Returns null for empty / unspecified.
     * Rejects UI sentinels and literal "Other".
     */
    public static function sanitize(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $lower = strtolower($raw);

        if ($lower === self::SPECIFY || strcasecmp($raw, 'Specify Organization Type') === 0) {
            return null;
        }

        if ($lower === self::LEGACY_OTHER) {
            return null;
        }

        if (array_key_exists($lower, self::PREDEFINED)) {
            return $lower === self::UNSPECIFIED ? null : $lower;
        }

        // Meaningful custom type — preserve user capitalization, clamp length
        $custom = preg_replace('/\s+/u', ' ', $raw) ?? $raw;
        $custom = trim($custom);
        if ($custom === '' || strcasecmp($custom, 'Other') === 0) {
            return null;
        }

        return mb_substr($custom, 0, 100);
    }

    /**
     * Laravel validation rule allowing predefined keys OR custom text (2–100 chars).
     * Does not allow literal "other" or the Specify sentinel as stored values when
     * combined with prepareForValidation / sanitize on the controller.
     */
    public static function validationRule(bool $required = false): string
    {
        $prefix = $required ? 'required' : 'nullable';

        return $prefix.'|string|max:100';
    }

    public static function label(?string $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return self::PREDEFINED[self::UNSPECIFIED];
        }

        if (self::isLegacyOther($raw) || strcasecmp($raw, 'Specify Organization Type') === 0) {
            return 'Needs Specification';
        }

        $lower = strtolower($raw);
        if (array_key_exists($lower, self::PREDEFINED)) {
            return self::PREDEFINED[$lower];
        }

        return $raw;
    }

    /**
     * Validate + sanitize for API writes. Throws ValidationException-friendly messages via array.
     *
     * @return array{ok: bool, value: ?string, message: ?string}
     */
    public static function resolveForStorage(?string $value, bool $required = false): array
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            if ($required) {
                return ['ok' => false, 'value' => null, 'message' => 'Organization Type is required.'];
            }

            return ['ok' => true, 'value' => null, 'message' => null];
        }

        $lower = strtolower($raw);

        if ($lower === self::SPECIFY || strcasecmp($raw, 'Specify Organization Type') === 0) {
            return ['ok' => false, 'value' => null, 'message' => 'Organization Type is required.'];
        }

        if ($lower === self::LEGACY_OTHER || strcasecmp($raw, 'Other') === 0) {
            return ['ok' => false, 'value' => null, 'message' => 'Please specify the actual type of organization.'];
        }

        $sanitized = self::sanitize($raw);
        if ($sanitized === null && $lower !== self::UNSPECIFIED) {
            return ['ok' => false, 'value' => null, 'message' => 'Organization Type is required.'];
        }

        return ['ok' => true, 'value' => $sanitized, 'message' => null];
    }
}
