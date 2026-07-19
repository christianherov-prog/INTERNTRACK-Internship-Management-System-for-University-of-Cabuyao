<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Convert document_type from a fixed ENUM to a flexible VARCHAR.
     * The Internship Manual's required document list can change without
     * requiring a schema migration every time a form is renamed or added.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE documents MODIFY COLUMN document_type VARCHAR(255) NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE documents MODIFY COLUMN document_type ENUM(
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
            'Other'
        ) NOT NULL");
    }
};
