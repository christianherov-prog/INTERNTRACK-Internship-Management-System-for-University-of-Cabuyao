<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faculty-set Weekly Journal deadlines, scoped per internship and internship week.
 * Journal rows keep the real submission timestamp and the deadline that applied.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('journal_deadlines')) {
            Schema::create('journal_deadlines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('internship_id')->constrained('internships')->cascadeOnDelete();
                $table->unsignedInteger('week_number');
                $table->timestamp('due_at');
                $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['internship_id', 'week_number'], 'journal_deadlines_internship_week_unique');
            });
        }

        Schema::table('journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_entries', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('journal_entries', 'deadline_at')) {
                $table->timestamp('deadline_at')->nullable()->after('submitted_at');
            }
            if (! Schema::hasColumn('journal_entries', 'submitted_late')) {
                $table->boolean('submitted_late')->default(false)->after('deadline_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            foreach (['submitted_late', 'deadline_at', 'submitted_at'] as $column) {
                if (Schema::hasColumn('journal_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::dropIfExists('journal_deadlines');
    }
};
