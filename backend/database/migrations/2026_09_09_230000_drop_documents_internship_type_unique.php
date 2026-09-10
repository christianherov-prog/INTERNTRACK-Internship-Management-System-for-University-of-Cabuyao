<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        if ($this->indexExists('documents', 'documents_internship_type_unique')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropUnique('documents_internship_type_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        if (! $this->indexExists('documents', 'documents_internship_type_unique')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->unique(['internship_id', 'document_type'], 'documents_internship_type_unique');
            });
        }
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
};
