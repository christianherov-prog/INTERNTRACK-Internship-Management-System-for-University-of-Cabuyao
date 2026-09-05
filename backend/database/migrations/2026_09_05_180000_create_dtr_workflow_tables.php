<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained('internships')->cascadeOnDelete();
            $table->foreignId('proposed_by')->constrained('users')->cascadeOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status', 32)->default('pending');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();
            $table->timestamps();

            $table->index(['internship_id', 'status']);
            $table->index(['internship_id', 'effective_from', 'effective_to']);
        });

        Schema::create('overtime_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained('internships')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('attendance_log_id')->constrained('attendance_logs')->cascadeOnDelete();
            $table->unsignedInteger('excess_minutes');
            $table->string('status', 32)->default('pending');
            $table->decimal('original_hours_rendered', 5, 2)->nullable();
            $table->decimal('original_overtime_hours', 5, 2)->nullable();
            $table->time('detected_clock_out')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();
            $table->decimal('applied_hours_rendered', 5, 2)->nullable();
            $table->decimal('applied_overtime_hours', 5, 2)->nullable();
            $table->timestamps();

            $table->index(['internship_id', 'status']);
            $table->index('attendance_log_id');
        });

        Schema::create('attendance_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained('internships')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('attendance_log_id')->nullable()->constrained('attendance_logs')->nullOnDelete();
            $table->date('date');
            $table->time('original_clock_in')->nullable();
            $table->time('original_clock_out')->nullable();
            $table->decimal('original_hours_rendered', 5, 2)->nullable();
            $table->time('requested_clock_in')->nullable();
            $table->time('requested_clock_out')->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 32)->default('pending_supervisor');
            $table->foreignId('supervisor_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_reviewed_at')->nullable();
            $table->text('supervisor_remarks')->nullable();
            $table->string('supervisor_decision', 16)->nullable();
            $table->foreignId('faculty_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('faculty_reviewed_at')->nullable();
            $table->text('faculty_remarks')->nullable();
            $table->string('faculty_decision', 16)->nullable();
            $table->string('rejected_by_role', 32)->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->time('applied_clock_in')->nullable();
            $table->time('applied_clock_out')->nullable();
            $table->decimal('applied_hours_rendered', 5, 2)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['internship_id', 'status']);
            $table->index(['internship_id', 'date']);
        });

        Schema::create('dtr_request_audits', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->foreignId('internship_id')->constrained('internships')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('event', 64);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 32)->nullable();
            $table->json('original_state')->nullable();
            $table->json('requested_values')->nullable();
            $table->string('decision', 32)->nullable();
            $table->text('remarks')->nullable();
            $table->json('final_applied_value')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['internship_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dtr_request_audits');
        Schema::dropIfExists('attendance_correction_requests');
        Schema::dropIfExists('overtime_entries');
        Schema::dropIfExists('work_schedules');
    }
};
