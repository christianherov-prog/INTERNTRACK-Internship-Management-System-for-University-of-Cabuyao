<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained()->cascadeOnDelete();
            $table->enum('document_type', [
                'Curriculum Vitae (PNC:AA-FO-27)',
                'Medical Clearance',
                'Psychological Assessment Certificate',
                'Notarized Student Internship Consent Form (PNC:AA-FO-28)',
                'Student Internship Acceptance Form (PNC:AA-FO-29)',
                'Application Letter',
                'Recommendation Letter',
                'MOA / LOA / TOR',
                'Company Profile',
                'Training Plan',
                'Midterm Evaluation',
                'Final Report',
                'Certificate of Completion',
                'Other',
            ]);
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_size')->nullable();
            $table->string('mime_type')->nullable();

            $table->string('status', 40)->default('pending_review');

            $table->text('remarks')->nullable()->comment('Reviewer feedback');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['internship_id', 'document_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
