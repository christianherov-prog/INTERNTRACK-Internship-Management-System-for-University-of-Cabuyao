<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained()->cascadeOnDelete();
            $table->enum('evaluator_type', ['supervisor', 'faculty'])->comment('Who performed this evaluation');
            $table->foreignId('evaluated_by')->constrained('users')->restrictOnDelete();
            $table->enum('evaluation_period', ['midterm', 'final']);

            // Core competency scores (1-5 scale per UC rubric)
            $table->decimal('technical_skills', 4, 2)->nullable();
            $table->decimal('communication_skills', 4, 2)->nullable();
            $table->decimal('teamwork', 4, 2)->nullable();
            $table->decimal('initiative', 4, 2)->nullable();
            $table->decimal('work_ethics', 4, 2)->nullable();
            $table->decimal('attendance_punctuality', 4, 2)->nullable();
            $table->decimal('adaptability', 4, 2)->nullable();
            $table->decimal('problem_solving', 4, 2)->nullable();

            $table->decimal('total_score', 6, 2)->nullable()->comment('Computed total');
            $table->decimal('average_score', 5, 2)->nullable();
            $table->string('rating')->nullable()->comment('e.g. Excellent, Very Good, Good');

            $table->text('strengths')->nullable();
            $table->text('areas_for_improvement')->nullable();
            $table->text('general_comments')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['internship_id', 'evaluation_period', 'evaluator_type']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('content');
            $table->string('target_role')->default('all')
                  ->comment('all, student, supervisor, faculty, coordinator, director');
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['target_role', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('evaluations');
    }
};
