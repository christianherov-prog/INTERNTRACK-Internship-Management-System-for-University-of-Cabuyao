<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log indexes for admin list/filter performance.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $this->ensureIndex('audit_logs', 'audit_logs_action_created_at_index', function (Blueprint $table) {
            $table->index(['action', 'created_at'], 'audit_logs_action_created_at_index');
        });
        $this->ensureIndex('audit_logs', 'audit_logs_created_at_index', function (Blueprint $table) {
            $table->index('created_at', 'audit_logs_created_at_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }
        foreach (['audit_logs_action_created_at_index', 'audit_logs_created_at_index'] as $name) {
            if ($this->indexExists('audit_logs', $name)) {
                Schema::table('audit_logs', fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
            }
        }
    }

    private function ensureIndex(string $table, string $name, callable $add): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, $add);
    }

    private function indexExists(string $table, string $name): bool
    {
        $db = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$db, $table, $name]
        );

        return ((int) ($row->c ?? 0)) > 0;
    }
};
