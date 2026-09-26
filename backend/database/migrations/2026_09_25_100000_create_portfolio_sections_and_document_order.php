<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured portfolio composition:
 *  - portfolio_sections: one row per student-authored narrative section
 *    (biographical sketch, acknowledgment, narrative insights, recommendations…)
 *    linked to the student's portfolio for that internship.
 *  - documents.sort_order: student-controlled ordering for portfolio photo galleries.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portfolio_sections')) {
            Schema::create('portfolio_sections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_portfolio_id')->constrained('student_portfolios')->cascadeOnDelete();
                $table->string('section_key', 60);
                $table->longText('content')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['student_portfolio_id', 'section_key'], 'portfolio_sections_portfolio_key_unique');
            });
        }

        if (! Schema::hasColumn('documents', 'sort_order')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->nullable()->after('week_number');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_sections');

        if (Schema::hasColumn('documents', 'sort_order')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }
    }
};
