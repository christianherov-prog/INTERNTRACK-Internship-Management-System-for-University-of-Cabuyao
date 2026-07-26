<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Align name parts with iEnroll: middle_name (supervisors) + suffix (all profiles).
 * Suffix examples: Jr., Sr., II, III — nullable when not applicable.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('student_profiles') && !Schema::hasColumn('student_profiles', 'suffix')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->string('suffix', 30)->nullable()->after('last_name');
            });
        }

        if (Schema::hasTable('faculty_profiles') && !Schema::hasColumn('faculty_profiles', 'suffix')) {
            Schema::table('faculty_profiles', function (Blueprint $table) {
                $table->string('suffix', 30)->nullable()->after('last_name');
            });
        }

        if (Schema::hasTable('supervisor_profiles')) {
            Schema::table('supervisor_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('supervisor_profiles', 'middle_name')) {
                    $table->string('middle_name')->nullable()->after('first_name');
                }
                if (!Schema::hasColumn('supervisor_profiles', 'suffix')) {
                    $table->string('suffix', 30)->nullable()->after('last_name');
                }
            });
        }

        if (Schema::hasTable('supervisor_invite_tokens')) {
            Schema::table('supervisor_invite_tokens', function (Blueprint $table) {
                if (!Schema::hasColumn('supervisor_invite_tokens', 'middle_name')) {
                    $table->string('middle_name')->nullable()->after('first_name');
                }
                if (!Schema::hasColumn('supervisor_invite_tokens', 'suffix')) {
                    $table->string('suffix', 30)->nullable()->after('last_name');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['student_profiles', 'faculty_profiles', 'supervisor_profiles', 'supervisor_invite_tokens'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'suffix')) {
                    $blueprint->dropColumn('suffix');
                }
                if ($table !== 'student_profiles' && $table !== 'faculty_profiles'
                    && Schema::hasColumn($table, 'middle_name')) {
                    // Only drop middle_name we added for supervisor tables
                    if (in_array($table, ['supervisor_profiles', 'supervisor_invite_tokens'], true)) {
                        $blueprint->dropColumn('middle_name');
                    }
                }
            });
        }
    }
};
