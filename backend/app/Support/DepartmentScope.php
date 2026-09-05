<?php

namespace App\Support;

use App\Models\Internship;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * College/department visibility for faculty and coordinators.
 * Director and admin remain university-wide (existing behavior).
 */
final class DepartmentScope
{
    public const DENIED_MESSAGE = 'Access denied — different department';

    public static function isUniversityWide(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('director') || $user->hasRole('admin');
    }

    public static function departmentIdFor(?User $user): ?int
    {
        if (! $user) {
            return null;
        }

        $user->loadMissing('facultyProfile');
        $id = $user->facultyProfile?->department_id;

        return $id ? (int) $id : null;
    }

    public static function studentDepartmentId(User|StudentProfile|null $student): ?int
    {
        if ($student instanceof StudentProfile) {
            return $student->department_id ? (int) $student->department_id : null;
        }

        if ($student instanceof User) {
            $student->loadMissing('studentProfile');
            $id = $student->studentProfile?->department_id;

            return $id ? (int) $id : null;
        }

        return null;
    }

    public static function abortDifferentDepartment(): void
    {
        abort(403, self::DENIED_MESSAGE);
    }

    public static function constrainStudents(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (self::isUniversityWide($user)) {
            return $query;
        }

        if (! in_array($user->role, ['faculty', 'coordinator'], true)) {
            return $query;
        }

        $deptId = self::departmentIdFor($user);
        if (! $deptId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('studentProfile', function ($q) use ($deptId) {
            $q->where('department_id', $deptId);
        });
    }

    public static function constrainStaff(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (self::isUniversityWide($user)) {
            return $query;
        }

        if (! in_array($user->role, ['faculty', 'coordinator'], true)) {
            return $query;
        }

        $deptId = self::departmentIdFor($user);
        if (! $deptId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('role', ['faculty', 'coordinator'])
            ->whereHas('facultyProfile', function ($q) use ($deptId) {
                $q->where('department_id', $deptId);
            });
    }

    public static function constrainInternships(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (self::isUniversityWide($user)) {
            return $query;
        }

        if (! in_array($user->role, ['faculty', 'coordinator'], true)) {
            return $query;
        }

        $deptId = self::departmentIdFor($user);
        if (! $deptId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('student.studentProfile', function ($q) use ($deptId) {
            $q->where('department_id', $deptId);
        });
    }

    public static function studentBelongsToActor(User $actor, User $student): bool
    {
        if (self::isUniversityWide($actor)) {
            return true;
        }

        if (! in_array($actor->role, ['faculty', 'coordinator'], true)) {
            return false;
        }

        $deptId = self::departmentIdFor($actor);
        if (! $deptId) {
            return false;
        }

        $student->loadMissing('studentProfile');

        return (int) $student->studentProfile?->department_id === $deptId;
    }

    public static function facultyBelongsToActor(User $actor, User $faculty): bool
    {
        if (self::isUniversityWide($actor)) {
            return true;
        }

        if (! in_array($actor->role, ['faculty', 'coordinator'], true)) {
            return false;
        }

        $actorDept = self::departmentIdFor($actor);
        $facultyDept = self::departmentIdFor($faculty);
        if (! $actorDept || ! $facultyDept) {
            return false;
        }

        return $actorDept === $facultyDept;
    }

    public static function internshipBelongsToActor(User $actor, Internship $internship): bool
    {
        if (self::isUniversityWide($actor)) {
            return true;
        }

        if (! in_array($actor->role, ['faculty', 'coordinator'], true)) {
            return false;
        }

        $deptId = self::departmentIdFor($actor);
        if (! $deptId) {
            return false;
        }

        $internship->loadMissing('student.studentProfile');

        return (int) $internship->student?->studentProfile?->department_id === $deptId;
    }

    /**
     * True when faculty and student are the same college.
     * Incomplete department data is not treated as a cross-department mismatch.
     */
    public static function facultyMatchesStudent(?User $faculty, User|StudentProfile|null $student): bool
    {
        if (! $faculty || ! $student) {
            return false;
        }

        $facultyDept = self::departmentIdFor($faculty);
        $studentDept = self::studentDepartmentId($student);
        if (! $facultyDept || ! $studentDept) {
            return true;
        }

        return $facultyDept === $studentDept;
    }

    public static function abortUnlessFacultyMatchesStudent(?User $faculty, User|StudentProfile|null $student): void
    {
        if (! $faculty) {
            return;
        }

        if (! self::facultyMatchesStudent($faculty, $student)) {
            self::abortDifferentDepartment();
        }
    }

    public static function abortUnlessStudentInDepartment(User $actor, int $studentId): void
    {
        if (self::isUniversityWide($actor)) {
            return;
        }

        if (! User::query()->where('role', 'student')->whereKey($studentId)->exists()) {
            abort(404, 'Student not found.');
        }

        if (! User::inDepartment()->whereKey($studentId)->exists()) {
            self::abortDifferentDepartment();
        }
    }

    public static function abortUnlessFacultyInDepartment(User $actor, int $facultyId): void
    {
        if (self::isUniversityWide($actor)) {
            return;
        }

        if (! User::query()->whereIn('role', ['faculty', 'coordinator'])->whereKey($facultyId)->exists()) {
            abort(404, 'Faculty not found.');
        }

        if (! User::inStaffDepartment()->whereKey($facultyId)->exists()) {
            self::abortDifferentDepartment();
        }
    }

    public static function abortUnlessInternshipInDepartment(User $actor, Internship $internship): void
    {
        if (self::isUniversityWide($actor)) {
            return;
        }

        if (! self::internshipBelongsToActor($actor, $internship)) {
            self::abortDifferentDepartment();
        }
    }
}
