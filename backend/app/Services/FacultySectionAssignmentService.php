<?php

namespace App\Services;

use App\Models\FacultySectionAssignment;
use App\Models\Internship;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\DepartmentScope;
use App\Support\InternshipProvisioning;
use App\Support\InternshipStatuses;
use App\Support\ProgramCatalog;
use Illuminate\Database\Eloquent\Builder;

class FacultySectionAssignmentService
{
    /** Canonical UC CCS (BSIT / BSCS) section codes for practicum. */
    public const SECTIONS = ['4ITA', '4ITB', '4ITC', '4ITD', '4CSA', '4CSB'];

    /** Roles that may act as a Student's Faculty adviser (a Coordinator uses the same account). */
    public const ADVISER_ROLES = ['faculty', 'coordinator'];

    /**
     * Normalize UC section codes (4ITA, 4ITB, 4ITC, 4ITD).
     */
    public static function normalizeSection(?string $section): ?string
    {
        if ($section === null) {
            return null;
        }

        $normalized = strtoupper(trim(str_replace([' ', '-'], '', $section)));

        return $normalized !== '' ? $normalized : null;
    }

    public static function isAllowedSection(?string $section): bool
    {
        $normalized = self::normalizeSection($section);

        return $normalized !== null && in_array($normalized, self::SECTIONS, true);
    }

    /**
     * Students whose CURRENT internship is advised by this faculty-capable user.
     *
     * The actual adviser (internships.faculty_id) is authoritative. Section
     * assignment only supplies the default adviser when an internship is
     * created or has none (see Internship::booted / StudentProfile::booted);
     * it never adds Students to a roster on its own, so transfers and
     * deliberate reassignments are respected and a Coordinator's Faculty
     * workspace shows only the Students he personally advises.
     */
    public static function assignedStudentsQuery(User $faculty, bool $activeOnly = true): Builder
    {
        $query = User::inDepartment()
            ->where('role', 'student')
            ->whereHas('internshipsAsStudent', function ($i) use ($faculty) {
                self::constrainToCurrentInternship($i)->where('internships.faculty_id', $faculty->id);
            });

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query;
    }

    /** Whether $faculty is the actual adviser of the Student's current internship. */
    public static function advisesStudent(User $faculty, int $studentId): bool
    {
        return self::assignedStudentsQuery($faculty, false)->whereKey($studentId)->exists();
    }

    /**
     * Restrict an internships query to each Student's current internship: the
     * newest row in a current status (open, or completed and still shown).
     * Older superseded/cancelled rows never decide who advises the Student.
     */
    public static function constrainToCurrentInternship($query)
    {
        $statuses = InternshipStatuses::currentRelation();

        return $query->whereIn('internships.status', $statuses)
            ->whereNotExists(function ($newer) use ($statuses) {
                $newer->from('internships as newer_internship')
                    ->whereColumn('newer_internship.student_id', 'internships.student_id')
                    ->whereColumn('newer_internship.id', '>', 'internships.id')
                    ->whereIn('newer_internship.status', $statuses)
                    ->whereNull('newer_internship.deleted_at');
            });
    }

