<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Internship;
use App\Models\InternshipApplication;
use App\Models\User;

/**
 * Whether a student may send new HTE applications.
 *
 * A student is locked out of new applications while their current (open)
 * internship has an accepted placement:
 *  - a Coordinator-approved application from the current internship cycle, or
 *  - the open internship itself is already placed / active / under evaluation.
 *
 * Eligibility is recomputed from authoritative records on every call, so a
 * placement that is later cancelled, terminated, withdrawn, or completed
 * through the existing workflows (the internship leaves the open statuses)
 * reopens applications automatically.
 */
final class PlacementEligibility
{
    public const LOCK_MESSAGE = 'Application unavailable while you have an active internship placement.';

    /** Open internship statuses that mean the student is already placed. */
    public const PLACED_STATUSES = ['placed', 'ongoing', 'active', 'for_evaluation', 'suspended', 'deferred'];

    /**
     * @return array{locked: bool, message: string|null, internship_id: int|null, company_id: int|null, company_name: string|null, source: string|null}
     */
    public static function forStudent(User $student, ?Internship $open = null): array
    {
        $open ??= InternshipProvisioning::openForStudent($student->id);

        $unlocked = [
            'locked' => false,
            'message' => null,
            'internship_id' => $open?->id,
            'company_id' => null,
            'company_name' => null,
            'source' => null,
        ];

        if (! $open) {
            return $unlocked;
        }

        $accepted = InternshipApplication::query()
            ->where('student_id', $student->id)
            ->where('status', 'approved')
            ->where('updated_at', '>=', $open->created_at)
            ->latest('updated_at')
            ->first();

        if ($accepted) {
            return self::locked($open, (int) $accepted->company_id, 'approved_application');
        }

        if (in_array($open->status, self::PLACED_STATUSES, true) && $open->company_id) {
            return self::locked($open, (int) $open->company_id, 'placement');
        }

        return $unlocked;
    }

    private static function locked(Internship $open, int $companyId, string $source): array
    {
        return [
            'locked' => true,
            'message' => self::LOCK_MESSAGE,
            'internship_id' => $open->id,
            'company_id' => $companyId,
            'company_name' => Company::query()->whereKey($companyId)->value('company_name'),
            'source' => $source,
        ];
    }
}
