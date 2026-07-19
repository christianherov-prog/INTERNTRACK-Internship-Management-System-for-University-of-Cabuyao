<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->decimal('hours_rendered', 5, 2)->nullable();
            $table->decimal('overtime_hours', 5, 2)->default(0);

            $table->enum('status', [
                'pending',
                'validated',
                'rejected',
                'flagged'
            ])->default('pending');

            $table->text('remarks')->nullable()->comment('Supervisor validation remarks');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();

            $table->string('clock_in_location')->nullable()->comment('GPS coordinates or description');
            $table->string('clock_out_location')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['internship_id', 'date']);
            $table->index('status');
            $table->unique(['internship_id', 'date'], 'unique_attendance_per_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
