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
    /**
     * Standard 13 UC Internship required documents according to the university manual.
     * @return list<string>
     */
    public static function defaultTypes(): array
    {
        return [
            'Registration Form',
            'Medical Clearance',
            'Psychological Assessment Certificate',
            'Application Letter',
            'Curriculum Vitae (PNC:AA-FO-27)',
            'Internship Host Establishment Request for Recommendation Letter (PNC:AA-FO-26)',
            'Student Internship Acceptance Form (PNC:AA-FO-29)',
            'Notarized Student Internship Consent Form (PNC:AA-FO-28)',
            'Internship Training Plan (PNC:AA-FO-25.3)',
            'Student Internship Daily Time Record (PNC:AA-FO-30)',
            'Memorandum of Agreement (MOA)',
            'Internship / OJT Visitation Form',
            'Certificate of Completion',
        ];
    }

    /** @return list<string> */
    public static function types(): array
    {
        return Cache::remember('ojt_requirements_types', 3600, function () {
            if (Schema::hasTable('ojt_requirement_templates')) {
                $names = OjtRequirementTemplate::active()->pluck('name')->toArray();
                if (!empty($names)) {
                    return $names;
                }
            }
            return self::defaultTypes();
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
        Cache::forget('ojt_requirements_types');
    }
}
