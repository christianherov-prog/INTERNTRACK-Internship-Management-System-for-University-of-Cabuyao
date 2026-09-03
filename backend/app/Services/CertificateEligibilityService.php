<?php

namespace App\Services;

use App\Models\Internship;

class CertificateEligibilityService
{
    /**
     * Determine whether a student is eligible to receive their
     * OJT Completion Certificate.
     *
     * All four conditions must be true:
     *  1. Internship status is 'completed'
     *  2. Rendered hours >= target hours
     *  3. All required documents are approved (none pending/rejected)
     *  4. At least one FO-24 supervisor evaluation has been submitted
     */
    public static function isEligible(Internship $internship): bool
    {
        // 1. Internship must be officially completed
        if ($internship->status !== 'completed') {
            return false;
        }

        // 2. Hours must meet or exceed the target
        $hoursComplete = (float) $internship->total_hours_rendered
                       >= (float) $internship->target_hours;
        if (!$hoursComplete) {
            return false;
        }

        // 3. No documents should remain in a non-approved state
        $hasUnapprovedDocs = $internship->documents()
            ->whereNotIn('status', ['approved'])
            ->exists();
        if ($hasUnapprovedDocs) {
            return false;
        }

        // 4. Supervisor must have submitted at least one FO-24 evaluation
        $hasSupervisorEval = $internship->evaluations()
            ->where('form_type', 'FO-24')
            ->exists();
        if (!$hasSupervisorEval) {
            return false;
        }

        return true;
    }

    /**
     * Return a structured checklist of eligibility requirements
     * so the frontend can display what is missing.
     */
    public static function checklist(Internship $internship): array
    {
        $hoursComplete = (float) $internship->total_hours_rendered
                       >= (float) $internship->target_hours;

        $hasUnapprovedDocs = $internship->documents()
            ->whereNotIn('status', ['approved'])
            ->exists();

        $hasSupervisorEval = $internship->evaluations()
            ->where('form_type', 'FO-24')
            ->exists();

        return [
            [
                'label'   => 'Internship status is Completed',
                'passed'  => $internship->status === 'completed',
            ],
            [
                'label'   => 'Required hours rendered ('
                           . (int) $internship->total_hours_rendered . ' / '
                           . (int) $internship->target_hours . ' hrs)',
                'passed'  => $hoursComplete,
            ],
            [
                'label'   => 'All required documents approved',
                'passed'  => !$hasUnapprovedDocs,
            ],
            [
                'label'   => 'Supervisor evaluation (FO-24) submitted',
                'passed'  => $hasSupervisorEval,
            ],
        ];
    }
}
