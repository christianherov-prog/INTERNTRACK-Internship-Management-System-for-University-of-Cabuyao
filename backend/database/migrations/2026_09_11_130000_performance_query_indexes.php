<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes justified by attendance / dashboard / notification query patterns.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->ensureIndex('internships', 'internships_student_id_status_index', function (Blueprint $table) {
            $table->index(['student_id', 'status'], 'internships_student_id_status_index');
        });
        $this->ensureIndex('internships', 'internships_supervisor_id_status_index', function (Blueprint $table) {
            $table->index(['supervisor_id', 'status'], 'internships_supervisor_id_status_index');
        });
        $this->ensureIndex('attendance_logs', 'attendance_logs_internship_id_date_index', function (Blueprint $table) {
            $table->index(['internship_id', 'date'], 'attendance_logs_internship_id_date_index');
        });
        $this->ensureIndex('attendance_logs', 'attendance_logs_validated_by_index', function (Blueprint $table) {
            $table->index('validated_by', 'attendance_logs_validated_by_index');
        });

        if (Schema::hasTable('notifications')) {
            $this->ensureIndex('notifications', 'notifications_user_id_read_at_index', function (Blueprint $table) {
                $table->index(['user_id', 'read_at'], 'notifications_user_id_read_at_index');
            });
        }
        if (Schema::hasTable('journal_entries')) {
            $this->ensureIndex('journal_entries', 'journal_entries_internship_id_week_index', function (Blueprint $table) {
                $table->index(['internship_id', 'week_number'], 'journal_entries_internship_id_week_index');
            });
        }
        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'recipient_id')) {
            $this->ensureIndex('messages', 'messages_recipient_id_created_at_index', function (Blueprint $table) {
                $table->index(['recipient_id', 'created_at'], 'messages_recipient_id_created_at_index');
            });
            $this->ensureIndex('messages', 'messages_sender_id_created_at_index', function (Blueprint $table) {
                $table->index(['sender_id', 'created_at'], 'messages_sender_id_created_at_index');
            });
        }

        if (Schema::hasTable('audit_logs')) {
            $this->ensureIndex('audit_logs', 'audit_logs_action_created_at_index', function (Blueprint $table) {
                $table->index(['action', 'created_at'], 'audit_logs_action_created_at_index');
            });
            $this->ensureIndex('audit_logs', 'audit_logs_created_at_index', function (Blueprint $table) {
                $table->index('created_at', 'audit_logs_created_at_index');
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'internships' => ['internships_student_id_status_index', 'internships_supervisor_id_status_index'],
            'attendance_logs' => ['attendance_logs_internship_id_date_index', 'attendance_logs_validated_by_index'],
            'notifications' => ['notifications_user_id_read_at_index'],
            'journal_entries' => ['journal_entries_internship_id_week_index'],
            'messages' => ['messages_recipient_id_created_at_index', 'messages_sender_id_created_at_index'],
            'audit_logs' => ['audit_logs_action_created_at_index', 'audit_logs_created_at_index'],
        ] as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $name) {
                if ($this->indexExists($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
                }
            }
        }
    }

    private function ensureIndex(string $table, string $name, callable $add): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $name)) {
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

        return (int) ($row->c ?? 0) > 0;
    }
};
