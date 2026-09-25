<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The system-generated OJT completion certificate is outside the project's
 * objectives and was removed (no endpoint, UI, or generation remains).
 * These two columns existed only for it (added by the former
 * 2026_08_03_133350_add_certificate_fields_to_internships_table).
 *
 * Not affected: the "Certificate of Completion" the HTE issues and the
 * Student uploads as a compliance document (documents / portfolio uploads).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['certificate_eligible', 'certificate_issued_at'] as $column) {
            if (Schema::hasColumn('internships', $column)) {
                Schema::table('internships', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('internships', function (Blueprint $table) {
            if (! Schema::hasColumn('internships', 'certificate_eligible')) {
                $table->boolean('certificate_eligible')->default(false);
            }
            if (! Schema::hasColumn('internships', 'certificate_issued_at')) {
                $table->timestamp('certificate_issued_at')->nullable();
            }
        });
    }
};
