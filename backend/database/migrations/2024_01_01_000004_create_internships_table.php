<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('internships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('faculty_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('academic_year')->comment('e.g. 2024-2025');
            $table->tinyInteger('semester');
            $table->string('term')->comment('e.g. AY 2024-2025, Sem 2');
            $table->string('program')->nullable();
            $table->integer('target_hours')->default(360);
            $table->decimal('total_hours_rendered', 8, 2)->default(0);

            $table->enum('status', [
                'pending_placement',
                'placed',
                'ongoing',
                'for_evaluation',
                'completed',
                'terminated',
                'failed'
            ])->default('pending_placement');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('expected_end_date')->nullable();

            $table->text('termination_reason')->nullable();
            $table->decimal('final_grade', 5, 2)->nullable();
            $table->string('final_remarks')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'academic_year', 'semester']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internships');
    }
};
