<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('entry_number');
            $table->date('date');
            $table->text('activities_summary');
            $table->text('learnings')->nullable()->comment('What the student learned');
            $table->text('challenges')->nullable();
            $table->decimal('hours_declared', 5, 2)->default(8);

            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'needs_revision',
                'rejected'
            ])->default('draft');

            // Supervisor review
            $table->text('supervisor_feedback')->nullable();
            $table->foreignId('supervisor_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_reviewed_at')->nullable();

            // Faculty review
            $table->text('faculty_feedback')->nullable();
            $table->foreignId('faculty_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('faculty_reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['internship_id', 'date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
