<?php

namespace App\Support;

use App\Models\Internship;
use App\Models\User;

/**
 * Single authoritative evaluation-period representation.
 *
 * Source of truth: internships.evaluation_period_status ('pending' | 'approved',
 * set by the assigned Faculty) on the student's CURRENT internship, resolved by
 * currentInternshipFor(). Student API, Faculty list, and the approve endpoint all
 * use this class so "which internship" and "what state" never diverge.
 *
 * Derived states (no new database enum values):
 *  - approved                 Faculty approved; forms unlock per their own rules.
 *  - pending_faculty_approval Placed/active internship waiting for Faculty.
 *  - not_yet_eligible         Still pending placement and not approved.
 *  - closed                   Internship ended without completion (cancelled, etc.).
 */
final class EvaluationPeriod
{
    public const APPROVED = 'approved';

    public const PENDING = 'pending_faculty_approval';

    public const NOT_YET_ELIGIBLE = 'not_yet_eligible';

    public const CLOSED = 'closed';

    private const CLOSED_STATUSES = ['cancelled', 'terminated', 'withdrawn', 'failed'];

    /**
     * The student's current internship: the latest open row, otherwise the
     * latest current-relation row (e.g. completed). Same rule the Student API uses.
     */
    public static function currentInternshipFor(int $studentId): ?Internship
    {
        return InternshipProvisioning::openForStudent($studentId)
            ?? User::query()->find($studentId)?->activeInternship()->first();
    }

    public static function isCurrentFor(Internship $internship): bool
    {
        return (int) self::currentInternshipFor((int) $internship->student_id)?->id === (int) $internship->id;
    }

    public static function state(Internship $internship): array
    {
        $raw = $internship->evaluation_period_status ?: 'pending';
        $status = InternshipStatuses::normalize($internship->status);

        $state = match (true) {
            in_array($status, self::CLOSED_STATUSES, true) => self::CLOSED,
            $raw === 'approved' => self::APPROVED,
            $status === 'pending_placement' => self::NOT_YET_ELIGIBLE,
            default => self::PENDING,
        };

        return [
            'internship_id' => $internship->id,
            'status' => $state,
            'raw_status' => $raw,
            'approved' => $state === self::APPROVED,
            'approved_at' => $internship->evaluation_period_approved_at?->toIso8601String(),
            'approved_by' => $internship->evaluation_period_approved_by,
            'label' => match ($state) {
                self::APPROVED => 'Approved',
                self::PENDING => 'Pending Faculty Approval',
                self::NOT_YET_ELIGIBLE => 'Not Yet Eligible',
                default => 'Closed',
            },
            'message' => match ($state) {
                self::APPROVED => null,
                self::PENDING => 'Waiting for Faculty approval of the evaluation period. Your forms stay locked until then.',
                self::NOT_YET_ELIGIBLE => 'Evaluation forms become available after your placement starts and your Faculty approves the evaluation period.',
                default => 'This internship is closed. Evaluation forms are no longer available.',
            },
        ];
    }

    /** Approve (or reset to pending) the period on the given internship. */
    public static function setApproved(Internship $internship, bool $approved, User $faculty): Internship
    {
        $internship->forceFill([
            'evaluation_period_status' => $approved ? 'approved' : 'pending',
            'evaluation_period_approved_by' => $approved ? $faculty->id : null,
            'evaluation_period_approved_at' => $approved ? now() : null,
        ])->save();

        return $internship->fresh();
    }
}
