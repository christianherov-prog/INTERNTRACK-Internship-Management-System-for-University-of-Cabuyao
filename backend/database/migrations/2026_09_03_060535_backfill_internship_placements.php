<?php

use App\Models\Internship;
use App\Models\InternshipPlacement;
use App\Models\ProgramHteRequirement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfill internship_placements for all existing internships.
     * For each internship:
     * 1. Find the student's program
     * 2. Get the program's HTE requirements
     * 3. Create placement records for each requirement
     * 4. Distribute accumulated hours (all to first placement for legacy data)
     * 5. Set current_placement_id to the active placement
     * 6. Backfill attendance_logs.placement_id
     */
    public function up(): void
    {
        if (! Schema::hasTable('internship_placements') || ! Schema::hasTable('program_hte_requirements')) {
            return;
        }

        $internships = Internship::with('student.studentProfile.program')->get();
        
        echo "\n🔄 Backfilling placements for {$internships->count()} internships...\n";
        
        $successCount = 0;
        $skipCount = 0;

        foreach ($internships as $internship) {
            try {
                $program = $internship->student?->studentProfile?->program;
                
                if (!$program) {
                    echo "  ⚠ Skipping internship #{$internship->id}: No program found for student #{$internship->student_id}\n";
                    $skipCount++;
                    continue;
                }

                $requirements = ProgramHteRequirement::where('program_id', $program->id)
                    ->orderBy('sequence_order')
                    ->get();

                if ($requirements->isEmpty()) {
                    echo "  ⚠ Skipping internship #{$internship->id}: No HTE requirements for program {$program->code}\n";
                    $skipCount++;
                    continue;
                }

                // Create placement records
                $firstPlacement = null;
                $activePlacement = null;

                foreach ($requirements as $requirement) {
                    $placement = InternshipPlacement::create([
                        'internship_id' => $internship->id,
                        'program_hte_requirement_id' => $requirement->id,
                        'company_id' => $internship->company_id,
                        'supervisor_id' => $internship->supervisor_id,
                        'sequence_order' => $requirement->sequence_order,
                        'label' => $requirement->label,
                        'required_hours' => $requirement->required_hours,
                        'accumulated_hours' => 0.00,
                        'status' => 'pending',
                        'start_date' => $internship->start_date,
                        'end_date' => null,
                    ]);

                    if (!$firstPlacement) {
                        $firstPlacement = $placement;
                    }
                }

                // For legacy internships, assign all hours to the first placement
                if ($firstPlacement && $internship->total_hours_rendered > 0) {
                    $firstPlacement->update([
                        'accumulated_hours' => min($internship->total_hours_rendered, $firstPlacement->required_hours),
                        'status' => $internship->total_hours_rendered >= $firstPlacement->required_hours ? 'completed' : 'active',
                    ]);
                    $activePlacement = $firstPlacement;
                } elseif ($firstPlacement) {
                    // No hours yet, mark first as active if internship is active
                    if (in_array($internship->status, ['active', 'ongoing'])) {
                        $firstPlacement->update(['status' => 'active']);
                        $activePlacement = $firstPlacement;
                    }
                }

                // Distribute remaining hours to subsequent placements (for multi-placement programs)
                $remainingHours = $internship->total_hours_rendered - ($firstPlacement->accumulated_hours ?? 0);
                if ($remainingHours > 0 && $requirements->count() > 1) {
                    foreach ($requirements->skip(1) as $idx => $requirement) {
                        $placement = InternshipPlacement::where('internship_id', $internship->id)
                            ->where('sequence_order', $requirement->sequence_order)
                            ->first();
                        
                        if ($placement && $remainingHours > 0) {
                            $hoursToAssign = min($remainingHours, $placement->required_hours);
                            $placement->update([
                                'accumulated_hours' => $hoursToAssign,
                                'status' => $hoursToAssign >= $placement->required_hours ? 'completed' : 'active',
                            ]);
                            $remainingHours -= $hoursToAssign;
                            
                            if ($hoursToAssign > 0 && $hoursToAssign < $placement->required_hours) {
                                $activePlacement = $placement;
                            }
                        }
                    }
                }

                // Set current_placement_id
                if ($activePlacement) {
                    $internship->update(['current_placement_id' => $activePlacement->id]);
                } elseif ($firstPlacement) {
                    $internship->update(['current_placement_id' => $firstPlacement->id]);
                }

                // Backfill attendance_logs.placement_id (all to first placement for simplicity)
                if ($firstPlacement) {
                    DB::table('attendance_logs')
                        ->where('internship_id', $internship->id)
                        ->whereNull('placement_id')
                        ->update(['placement_id' => $firstPlacement->id]);
                }

                $successCount++;
                echo "  ✓ Internship #{$internship->id} ({$program->code}): {$requirements->count()} placement(s) created\n";

            } catch (\Exception $e) {
                echo "  ✗ Error processing internship #{$internship->id}: {$e->getMessage()}\n";
                $skipCount++;
            }
        }

        echo "\n✅ Backfill complete: {$successCount} successful, {$skipCount} skipped\n\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove all placement records and reset current_placement_id
        InternshipPlacement::truncate();
        
        DB::table('internships')->update(['current_placement_id' => null]);
        DB::table('attendance_logs')->update(['placement_id' => null]);
        
        echo "\n✅ Placement backfill reversed\n\n";
    }
};
