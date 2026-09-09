<?php

namespace App\Services;

use App\Models\Internship;
use App\Support\InternshipStatuses;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical internship progress numbers shared by student, faculty,
 * coordinator, supervisor, and director surfaces.
 */
class InternshipProgressService
{
    /**
     * @return array{
     *     hours_rendered: float,
     *     target_hours: float,
     *     remaining_hours: float,
     *     progress_pct: float,
     *     hte_count: int,
     *     hte_completed: int,
     *     status: string,
     *     status_label: string,
     *     company_name: ?string
     * }
     */
    public static function snapshot(Internship $internship): array
    {
        $relations = ['company', 'student.studentProfile.program'];
        $hasPlacements = Schema::hasTable('internship_placements');
        if ($hasPlacements) {
            $relations[] = 'placements.company';
            $relations[] = 'placements.supervisor';
        }

        $internship->loadMissing($relations);

        $hours = $internship->computeTotalHours();
        $programHours = ProgramRequirementService::targetHoursFor($internship->student?->studentProfile?->program);
        $target = $programHours > 0 ? $programHours : (float) $internship->target_hours;
        $placements = $hasPlacements ? $internship->placements : collect();
        $hteCount = $placements->count();
        if ($hteCount === 0) {
            $hteCount = ProgramRequirementService::hteCountFor($internship->student?->studentProfile?->program);
        }
        $hteCompleted = $placements
            ->filter(fn ($p) => in_array($p->status, ['completed', 'done'], true) || $p->isComplete())
            ->count();

        return [
            'hours_rendered' => $hours,
            'target_hours' => $target,
            'remaining_hours' => max(0, round($target - $hours, 2)),
            'progress_pct' => $target > 0 ? (float) min(100, max(0, round(($hours / $target) * 100, 1))) : 0.0,
            'hte_count' => $hteCount,
            'hte_completed' => $hteCompleted,
            'status' => InternshipStatuses::normalize($internship->status),
            'status_label' => InternshipStatuses::label($internship->status),
            'company_name' => $internship->company?->company_name,
        ];
    }

    public static function synchronize(Internship $internship): void
    {
        $internship->loadMissing('student.studentProfile.program');
        InternshipPlacementService::ensurePlacements($internship);

        $programHours = ProgramRequirementService::targetHoursFor($internship->student?->studentProfile?->program);
        if ($programHours > 0 && (float) $internship->target_hours !== $programHours) {
            $internship->target_hours = $programHours;
            $internship->save();
        }

        $internship->refreshTotalHours();
    }

    public static function targetHoursForInternship(?Internship $internship, $fallbackProfile = null): float
    {
        if ($internship) {
            $internship->loadMissing('student.studentProfile.program');
            $programHours = ProgramRequirementService::targetHoursFor($internship->student?->studentProfile?->program);
            if ($programHours > 0) {
                return $programHours;
            }

            return (float) $internship->target_hours;
        }

        return ProgramRequirementService::targetHoursForProfile($fallbackProfile);
    }
}
