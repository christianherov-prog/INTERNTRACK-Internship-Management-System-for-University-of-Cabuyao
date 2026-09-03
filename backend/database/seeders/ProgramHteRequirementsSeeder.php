<?php

namespace Database\Seeders;

use App\Models\Internship;
use App\Models\Program;
use App\Models\ProgramHteRequirement;
use App\Services\InternshipPlacementService;
use Illuminate\Database\Seeder;

class ProgramHteRequirementsSeeder extends Seeder
{
    /**
     * Seed HTE requirements for all programs.
     * 
     * Single-HTE Programs (N=1):
     * - BSIT: 500h
     * - BSCS: 300h
     * - BSBA Marketing: 600h
     * - BSBA Financial Mgmt: 600h
     * - BS Accountancy: 400h
     * 
     * Multi-HTE Programs (N>1):
     * - BSED majors (Social Studies, Filipino, English, Mathematics): Public School (180h) + Private School (180h) = 360h
     * - BEED (Elementary Education): Public School (180h) + Private School (180h) = 360h
     * - Nursing: HTE 1-5 (540.6h each) = 2,703h
     * - Psychology: Clinical (150h) + Educational (150h) + Industrial/Organizational (150h) = 450h
     */
    public function run(): void
    {
        // Single-HTE Programs
        $this->seedSinglePlacement('BSIT', 'Primary Internship', 500.00);
        $this->seedSinglePlacement('BSCS', 'Primary Internship', 300.00);
        
        // CBAA Programs
        $this->seedSinglePlacement('BSBAMM', 'Primary Internship', 600.00);
        $this->seedSinglePlacement('BSBAFM', 'Primary Internship', 600.00);
        $this->seedSinglePlacement('BSA', 'Primary Internship', 400.00);

        // Education Programs (COED) - 2 placements each
        $this->seedEducationProgram('BSED');  // Keep for backward compatibility (inactive)
        $this->seedEducationProgram('BSEDSS'); // Social Studies
        $this->seedEducationProgram('BSEDF');  // Filipino
        $this->seedEducationProgram('BSEDE');  // English
        $this->seedEducationProgram('BSEDM');  // Mathematics
        $this->seedEducationProgram('BEED');   // Elementary Education

        // Nursing (CHAS) - 5 placements
        $this->seedNursingProgram();

        // Psychology (CAS) - 3 placements
        $this->seedPsychologyProgram();

        Internship::query()->with('student.studentProfile.program')->each(function (Internship $internship) {
            InternshipPlacementService::ensurePlacements($internship);
        });

        $this->command->info('✓ Program HTE requirements seeded successfully.');
    }

    private function seedSinglePlacement(string $code, string $label, float $hours): void
    {
        $program = Program::where('code', $code)->first();
        
        if (!$program) {
            $this->command->warn("⚠ Program '{$code}' not found. Skipping.");
            return;
        }

        ProgramHteRequirement::updateOrCreate(
            ['program_id' => $program->id, 'sequence_order' => 1],
            ['label' => $label, 'required_hours' => $hours]
        );

        $this->command->info("  → {$code}: {$label} ({$hours}h)");
    }

    private function seedEducationProgram(string $code): void
    {
        $program = Program::where('code', $code)->first();
        
        if (!$program) {
            $this->command->warn("⚠ Program '{$code}' not found. Skipping.");
            return;
        }

        $placements = [
            ['sequence_order' => 1, 'label' => 'Public School HTE', 'required_hours' => 180.00],
            ['sequence_order' => 2, 'label' => 'Private School HTE', 'required_hours' => 180.00],
        ];

        foreach ($placements as $placement) {
            ProgramHteRequirement::updateOrCreate(
                ['program_id' => $program->id, 'sequence_order' => $placement['sequence_order']],
                ['label' => $placement['label'], 'required_hours' => $placement['required_hours']]
            );
        }

        $this->command->info("  → {$code}: Public School HTE (180h) + Private School HTE (180h) = 360h total");
    }

    private function seedNursingProgram(): void
    {
        $program = Program::where('code', 'BSN')->first();
        
        if (!$program) {
            // Try alternate code
            $program = Program::where('name', 'LIKE', '%Nursing%')->first();
        }
        
        if (!$program) {
            $this->command->warn("⚠ Nursing program not found. Skipping.");
            return;
        }

        for ($i = 1; $i <= 5; $i++) {
            ProgramHteRequirement::updateOrCreate(
                ['program_id' => $program->id, 'sequence_order' => $i],
                ['label' => "HTE {$i}", 'required_hours' => 540.60]
            );
        }

        $this->command->info("  → Nursing: HTE 1-5 (540.6h each) = 2,703h total");
    }

    private function seedPsychologyProgram(): void
    {
        $program = Program::where('code', 'BSPSY')->first();
        
        if (!$program) {
            $this->command->warn("⚠ Psychology program not found. Skipping.");
            return;
        }

        $placements = [
            ['sequence_order' => 1, 'label' => 'Clinical Setting', 'required_hours' => 150.00],
            ['sequence_order' => 2, 'label' => 'Educational Setting', 'required_hours' => 150.00],
            ['sequence_order' => 3, 'label' => 'Industrial/Organizational Setting', 'required_hours' => 150.00],
        ];

        foreach ($placements as $placement) {
            ProgramHteRequirement::updateOrCreate(
                ['program_id' => $program->id, 'sequence_order' => $placement['sequence_order']],
                ['label' => $placement['label'], 'required_hours' => $placement['required_hours']]
            );
        }

        $this->command->info("  → Psychology: Clinical (150h) + Educational (150h) + Industrial/Organizational (150h) = 450h total");
    }
}
