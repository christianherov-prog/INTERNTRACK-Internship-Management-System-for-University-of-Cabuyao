<?php

namespace App\Support;

use App\Models\Internship;
use App\Models\InternshipStatusHistory;
use App\Models\User;
use App\Services\InternshipProgressService;
use App\Services\ProgramRequirementService;
use Illuminate\Support\Facades\DB;

/**
 * One open (current) internship per student.
 * Multi-HTE programs use placement rows on that internship, not extra internship rows.
 */
final class InternshipProvisioning
{
    public static function openQuery(int $studentId)
    {
        return Internship::query()
            ->where('student_id', $studentId)
            ->whereIn('status', InternshipStatuses::openCurrent());
    }

    public static function openForStudent(int $studentId): ?Internship
    {
        return self::openQuery($studentId)->latest('id')->first();
    }

    /**
     * Current internship for student API requests (header / query override, else open row).
     */
    public static function resolveForStudent(User $user, mixed $requestedId = null): ?Internship
    {
        if ($requestedId) {
            return $user->internshipsAsStudent()->where('id', $requestedId)->first();
        }

        return self::openForStudent($user->id)
            ?? $user->activeInternship()->first();
    }

    public static function hasOpenInternship(int $studentId): bool
    {
        return self::openQuery($studentId)->exists();
    }

    /**
     * Create a pending internship only when the student has no open row.
     * Concurrent callers share the same locked lookup.
     */
    public static function createPendingIfNone(User $user, array $attributes = []): Internship
    {
        return UniqueWrite::retry(fn () => DB::transaction(function () use ($user, $attributes) {
            $existing = self::openQuery($user->id)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            $user->loadMissing('studentProfile.program');
            $profile = $user->studentProfile;
            $ay = $attributes['school_year'] ?? ($profile?->school_year ?: '2025-2026');
            $sem = $attributes['semester'] ?? ($profile?->semester ?: '2nd Semester');

            $defaults = [
                'status' => 'pending_placement',
                'school_year' => $ay,
                'semester' => $sem,
                'term' => "AY {$ay}, {$sem}",
                'program' => $profile?->program?->name,
                'faculty_id' => $attributes['faculty_id'] ?? app(\App\Services\FacultySectionAssignmentService::class)->resolveFacultyForProfile($profile)?->id,
                'target_hours' => ProgramRequirementService::targetHoursForProfile($profile),
                'total_hours_rendered' => 0,
            ];

            $internship = $user->internshipsAsStudent()->create(array_merge($defaults, $attributes));
            InternshipProgressService::synchronize($internship);

            return $internship->fresh();
        }));
    }

    /**
     * Keep the most complete open internship per student; mark the rest cancelled.
     *
     * @return int Number of internships cancelled
     */
    public static function supersedeDuplicateOpenInternships(?int $changedBy = null): int
    {
        $studentIds = Internship::query()
            ->whereIn('status', InternshipStatuses::openCurrent())
            ->select('student_id')
            ->groupBy('student_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('student_id');

        $cancelled = 0;

        foreach ($studentIds as $studentId) {
            // Rows that already hold student work are never superseded by empty ones.
            $rows = Internship::query()
                ->where('student_id', $studentId)
                ->whereIn('status', InternshipStatuses::openCurrent())
                ->withCount(['journals', 'attendance', 'documents', 'evaluations'])
                ->orderByRaw('(journals_count + attendance_count + documents_count + evaluations_count) DESC')
                ->orderByRaw('(company_id IS NOT NULL) DESC')
                ->orderByRaw('(supervisor_id IS NOT NULL) DESC')
                ->orderByDesc('id')
                ->get();

            $keep = $rows->first();
            if (! $keep) {
                continue;
            }

            foreach ($rows->skip(1) as $duplicate) {
                $from = $duplicate->status;
                $duplicate->update([
                    'status' => 'cancelled',
                    'status_reason' => 'Superseded duplicate current internship.',
                ]);

                if ($changedBy) {
                    InternshipStatusHistory::create([
                        'internship_id' => $duplicate->id,
                        'from_status' => $from,
                        'to_status' => 'cancelled',
                        'reason' => 'Superseded duplicate current internship.',
                        'changed_by' => $changedBy,
                    ]);
                }

                $cancelled++;
            }
        }

        return $cancelled;
    }
}
