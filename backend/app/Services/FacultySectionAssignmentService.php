<?php

namespace App\Services;

use App\Models\FacultySectionAssignment;
use App\Models\Internship;
use App\Models\StudentProfile;
use App\Models\User;

class FacultySectionAssignmentService
{
    /** Canonical UC BSIT section codes for practicum. */
    public const SECTIONS = ['4ITA', '4ITB', '4ITC', '4ITD'];

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
     * Resolve faculty supervisor for a student profile.
     */
    public function resolveFacultyForProfile(?StudentProfile $profile): ?User
    {
        if (!$profile) {
            return null;
        }

        $programName = is_object($profile->program) ? ($profile->program->name) : $profile->program;

        return $this->suggestFacultyForSection(
            $profile->section,
            $programName,
            $profile->school_year,
            $profile->semester
        );
    }

    /**
     * Suggest faculty from active section mapping (used for auto-assign + UI default).
     */
    public function suggestFacultyForSection(
        ?string $section,
        ?string $program = null,
        ?string $schoolYear = null,
        ?string $semester = null
    ): ?User {
        $normalized = self::normalizeSection($section);
        if (!$normalized) {
            return null;
        }

        $rawSection = $section;
        $hyphenated = preg_replace('/^(\d+)([A-Z]+)-?([A-Z0-9]+)$/', '$1$2-$3', $normalized);
        $sectionVariants = array_values(array_unique(array_filter([$rawSection, $normalized, $hyphenated])));

        $query = FacultySectionAssignment::query()
            ->where('is_active', true)
            ->whereIn('section', $sectionVariants);

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

        if (!$assignment && $program) {
            $assignment = (clone $query)
                ->where(function ($sub) {
                    $sub->whereNull('program')->orWhere('program', '');
                })
                ->with('faculty.facultyProfile')
                ->first();
        }

        if (!$assignment) {
            $assignment = (clone $query)
                ->with('faculty.facultyProfile')
                ->first();
        }

        if (!$assignment) {
            $assignment = FacultySectionAssignment::query()
                ->where('is_active', true)
                ->whereIn('section', $sectionVariants)
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
        return User::where('role', 'faculty')
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
        if (!$faculty) {
            return null;
        }

        $fp = $faculty->facultyProfile;
        $name = $fp
            ? trim("{$fp->first_name} {$fp->last_name}")
            : $faculty->username;

        return [
            'id'              => $faculty->id,
            'username'        => $faculty->username,
            'name'            => $name !== '' ? $name : $faculty->username,
            'faculty_number'  => $fp?->faculty_number ?? $faculty->username,
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
            'section'                   => $profile?->section,
            'section_normalized'        => self::normalizeSection($profile?->section),
            'program'                   => $profile?->program,
            'school_year'             => $profile?->school_year,
            'semester'                  => $profile?->semester,
            'resolved_faculty'          => $this->formatFaculty($faculty),
            'faculty_resolution_status' => $faculty ? 'resolved' : 'missing_mapping',
            'allowed_sections'          => self::SECTIONS,
        ];
    }

    /**
     * Ensure all students in a section have an initialized internship record assigned to the section's faculty.
     */
    public function syncInternshipsForSection(
        ?string $section,
        ?string $program = null,
        ?string $schoolYear = null,
        ?string $semester = null
    ): void {
        $normalized = self::normalizeSection($section);
        if (!$normalized) {
            return;
        }

        $faculty = $this->suggestFacultyForSection($normalized, $program, $schoolYear, $semester);
        $facultyId = $faculty?->id;

        $profiles = StudentProfile::all()->filter(function ($p) use ($normalized) {
            return self::normalizeSection($p->section) === $normalized;
        });

        foreach ($profiles as $profile) {
            if (!$profile->user_id) {
                continue;
            }

            $internship = Internship::where('student_id', $profile->user_id)->first();
            if (!$internship) {
                $user = User::find($profile->user_id);
                if ($user && $user->role === 'student') {
                    $prog = $profile->program ?: ($program ?: 'Bachelor of Science in Information Technology');
                    $targetHours = 500;
                    if (stripos($prog, 'Computer Science') !== false) $targetHours = 300;
                    if (stripos($prog, 'Engineering') !== false || stripos($profile->department, 'Engineering') !== false) $targetHours = 240;

                    $user->internshipsAsStudent()->create([
                        'status' => 'pending_placement',
                        'school_year' => $profile->school_year ?: ($schoolYear ?: '2025-2026'),
                        'semester' => $profile->semester ?: ($semester ?: '2nd Semester'),
                        'term' => "AY " . ($profile->school_year ?: ($schoolYear ?: '2025-2026')) . ", " . ($profile->semester ?: ($semester ?: '2nd Semester')),
                        'program' => $prog,
                        'faculty_id' => $facultyId,
                        'target_hours' => $targetHours,
                        'total_hours_rendered' => 0,
                    ]);
                }
            } elseif ($facultyId && $internship->faculty_id !== $facultyId) {
                $internship->forceFill(['faculty_id' => $facultyId])->saveQuietly();
            }
        }
    }
}
