<?php

namespace App\Support;

use App\Models\OjtRequirementTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth for required internship documents (UC Internship Manual).
 */
final class RequiredDocuments
{
    /** @return list<string> */
    public static function types(): array
    {
        return Cache::remember('ojt_requirements_types', 3600, function () {
            if (! Schema::hasTable('ojt_requirement_templates')) {
                return self::canonicalTypeNames();
            }

            // Prefer system templates; include unique custom names without duplicating system codes.
            $templates = OjtRequirementTemplate::active()
                ->orderByDesc('is_system')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'is_system', 'system_code']);

            $seen = [];
            $names = [];
            foreach ($templates as $template) {
                $key = $template->system_code
                    ? 'code:'.$template->system_code
                    : 'name:'.strtolower(trim((string) $template->name));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $names[] = $template->name;
            }

            return $names !== [] ? $names : self::canonicalTypeNames();
        });
    }

    public static function count(): int
    {
        return count(self::types());
    }

    /** @return list<string> */
    public static function typesList(): array
    {
        return self::types();
    }

    public static function isRequired(string $type): bool
    {
        return in_array($type, self::types(), true);
    }

    /**
     * Return the default set of document type names when no templates exist.
     * Prefer the DB-backed types when available.
     *
     * @return list<string>
     */
    public static function defaultTypes(): array
    {
        $types = self::types();
        if (! empty($types)) {
            return $types;
        }

        return self::canonicalTypeNames();
    }

    /**
     * Canonical system template names (always the fixed InternTrack set).
     *
     * @return list<string>
     */
    public static function canonicalTypeNames(): array
    {
        return [
            'Application Letter',
            'Curriculum Vitae',
            'Recommendation Letter',
            'Acceptance Form',
            'Consent Form',
            'Training Plan',
            'Daily Time Record',
            'Performance Evaluation',
            'Memorandum of Agreement',
            'Visitation Form',
            'Certificate of Completion',
            'Host Evaluation',
            'OJT Photos',
        ];
    }

    public static function clearCache(): void
    {
        Cache::forget('ojt_requirements_types');
    }
}
