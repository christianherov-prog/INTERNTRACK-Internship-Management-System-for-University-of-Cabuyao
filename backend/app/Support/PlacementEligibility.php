<?php

namespace App\Support;

use App\Models\Company;
use App\Models\FacultyProfile;
use App\Models\Internship;
use App\Models\InternshipApplication;
use App\Models\User;
use App\Services\FacultySectionAssignmentService;

/**
 * Whether a student may send new HTE applications.
 *
 * A student is locked out of new applications to a DIFFERENT company once
 * they have a current application/placement for their open internship:
 *  - a pending or Coordinator-approved application from the current
 *    internship cycle (the moment a Student submits an application it
 *    becomes their one current selection — see 'source' below), or
 *  - the open internship itself is already placed / active / under evaluation, or
 *  - there is no open internship and the latest one was COMPLETED at a company
 *    (a finished OJT is never re-pointed to another company by a Student).
 *
 * Eligibility is recomputed from authoritative records on every call, so a
 * placement that is later withdrawn, cancelled or terminated through the
 * existing workflows (the internship leaves the open statuses, or the
 * application leaves pending/approved) reopens applications automatically,
 * as does a new internship cycle (a new open internship). Rejected and
 * withdrawn applications never lock.
 */
final class PlacementEligibility
{
    public const LOCK_MESSAGE = 'Application unavailable while you have an active internship placement.';

    public const ADVISER_MESSAGE = 'A Faculty adviser must be assigned before you can submit an internship application.';

    /**
     * Institutional rule: a Student must be under a Faculty adviser before
     * applying to a company or requesting a new HTE. The adviser is the
     * current internship's faculty_id (section assignment fills it by default).
     *
     * @return array{assigned: bool, faculty_id: int|null, name: string|null, message: string|null}
     */
    public static function adviserFor(User $student, ?Internship $internship = null): array
    {
        $internship ??= InternshipProvisioning::openForStudent($student->id)
            ?? Internship::where('student_id', $student->id)->latest('id')->first();

        $facultyId = $internship?->faculty_id ? (int) $internship->faculty_id : null;
        $assigned = FacultySectionAssignmentService::isValidAdviser($facultyId);

        $name = null;
        if ($assigned) {
            $profile = FacultyProfile::where('user_id', $facultyId)->first();
            $name = $profile ? trim($profile->first_name.' '.$profile->last_name) : null;
        }

        return [
            'assigned' => $assigned,
            'faculty_id' => $assigned ? $facultyId : null,
            'name' => $name,
            'message' => $assigned ? null : self::ADVISER_MESSAGE,
        ];
    }

    /** Open internship statuses that mean the student is already placed. */
    public const PLACED_STATUSES = ['placed', 'ongoing', 'active', 'for_evaluation', 'suspended', 'deferred'];

    /** Application statuses that count as "current" and lock other companies. */
    public const CURRENT_APPLICATION_STATUSES = ['pending', 'approved'];

    /**
     * @return array{locked: bool, message: string|null, internship_id: int|null, company_id: int|null, company_name: string|null, source: string|null, application_id: int|null}
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
            'application_id' => null,
        ];

        if (! $open) {
            $finished = Internship::query()
                ->where('student_id', $student->id)
                ->where('status', 'completed')
                ->whereNotNull('company_id')
                ->latest('id')
                ->first();

            return $finished
                ? self::locked($finished, (int) $finished->company_id, 'completed', null)
                : $unlocked;
        }

        $current = InternshipApplication::query()
            ->where('student_id', $student->id)
            ->whereIn('status', self::CURRENT_APPLICATION_STATUSES)
            ->where('updated_at', '>=', $open->created_at)
            ->latest('updated_at')
            ->first();

        if ($current) {
            $source = $current->status === 'approved' ? 'approved_application' : 'pending_application';

            return self::locked($open, (int) $current->company_id, $source, (int) $current->id);
        }

        if (in_array($open->status, self::PLACED_STATUSES, true) && $open->company_id) {
            return self::locked($open, (int) $open->company_id, 'placement', null);
        }

        return $unlocked;
    }

    private static function locked(Internship $open, int $companyId, string $source, ?int $applicationId): array
    {
        return [
            'locked' => true,
            'message' => self::LOCK_MESSAGE,
            'internship_id' => $open->id,
            'company_id' => $companyId,
            'company_name' => Company::query()->whereKey($companyId)->value('company_name'),
            'source' => $source,
            'application_id' => $applicationId,
        ];
    }
}