    /** An adviser id is valid when it points at an active, faculty-capable account. */
    public static function isValidAdviser(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        return User::whereKey($userId)
            ->whereIn('role', self::ADVISER_ROLES)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Resolve faculty supervisor for a student profile.
     */
    public function resolveFacultyForProfile(?StudentProfile $profile): ?User
    {
        if (! $profile) {
            return null;
        }

        $profile->loadMissing('program');
        $programName = $profile->getRelation('program')?->name;

        $faculty = $this->suggestFacultyForSection(
            $profile->section,
            $programName,
            $profile->school_year,
            $profile->semester,
            DepartmentScope::studentDepartmentId($profile)
        );

        if ($faculty && ! DepartmentScope::facultyMatchesStudent($faculty, $profile)) {
            return null;
        }

        return $faculty;
    }

    /**
     * Suggest faculty from active section mapping (used for auto-assign + UI default).
     */
    public function suggestFacultyForSection(
        ?string $section,
        ?string $program = null,
        ?string $schoolYear = null,
        ?string $semester = null,
        ?int $departmentId = null
    ): ?User {
        $normalized = self::normalizeSection($section);
        if (! $normalized) {
            return null;
        }

        $rawSection = $section;
        $hyphenated = preg_replace('/^(\d+)([A-Z]+)-?([A-Z0-9]+)$/', '$1$2-$3', $normalized);
        $sectionVariants = array_values(array_unique(array_filter([$rawSection, $normalized, $hyphenated])));

        $query = FacultySectionAssignment::query()
            ->where('is_active', true)
            ->whereIn('section', $sectionVariants)
            ->when($departmentId, function ($q) use ($departmentId) {
                $q->whereHas('faculty.facultyProfile', fn ($fp) => $fp->where('department_id', $departmentId));
            });

        if ($schoolYear) {
            $query->where('school_year', $schoolYear);
        }
        if ($semester) {
            $semNum = (int) filter_var($semester, FILTER_SANITIZE_NUMBER_INT);
            $semVariants = array_values(array_unique(array_filter([
                $semester,
                $semNum ? "{$semNum}" : null,
                $semNum === 1 ? '1st Semester' : ($semNum === 2 ? '2nd Semester' : null),
                $semNum ? "Sem {$semNum}" : null,
            ])));
            $query->whereIn('semester', $semVariants);
        }

        $assignment = (clone $query)
            ->when($program, fn ($q) => $q->where(function ($sub) use ($program) {
                $sub->where('program', $program)
                    ->orWhere('program', 'like', "%{$program}%");
            }))
            ->with('faculty.facultyProfile')
            ->first();

        if (! $assignment && $program) {
            $assignment = (clone $query)
                ->where(function ($sub) {
                    $sub->whereNull('program')->orWhere('program', '');
                })
                ->with('faculty.facultyProfile')
                ->first();
        }

        if (! $assignment) {
            $assignment = (clone $query)
                ->with('faculty.facultyProfile')
                ->first();
        }

        if (! $assignment) {
            $assignment = FacultySectionAssignment::query()
                ->where('is_active', true)
                ->whereIn('section', $sectionVariants)
                ->when($departmentId, function ($q) use ($departmentId) {
                    $q->whereHas('faculty.facultyProfile', fn ($fp) => $fp->where('department_id', $departmentId));
                })
                ->with('faculty.facultyProfile')
                ->first();
        }

        return $assignment?->faculty;
    }

    public function resolveFacultyForInternship(Internship $internship): ?User
    {
        $internship->loadMissing('student.studentProfile');

        return $this->resolveFacultyForProfile($internship->student?->studentProfile);
    }

    /**
     * @return list<array{id:int,username:string,name:string|null,employee_number:string|null}>
     */
    public function facultyOptions(): array
    {
        return User::inStaffDepartment()
            ->whereIn('role', ['faculty', 'coordinator'])
            ->where('is_active', true)
            ->with('facultyProfile')
            ->orderBy('faculty_number')
            ->get()
            ->map(fn (User $u) => $this->formatFaculty($u))
            ->values()
            ->all();
    }

    public function formatFaculty(?User $faculty): ?array
    {
        if (! $faculty) {
            return null;
        }

        $fp = $faculty->facultyProfile;
        $name = $fp
            ? trim("{$fp->last_name}, {$fp->first_name}")
            : $faculty->username;

        return [
            'id' => $faculty->id,
            'username' => $faculty->username,
            'name' => $name !== '' ? $name : $faculty->username,
            'faculty_number' => $fp?->faculty_number ?? $faculty->username,
        ];
    }

    /**
     * Build placement preview payload for coordinator UI.
     */
    public function previewForInternship(Internship $internship): array
    {
        $internship->loadMissing('student.studentProfile');
        $profile = $internship->student?->studentProfile;
        $faculty = $this->resolveFacultyForProfile($profile);

        return [
            'section' => $profile?->section,
            'section_normalized' => self::normalizeSection($profile?->section),
            'program' => $profile?->getRelation('program')?->name,
            'school_year' => $profile?->school_year,
            'semester' => $profile?->semester,
            'resolved_faculty' => $this->formatFaculty($faculty),
            'faculty_resolution_status' => $faculty ? 'resolved' : 'missing_mapping',
            'allowed_sections' => self::SECTIONS,
        ];
    }

    /**
     * Ensure all students in a section have an initialized internship record with a valid adviser.
     * When a mapping moves to a new faculty, $previousFacultyId lets Students who followed the old default move with it.
     */
    public function syncInternshipsForSection(
        ?string $section,
        ?string $program = null,
        ?string $schoolYear = null,
        ?string $semester = null,
        ?int $previousFacultyId = null
    ): void {
        $normalized = self::normalizeSection($section);
        if (! $normalized) {
            return;
        }

        $profiles = StudentProfile::all()->filter(function ($p) use ($normalized) {
            return self::normalizeSection($p->section) === $normalized;
        });

        foreach ($profiles as $profile) {
            if (! $profile->user_id) {
                continue;
            }

            $faculty = $this->resolveFacultyForProfile($profile);
            $assignableFacultyId = $faculty?->id;

            $internship = InternshipProvisioning::openForStudent($profile->user_id)
                ?? Internship::where('student_id', $profile->user_id)->latest('id')->first();
            if (! $internship) {
                $user = User::find($profile->user_id);
                if ($user && $user->role === 'student') {
                    $profile->loadMissing('program');
                    $prog = $profile->getRelation('program')?->name
                        ?: ProgramCatalog::displayName($program)
                        ?: $program;

                    InternshipProvisioning::createPendingIfNone($user, [
                        'status' => 'pending_placement',
                        'school_year' => $profile->school_year ?: ($schoolYear ?: '2025-2026'),
                        'semester' => $profile->semester ?: ($semester ?: '2nd Semester'),
                        'term' => 'AY '.($profile->school_year ?: ($schoolYear ?: '2025-2026')).', '.($profile->semester ?: ($semester ?: '2nd Semester')),
                        'program' => $prog,
                        'faculty_id' => $assignableFacultyId,
                        'target_hours' => ProgramRequirementService::targetHoursForProfile($profile),
                        'total_hours_rendered' => 0,
                    ]);
                }
            } elseif ($assignableFacultyId
                && (int) $internship->faculty_id !== (int) $assignableFacultyId
                && (! self::isValidAdviser($internship->faculty_id)
                    || ($previousFacultyId && (int) $internship->faculty_id === (int) $previousFacultyId))) {
                // Only Students without a valid adviser, or still following the
                // section's previous default faculty, move with the section.
                // A Student deliberately reassigned elsewhere keeps that adviser.
                $internship->forceFill(['faculty_id' => $assignableFacultyId])->saveQuietly();
            }
        }
    }
}
