<?php

namespace App\Support;

use App\Models\FacultySectionAssignment;
use App\Models\User;
use App\Services\FacultySectionAssignmentService;

/**
 * The controlled CCS section → Faculty split for the current term.
 *
 * Section assignment is the DEFAULT adviser source: a Student in one of these
 * sections is advised by the listed faculty-capable account (a Faculty, or a
 * Coordinator acting in Faculty capacity with the same single account) unless
 * an authorized workflow deliberately reassigns them.
 */
final class CcsSectionDirectory
{
    public const SCHOOL_YEAR = '2025-2026';

    public const SEMESTER = '2nd Semester';

    /** @var list<array{section: string, program: string, faculty_number: string}> */
    public const SECTIONS = [
        ['section' => '4IT-A', 'program' => 'Bachelor of Science in Information Technology', 'faculty_number' => 'FAC-1001'],
        ['section' => '4IT-B', 'program' => 'Bachelor of Science in Information Technology', 'faculty_number' => 'COR-CCS-001'],
        ['section' => '4IT-D', 'program' => 'Bachelor of Science in Information Technology', 'faculty_number' => 'FAC-1001'],
        ['section' => '4CS-A', 'program' => 'Bachelor of Science in Computer Science', 'faculty_number' => 'FAC-1001'],
        ['section' => '4CS-B', 'program' => 'Bachelor of Science in Computer Science', 'faculty_number' => 'COR-CCS-001'],
    ];

    /**
     * Upsert the split without duplicating sections that differ only in
     * formatting ("4IT-D" vs "4ITD"). Every existing active row for the same
     * normalized section and term is pointed at the target faculty; a row is
     * created only when none exists. Saving goes through the model so the
     * normal section re-sync (FacultySectionAssignment::saved) runs.
     *
     * @return array<string, int> section => faculty user id
     */
    public static function apply(): array
    {
        $applied = [];

        foreach (self::SECTIONS as $row) {
            $faculty = User::where('faculty_number', $row['faculty_number'])
                ->whereIn('role', ['faculty', 'coordinator'])
                ->first();
            if (! $faculty) {
                continue;
            }

            $normalized = FacultySectionAssignmentService::normalizeSection($row['section']);
            $existing = FacultySectionAssignment::query()
                ->where('school_year', self::SCHOOL_YEAR)
                ->where('semester', self::SEMESTER)
                ->get()
                ->filter(fn (FacultySectionAssignment $a) => FacultySectionAssignmentService::normalizeSection($a->section) === $normalized);

            if ($existing->isEmpty()) {
                FacultySectionAssignment::create([
                    'section' => $row['section'],
                    'program' => $row['program'],
                    'school_year' => self::SCHOOL_YEAR,
                    'semester' => self::SEMESTER,
                    'faculty_user_id' => $faculty->id,
                    'is_active' => true,
                ]);
            } else {
                foreach ($existing as $assignment) {
                    $assignment->fill([
                        'faculty_user_id' => $faculty->id,
                        'program' => $assignment->program ?: $row['program'],
                        'is_active' => true,
                    ]);
                    if ($assignment->isDirty()) {
                        $assignment->save();
                    }
                }
            }

            $applied[$row['section']] = $faculty->id;
        }

        return $applied;
    }
}
