<?php

namespace App\Support;

/**
 * Shared document workflow status labels for API payloads and UI parity.
 */
final class DocumentStatuses
{
    public const LABELS = [
        'not_submitted'   => 'Not Submitted',
        'pending_review'  => 'Coordinator Review',
        'under_review'    => 'Under Review',
        'pending_faculty' => 'Faculty Verification',
        'approved'        => 'Fully Approved',
        'rejected'        => 'Rejected',
        'resubmitted'     => 'Resubmitted',
    ];

    public static function label(?string $status): string
    {
        if ($status === null || $status === '') {
            return self::LABELS['not_submitted'];
        }

        return self::LABELS[$status] ?? str_replace('_', ' ', ucfirst($status));
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::LABELS;
    }
}
