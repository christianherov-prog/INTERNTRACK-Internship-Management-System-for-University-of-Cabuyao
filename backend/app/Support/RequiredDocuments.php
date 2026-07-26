<?php

namespace App\Support;

/**
 * Single source of truth for required internship documents (UC Internship Manual).
 * Keep frontend StudentDocuments DOCUMENT_TYPES in sync via API required_types where possible.
 */
final class RequiredDocuments
{
    /** @var list<string> */
    public const DEFAULT_TYPES = [
        'Curriculum Vitae (PNC:AA-FO-27)',
        'Medical Clearance',
        'Psychological Assessment Certificate',
        'Notarized Student Internship Consent Form (PNC:AA-FO-28)',
        'Student Internship Acceptance Form (PNC:AA-FO-29)',
        'Application Letter',
        'Recommendation Letter',
        'MOA / LOA / TOR',
        'Company Profile',
        'Training Plan',
        'Midterm Evaluation',
        'Final Report',
        'Certificate of Completion',
    ];

    /** @return list<string> */
    public static function types(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('ojt_requirements_types', 3600, function () {
            if (\Illuminate\Support\Facades\Schema::hasTable('ojt_requirement_templates')) {
                $dbTypes = \App\Models\OjtRequirementTemplate::active()->pluck('name')->toArray();
                if (!empty($dbTypes)) {
                    return $dbTypes;
                }
            }
            return self::DEFAULT_TYPES;
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

    public static function clearCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget('ojt_requirements_types');
    }
}
