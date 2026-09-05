<?php

namespace App\Services;

use App\Models\Internship;
use App\Models\InternshipPlacement;
use App\Models\ProgramHteRequirement;
use Illuminate\Support\Facades\Schema;

class InternshipPlacementService
{
    /**
     * Create missing placement rows for an internship from its program HTE requirements.
     * Single-HTE programs get N=1; multi-HTE programs get one row per requirement.
     */
    public static function ensurePlacements(Internship $internship): void
    {
        if (! Schema::hasTable('internship_placements') || ! Schema::hasTable('program_hte_requirements')) {
            return;
        }

        $internship->loadMissing('student.studentProfile.program', 'placements');

        $program = $internship->student?->studentProfile?->program;
        if (! $program) {
            return;
        }

        $requirements = ProgramHteRequirement::where('program_id', $program->id)
            ->orderBy('sequence_order')
            ->get();

        if ($requirements->isEmpty()) {
            return;
        }

        $targetHours = (float) $requirements->sum('required_hours');
        if ($targetHours > 0 && (float) $internship->target_hours !== $targetHours) {
            $internship->target_hours = $targetHours;
        }

        foreach ($requirements as $requirement) {
            InternshipPlacement::firstOrCreate(
                [
                    'internship_id' => $internship->id,
                    'sequence_order' => $requirement->sequence_order,
                ],
                [
                    'program_hte_requirement_id' => $requirement->id,
                    'company_id' => $internship->company_id,
                    'supervisor_id' => $internship->supervisor_id,
                    'label' => $requirement->label,
                    'required_hours' => $requirement->required_hours,
                    'accumulated_hours' => 0,
                    'status' => $requirement->sequence_order === 1 ? 'active' : 'pending',
                    'start_date' => $requirement->sequence_order === 1 ? $internship->start_date : null,
                ]
            );
        }

        if (! $internship->current_placement_id) {
            $current = $internship->placements()
                ->orderBy('sequence_order')
                ->first();
            if ($current) {
                $internship->current_placement_id = $current->id;
            }
        }

        $internship->save();
    }
}
