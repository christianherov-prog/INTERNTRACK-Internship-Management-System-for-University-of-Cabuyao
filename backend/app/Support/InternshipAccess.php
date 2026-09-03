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
        if ($user->role === 'admin') {
            return true;
        }

        if (in_array($user->role, ['director', 'coordinator', 'faculty'], true)) {
            $userDept = $user->facultyProfile?->department;
            $internship->loadMissing('student.studentProfile');
            $studentDept = $internship->student?->studentProfile?->department;

            if ($userDept && $studentDept) {
                $isEngineering = stripos($userDept, 'Engineering') !== false || stripos($userDept, 'COE') !== false;
                if ($isEngineering) {
                    if (stripos($studentDept, 'Engineering') === false && stripos($studentDept, 'COE') === false) {
                        return false;
                    }
                } elseif ($userDept !== $studentDept) {
                    return false;
                }
            }
        }

        return match ($user->role) {
            'director' => true, // Assuming department check above passed
            'student' => (int) $internship->student_id === (int) $user->id,
            'supervisor' => (int) $internship->supervisor_id === (int) $user->id,
            'faculty' => (int) $internship->faculty_id === (int) $user->id,
            'coordinator' => (int) $internship->coordinator_id === (int) $user->id,
            default => false,
        };
    }

    public static function canManageAsCoordinator(User $user, Internship $internship): bool
    {
        return $user->role === 'coordinator'
            && (int) $internship->coordinator_id === (int) $user->id;
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
