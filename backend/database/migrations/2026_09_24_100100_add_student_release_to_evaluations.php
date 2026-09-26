<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student visibility release for supervisor-submitted forms:
 * FO-24 is released by the assigned Faculty, FO-03 by the Director.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            if (! Schema::hasColumn('evaluations', 'released_to_student_at')) {
                $table->timestamp('released_to_student_at')->nullable()->after('signed_at');
            }
            if (! Schema::hasColumn('evaluations', 'released_to_student_by')) {
                $table->foreignId('released_to_student_by')->nullable()->after('released_to_student_at')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            if (Schema::hasColumn('evaluations', 'released_to_student_by')) {
                $table->dropConstrainedForeignId('released_to_student_by');
            }
            if (Schema::hasColumn('evaluations', 'released_to_student_at')) {
                $table->dropColumn('released_to_student_at');
            }
        });
    }
};
