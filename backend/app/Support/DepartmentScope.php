<?php

namespace App\Support;

use App\Models\Internship;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

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

        $user->loadMissing('facultyProfile.department');
        $id = $user->facultyProfile?->department_id;
        if ($id) {
            return (int) $id;
        }

        return self::departmentIdFromStaffNumber($user->faculty_number);
    }

    /**
     * Resolve college from staff IDs such as COR-CCS-001 / FAC-CHAS-001.
     */
    public static function departmentIdFromStaffNumber(?string $number): ?int
    {
        if (! $number || ! preg_match('/-(CCS|COE|COED|CHAS|CAS|CBAA)-/i', $number, $match)) {
            return null;
        }

        $code = strtoupper($match[1]);

        return \App\Models\Department::query()
            ->where('code', $code)
            ->value('id');
    }

    public static function studentDepartmentId(User|StudentProfile|null $student): ?int
    {
        $profile = $student instanceof StudentProfile
            ? $student
            : ($student instanceof User ? $student->loadMissing('studentProfile.program')->studentProfile : null);

        if (! $profile) {
            return null;
        }

        $profile->loadMissing('program');

        if ($profile->getRelation('program')?->department_id) {
            return (int) $profile->getRelation('program')->department_id;
        }

        return $profile->department_id ? (int) $profile->department_id : null;
    }

    /**
     * Match student_profiles whose Program (authoritative) or stored
     * department_id belongs to the given college.
     */
    public static function constrainStudentProfiles(Builder $query, int $deptId): Builder
    {
        return $query->where(function ($inner) use ($deptId) {
            $inner->whereHas('program', fn ($p) => $p->where('department_id', $deptId))
                ->orWhere(function ($fallback) use ($deptId) {
                    $fallback->whereNull('program_id')->where('department_id', $deptId);
                });
        });
    }

    /**
     * Coordinators who belong to the student's college. Never falls back
     * to an unrelated department or the first coordinator in the database.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public static function coordinatorIdsForStudent(User|StudentProfile|null $student)
    {
        $deptId = self::studentDepartmentId($student);
        if (! $deptId) {
            return collect();
        }

        return User::query()
            ->where('role', 'coordinator')
            ->where('is_active', true)
            ->whereHas('facultyProfile', fn ($q) => $q->where('department_id', $deptId))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    public static function coordinatorIdForStudent(User|StudentProfile|null $student, ?int $preferredId = null): ?int
    {
        $ids = self::coordinatorIdsForStudent($student);
        if ($ids->isEmpty()) {
            return null;
        }

        if ($preferredId && $ids->contains((int) $preferredId)) {
            return (int) $preferredId;
        }

        return (int) $ids->first();
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
            self::constrainStudentProfiles($q, $deptId);
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
            self::constrainStudentProfiles($q, $deptId);
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

        $student->loadMissing('studentProfile.program');

        return self::studentDepartmentId($student) === $deptId;
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

        $internship->loadMissing('student.studentProfile.program');

        return self::studentDepartmentId($internship->student) === $deptId;
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

    /**
     * Assignment-time validation: reject a confirmed cross-department faculty.
     */
    public static function assertFacultySameDepartment(?User $faculty, User|StudentProfile|null $student): void
    {
        if (! $faculty || ! $student) {
            return;
        }

        $facultyDept = self::departmentIdFor($faculty);
        $studentDept = self::studentDepartmentId($student);
        if (! $facultyDept || ! $studentDept) {
            return;
        }

        if ($facultyDept !== $studentDept) {
            throw ValidationException::withMessages([
                'faculty_id' => ['Faculty must belong to the student\'s department.'],
            ]);
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
