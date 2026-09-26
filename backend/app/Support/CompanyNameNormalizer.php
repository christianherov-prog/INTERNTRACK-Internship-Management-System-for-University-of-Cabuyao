<?php

namespace App\Support;

use App\Models\Company;

/**
 * Canonical-name matching for companies/HTEs, so "Accenture Philippines",
 * "accenture   philippines", and " Accenture Philippines " are recognized
 * as the same company (case/whitespace only) without merging genuinely
 * different businesses whose names merely look similar.
 *
 * Beyond case/whitespace, only the explicit ALIASES below are treated as the
 * same organization — there is deliberately no fuzzy matching.
 */
final class CompanyNameNormalizer
{
    /**
     * Known alternate spellings of a canonical organization, expressed in
     * normalize() form. Add an entry only when both names are certainly the
     * same legal organization.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'accenture ph' => 'accenture philippines',
    ];

    /** Trim, collapse internal whitespace runs, and lowercase for comparison. */
    public static function normalize(?string $name): string
    {
        $name = trim((string) $name);
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        return mb_strtolower($name);
    }

    /** Identity key: normalize() plus explicit alias resolution. Stored in companies.normalized_name. */
    public static function key(?string $name): string
    {
        $normalized = self::normalize($name);

        return self::ALIASES[$normalized] ?? $normalized;
    }

    /** First non-deleted company whose identity key matches, or null. */
    public static function findExisting(string $name, ?int $exceptId = null): ?Company
    {
        $key = self::key($name);
        if ($key === '') {
            return null;
        }

        $query = Company::query()->where('normalized_name', $key);
        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        $match = $query->orderBy('id')->first();
        if ($match) {
            return $match;
        }

        // Rows written outside Eloquent may not carry a key yet.
        $unkeyed = Company::query()->whereNull('normalized_name');
        if ($exceptId !== null) {
            $unkeyed->whereKeyNot($exceptId);
        }

        return $unkeyed->get(['id', 'company_name'])
            ->first(fn (Company $c) => self::key($c->company_name) === $key);
    }
}
