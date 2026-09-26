<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dedupeJournalWeeks();
        $this->dedupeDocuments();
        $this->dedupeApplications();

        Schema::table('journal_entries', function (Blueprint $table) {
            if (! $this->indexExists('journal_entries', 'journal_entries_internship_week_unique')) {
                $table->unique(['internship_id', 'week_number'], 'journal_entries_internship_week_unique');
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            if (! $this->indexExists('documents', 'documents_internship_type_unique')) {
                $table->unique(['internship_id', 'document_type'], 'documents_internship_type_unique');
            }
        });

        Schema::table('internship_applications', function (Blueprint $table) {
            if (! $this->indexExists('internship_applications', 'internship_applications_student_company_unique')) {
                $table->unique(['student_id', 'company_id'], 'internship_applications_student_company_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique('journal_entries_internship_week_unique');
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique('documents_internship_type_unique');
        });
        Schema::table('internship_applications', function (Blueprint $table) {
            $table->dropUnique('internship_applications_student_company_unique');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = Schema::getConnection()->getDatabaseName();
        $rows = DB::select(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$database, $table, $index]
        );

        return $rows !== [];
    }

    private function dedupeJournalWeeks(): void
    {
        $dupes = DB::select(
            'SELECT internship_id, week_number, MIN(id) AS keep_id
             FROM journal_entries
             WHERE deleted_at IS NULL AND week_number IS NOT NULL
             GROUP BY internship_id, week_number
             HAVING COUNT(*) > 1'
        );

        foreach ($dupes as $row) {
            DB::table('journal_entries')
                ->where('internship_id', $row->internship_id)
                ->where('week_number', $row->week_number)
                ->whereNull('deleted_at')
                ->where('id', '!=', $row->keep_id)
                ->update(['deleted_at' => now()]);
        }
    }

    private function dedupeDocuments(): void
    {
        $dupes = DB::select(
            'SELECT internship_id, document_type, MIN(id) AS keep_id
             FROM documents
             WHERE deleted_at IS NULL
             GROUP BY internship_id, document_type
             HAVING COUNT(*) > 1'
        );

        foreach ($dupes as $row) {
            DB::table('documents')
                ->where('internship_id', $row->internship_id)
                ->where('document_type', $row->document_type)
                ->whereNull('deleted_at')
                ->where('id', '!=', $row->keep_id)
                ->update(['deleted_at' => now()]);
        }
    }

    private function dedupeApplications(): void
    {
        $dupes = DB::select(
            'SELECT student_id, company_id, MIN(id) AS keep_id
             FROM internship_applications
             GROUP BY student_id, company_id
             HAVING COUNT(*) > 1'
        );

        foreach ($dupes as $row) {
            DB::table('internship_applications')
                ->where('student_id', $row->student_id)
                ->where('company_id', $row->company_id)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }
    }
};
