<?php

namespace App\Support;

/**
 * Manuscript Scope statuses + operational placement statuses.
 */
final class InternshipStatuses
{
    /** Scope-required tags (staff can set these with a mandatory reason). */
    public const SCOPE = [
        'active',
        'completed',
        'suspended',
        'deferred',
        'expelled',
    ];

    /** All valid persisted values. */
    public const ALL = [
        'pending_placement',
        'placed',
        'ongoing', // legacy alias of active
        'active',
        'for_evaluation',
        'completed',
        'suspended',
        'deferred',
        'expelled',
        'terminated',
        'failed',
        'cancelled',
        'withdrawn',
    ];

    public static function normalize(?string $status): string
    {
        $status = strtolower(trim((string) $status));
        if ($status === 'ongoing') {
            return 'active';
        }

        return $status;
    }

    public static function label(?string $status): string
    {
        $status = self::normalize($status);

        return match ($status) {
            'pending_placement' => 'Pending Placement',
            'placed' => 'Placed',
            'active' => 'Active',
            'for_evaluation' => 'For Evaluation',
            'completed' => 'Completed',
            'suspended' => 'Suspended',
            'deferred' => 'Deferred',
            'expelled' => 'Expelled',
            'terminated' => 'Terminated',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            'withdrawn' => 'Withdrawn',
            default => $status !== '' ? ucwords(str_replace('_', ' ', $status)) : 'Unknown',
        };
    }

    /** Statuses that still count as the student's current internship record. */
    public static function currentRelation(): array
    {
        return [
            'pending_placement',
            'placed',
            'ongoing',
            'active',
            'for_evaluation',
            'suspended',
            'deferred',
            'completed', // keep visible for status history, portfolio and absorption on roster
            'expelled',
        ];
    }

    /**
     * Live / in-progress placements for coordinator monitoring counts & lists.
     * Includes legacy `ongoing` (normalized alias of `active`).
     */
    public static function liveMonitoring(): array
    {
        return ['ongoing', 'active', 'placed', 'for_evaluation'];
    }

    /**
     * Currently open internships that block creating another "current" row.
     * Historical completed/cancelled records are excluded so sequential HTE
     * deployments can start after a finished internship. Multi-HTE programs
     * still use placement rows on a single open internship.
     */
    public static function openCurrent(): array
    {
        return [
            'pending_placement',
            'placed',
            'ongoing',
            'active',
            'for_evaluation',
            'suspended',
            'deferred',
            'expelled',
        ];
    }
}
