<?php

namespace App\Services;

use App\Models\Program;
use App\Models\ProgramHteRequirement;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth for program internship hour and HTE requirements.
 */
class ProgramRequirementService
{
    /**
     * @return array{target_hours: float, hte_count: int}
     */
    public static function forProgram(?Program $program): array
    {
        if (! $program || ! Schema::hasTable('program_hte_requirements')) {
            return ['target_hours' => 0.0, 'hte_count' => 0];
        }

        $requirements = ProgramHteRequirement::query()
            ->where('program_id', $program->id)
            ->orderBy('sequence_order')
            ->get();

        return [
            'target_hours' => (float) $requirements->sum('required_hours'),
            'hte_count' => $requirements->count(),
        ];
    }

    public static function forProfile(?StudentProfile $profile): array
    {
        if (! $profile || ! Schema::hasTable('program_hte_requirements')) {
            return ['target_hours' => 0.0, 'hte_count' => 0];
        }

        $profile->loadMissing('program.hteRequirements');

        return self::forProgram($profile->program);
    }

    public static function targetHoursFor(?Program $program): float
    {
        return self::forProgram($program)['target_hours'];
    }

    public static function targetHoursForProfile(?StudentProfile $profile): float
    {
        return self::forProfile($profile)['target_hours'];
    }

    public static function hteCountFor(?Program $program): int
    {
        return self::forProgram($program)['hte_count'];
    }
}
