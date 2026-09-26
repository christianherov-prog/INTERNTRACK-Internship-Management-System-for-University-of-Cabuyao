<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reconcile College/Program data with authoritative list.
     * 
     * Changes:
     * 1. COE: Add Industrial Engineering and Electronics Engineering
     * 2. COE: Delete Civil Engineering (BSCE), reassign student to Computer Engineering
     * 3. COED: Create 4 specific BSED major programs
     * 4. COED: Migrate BSED student to Social Studies major, mark generic BSED inactive
     */
    public function up(): void
    {
        // Get department IDs
        $coe = DB::table('departments')->where('code', 'COE')->first();
        $coed = DB::table('departments')->where('code', 'COED')->first();
        
        if (!$coe || !$coed) {
            echo "⚠️  COE or COED department not found. Skipping migration.\n";
            return;
        }

        // ─────────────────────────────────────────────────────────────────────
        // 1. COE: Add missing programs
        // ─────────────────────────────────────────────────────────────────────
        
        echo "📝 Adding COE programs...\n";
        
        $bsie = DB::table('programs')->insertGetId([
            'department_id' => $coe->id,
            'code' => 'BSIE',
            'name' => 'Bachelor of Science in Industrial Engineering',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "  ✓ Created BSIE (ID: {$bsie})\n";
        
        $bsece = DB::table('programs')->insertGetId([
            'department_id' => $coe->id,
            'code' => 'BSECE',
            'name' => 'Bachelor of Science in Electronics Engineering',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "  ✓ Created BSECE (ID: {$bsece})\n";

        // ─────────────────────────────────────────────────────────────────────
        // 2. COE: Migrate BSCE student to BSCPE, then delete BSCE
        // ─────────────────────────────────────────────────────────────────────
        
        echo "\n📝 Handling Civil Engineering (BSCE)...\n";
        
        $bsce = DB::table('programs')->where('code', 'BSCE')->first();
        $bscpe = DB::table('programs')->where('code', 'BSCPE')->first();
        
        if ($bsce && $bscpe) {
            // Migrate students from BSCE to BSCPE
            $migratedCount = DB::table('student_profiles')
                ->where('program_id', $bsce->id)
                ->update(['program_id' => $bscpe->id, 'updated_at' => now()]);
            
            echo "  ✓ Migrated {$migratedCount} student(s) from BSCE to BSCPE\n";
            
            // Delete BSCE program
            DB::table('programs')->where('id', $bsce->id)->delete();
            echo "  ✓ Deleted BSCE program\n";
        } else {
            echo "  ⚠️  BSCE or BSCPE not found. Skipping.\n";
        }

        // ─────────────────────────────────────────────────────────────────────
        // 3. COED: Create 4 specific BSED major programs
        // ─────────────────────────────────────────────────────────────────────
        
        echo "\n📝 Creating COED BSED major programs...\n";
        
        $bsedPrograms = [
            ['code' => 'BSEDSS', 'name' => 'Bachelor of Secondary Education major in Social Studies'],
            ['code' => 'BSEDF', 'name' => 'Bachelor of Secondary Education major in Filipino'],
            ['code' => 'BSEDE', 'name' => 'Bachelor of Secondary Education major in English'],
            ['code' => 'BSEDM', 'name' => 'Bachelor of Secondary Education major in Mathematics'],
        ];
        
        $newBsedIds = [];
        foreach ($bsedPrograms as $prog) {
            $id = DB::table('programs')->insertGetId([
                'department_id' => $coed->id,
                'code' => $prog['code'],
                'name' => $prog['name'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $newBsedIds[$prog['code']] = $id;
            echo "  ✓ Created {$prog['code']} (ID: {$id})\n";
        }

        // ─────────────────────────────────────────────────────────────────────
        // 4. COED: Migrate BSED student to Social Studies, mark generic BSED inactive
        // ─────────────────────────────────────────────────────────────────────
        
        echo "\n📝 Migrating BSED students...\n";
        
        $genericBsed = DB::table('programs')->where('code', 'BSED')->where('department_id', $coed->id)->first();
        
        if ($genericBsed && isset($newBsedIds['BSEDSS'])) {
            // Migrate students to Social Studies
            $migratedCount = DB::table('student_profiles')
                ->where('program_id', $genericBsed->id)
                ->update(['program_id' => $newBsedIds['BSEDSS'], 'updated_at' => now()]);
            
            echo "  ✓ Migrated {$migratedCount} student(s) from generic BSED to BSED Social Studies\n";
            
            // Mark generic BSED as inactive (preserve for historical HTE requirements)
            DB::table('programs')
                ->where('id', $genericBsed->id)
                ->update(['is_active' => false, 'updated_at' => now()]);
            
            echo "  ✓ Marked generic BSED as inactive (ID: {$genericBsed->id})\n";
        } else {
            echo "  ⚠️  Generic BSED or new BSEDSS not found. Skipping.\n";
        }

        echo "\n✅ College/Program reconciliation complete!\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Get department IDs
        $coe = DB::table('departments')->where('code', 'COE')->first();
        $coed = DB::table('departments')->where('code', 'COED')->first();
        
        if (!$coe || !$coed) {
            return;
        }

        // Reactivate generic BSED
        DB::table('programs')
            ->where('code', 'BSED')
            ->where('department_id', $coed->id)
            ->update(['is_active' => true, 'updated_at' => now()]);

        // Delete new BSED major programs
        DB::table('programs')->where('department_id', $coed->id)
            ->whereIn('code', ['BSEDSS', 'BSEDF', 'BSEDE', 'BSEDM'])
            ->delete();

        // Delete new COE programs
        DB::table('programs')->where('department_id', $coe->id)
            ->whereIn('code', ['BSIE', 'BSECE'])
            ->delete();
        
        // Note: We cannot restore BSCE or migrate students back automatically
        // as we don't know which students were originally in BSCE vs BSCPE
        echo "⚠️  Manual intervention required to restore BSCE and student assignments if needed.\n";
    }
};
