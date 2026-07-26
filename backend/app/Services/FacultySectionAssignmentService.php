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

        return $this->suggestFacultyForSection(
            $profile->section,
            $profile->program ?: $profile->course_name,
            $profile->academic_year,
            $profile->semester
        );
    }

    /**
     * Suggest faculty from active section mapping (used for auto-assign + UI default).
     */
    public function suggestFacultyForSection(
        ?string $section,
        ?string $program = null,
        ?string $academicYear = null,
        mixed $semester = null
    ): ?User {
        $section = self::normalizeSection($section);
        if (!$section) {
            return null;
        }

        $semester = (int) ($semester ?: 0);

        $query = FacultySectionAssignment::query()
            ->where('is_active', true)
            ->where('section', $section);

        if ($academicYear) {
            $query->where('academic_year', $academicYear);
        }
        if ($semester > 0) {
            $query->where('semester', $semester);
        }

        $assignment = (clone $query)
            ->when($program, fn ($q) => $q->where('program', $program))
            ->with('faculty.facultyProfile')
            ->first();

        if (!$assignment && $program) {
            $assignment = $query
                ->whereNull('program')
                ->with('faculty.facultyProfile')
                ->first();
        }

        if (!$assignment) {
            $assignment = FacultySectionAssignment::query()
                ->where('is_active', true)
                ->where('section', $section)
                ->with('faculty.facultyProfile')
                ->orderByDesc('academic_year')
                ->orderByDesc('semester')
                ->first();
        }

        $faculty = $assignment?->faculty;

        return ($faculty && $faculty->role === 'faculty' && $faculty->is_active) ? $faculty : null;
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
            ->orderBy('username')
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
            'employee_number' => $fp?->employee_number ?? $faculty->username,
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
            'program'                   => $profile?->program ?: $profile?->course_name,
            'academic_year'             => $profile?->academic_year,
            'semester'                  => $profile?->semester,
            'resolved_faculty'          => $this->formatFaculty($faculty),
            'faculty_resolution_status' => $faculty ? 'resolved' : 'missing_mapping',
            'allowed_sections'          => self::SECTIONS,
        ];
    }
}
