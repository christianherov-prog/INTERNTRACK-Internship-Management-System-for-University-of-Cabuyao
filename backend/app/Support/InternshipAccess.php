<?php

namespace App\Support;

use App\Models\Internship;
use App\Models\User;

/**
 * Shared ownership checks for internship-scoped resources.
 */
class InternshipAccess
{
    public static function canView(User $user, Internship $internship): bool
    {
        if ($user->role === 'admin' || $user->role === 'director') {
            return true;
        }

        return match ($user->role) {
            'student' => (int) $internship->student_id === (int) $user->id,
            'supervisor' => (int) $internship->supervisor_id === (int) $user->id,
            'faculty' => (int) $internship->faculty_id === (int) $user->id
                && DepartmentScope::internshipBelongsToActor($user, $internship),
            'coordinator' => DepartmentScope::internshipBelongsToActor($user, $internship),
            default => false,
        };
    }

    public static function abortUnlessCanView(User $user, Internship $internship): void
    {
        if (self::canView($user, $internship)) {
            return;
        }

        if (in_array($user->role, ['faculty', 'coordinator'], true)
            && ! DepartmentScope::internshipBelongsToActor($user, $internship)) {
            DepartmentScope::abortDifferentDepartment();
        }

        abort(403, 'Access denied to this internship.');
    }

    public static function canManageAsCoordinator(User $user, Internship $internship): bool
    {
        if ($user->role !== 'coordinator') {
            return false;
        }

        if (! DepartmentScope::internshipBelongsToActor($user, $internship)) {
            return false;
        }

        return $internship->coordinator_id === null
            || (int) $internship->coordinator_id === (int) $user->id;
    }

    /**
     * Resolve internship id from a private storage path when possible.
     * Expected prefixes: journals/{id}/, internships/{id}/, portfolios/{id}/, messages/{id}/, signatures/...
     */
    public static function internshipIdFromPath(string $path): ?int
    {
        $path = preg_replace('#^(?:storage/|public/|app/public/)#i', '', ltrim($path, '/'));

        if (preg_match('#^(?:journals|internships|portfolios|messages|documents)/(\d+)/#', $path, $m)) {
            return (int) $m[1];
        }

        // signatures/evaluations/{internshipId}/... or signatures/attendance/{internshipId}/...
        if (preg_match('#^signatures/(?:evaluations|attendance)/(\d+)/#', $path, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
