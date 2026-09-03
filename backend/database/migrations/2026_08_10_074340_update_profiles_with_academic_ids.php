<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add foreign keys
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->foreignId('program_id')->nullable()->constrained('programs')->onDelete('set null');
        });

        Schema::table('faculty_profiles', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
        });

        // 2. Data Migration
        // Extract distinct departments from both profiles
        $departments = array_unique(array_merge(
            \DB::table('student_profiles')->whereNotNull('department')->where('department', '!=', '')->pluck('department')->toArray(),
            \DB::table('faculty_profiles')->whereNotNull('department')->where('department', '!=', '')->pluck('department')->toArray()
        ));

        $deptMap = [];
        foreach ($departments as $deptName) {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($deptName, 0, 10)));
            // Ensure unique code
            $baseCode = $code;
            $counter = 1;
            while (in_array($code, array_column($deptMap, 'code'))) {
                $code = $baseCode . $counter++;
            }
            $deptId = \DB::table('departments')->insertGetId([
                'name' => $deptName,
                'code' => $code,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $deptMap[$deptName] = ['id' => $deptId, 'code' => $code];
        }

        // Extract distinct programs from students
        $programs = \DB::table('student_profiles')
            ->whereNotNull('program')
            ->where('program', '!=', '')
            ->select('program', 'department')
            ->distinct()
            ->get();

        $progMap = [];
        foreach ($programs as $prog) {
            $deptId = isset($deptMap[$prog->department]) ? $deptMap[$prog->department]['id'] : null;
            // If no department is set for the student, create a generic "Unassigned" department if we must, 
            // but the column is nullable so we can skip it, except Program requires a department_id!
            if (!$deptId) {
                // Check if an "Unassigned" department exists
                $unassigned = \DB::table('departments')->where('code', 'UNASSIGNED')->first();
                if (!$unassigned) {
                    $deptId = \DB::table('departments')->insertGetId([
                        'name' => 'Unassigned Department',
                        'code' => 'UNASSIGNED',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $deptId = $unassigned->id;
                }
            }

            $progName = $prog->program;
            // Handle duplicates in case different departments had the same program string
            if (!isset($progMap[$progName])) {
                $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($progName, 0, 10)));
                $baseCode = $code;
                $counter = 1;
                while (\DB::table('programs')->where('code', $code)->exists()) {
                    $code = $baseCode . $counter++;
                }

                $progId = \DB::table('programs')->insertGetId([
                    'department_id' => $deptId,
                    'name' => $progName,
                    'code' => $code,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $progMap[$progName] = $progId;
            }
        }

        // 3. Update existing records
        $students = \DB::table('student_profiles')->get();
        foreach ($students as $s) {
            $dId = $s->department && isset($deptMap[$s->department]) ? $deptMap[$s->department]['id'] : null;
            $pId = $s->program && isset($progMap[$s->program]) ? $progMap[$s->program] : null;
            
            \DB::table('student_profiles')
                ->where('id', $s->id)
                ->update([
                    'department_id' => $dId,
                    'program_id' => $pId,
                ]);
        }

        $faculties = \DB::table('faculty_profiles')->get();
        foreach ($faculties as $f) {
            $dId = $f->department && isset($deptMap[$f->department]) ? $deptMap[$f->department]['id'] : null;
            
            \DB::table('faculty_profiles')
                ->where('id', $f->id)
                ->update([
                    'department_id' => $dId,
                ]);
        }

        // 4. Drop old columns
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn(['department', 'program']);
        });

        Schema::table('faculty_profiles', function (Blueprint $table) {
            $table->dropColumn(['department']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('department')->nullable();
            $table->string('program')->nullable();
        });

        Schema::table('faculty_profiles', function (Blueprint $table) {
            $table->string('department')->nullable();
        });

        // Best effort reverse mapping
        $students = \DB::table('student_profiles')->get();
        foreach ($students as $s) {
            $deptName = $s->department_id ? \DB::table('departments')->where('id', $s->department_id)->value('name') : null;
            $progName = $s->program_id ? \DB::table('programs')->where('id', $s->program_id)->value('name') : null;
            \DB::table('student_profiles')->where('id', $s->id)->update([
                'department' => $deptName,
                'program' => $progName,
            ]);
        }

        $faculties = \DB::table('faculty_profiles')->get();
        foreach ($faculties as $f) {
            $deptName = $f->department_id ? \DB::table('departments')->where('id', $f->department_id)->value('name') : null;
            \DB::table('faculty_profiles')->where('id', $f->id)->update([
                'department' => $deptName,
            ]);
        }

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['program_id']);
            $table->dropColumn(['department_id', 'program_id']);
        });

        Schema::table('faculty_profiles', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id']);
        });
    }
};
