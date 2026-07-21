<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Role-scoped messaging: history follows internship + role pair so a newly
 * assigned faculty/supervisor/coordinator can see prior conversation history.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'sender_role')) {
                $table->string('sender_role', 32)->nullable()->after('sender_id');
            }
            if (!Schema::hasColumn('messages', 'recipient_role')) {
                $table->string('recipient_role', 32)->nullable()->after('recipient_id');
            }
        });

        if (Schema::hasColumn('messages', 'sender_role')) {
            // Backfill from users.role for existing rows
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement('UPDATE messages m INNER JOIN users u ON u.id = m.sender_id SET m.sender_role = u.role WHERE m.sender_role IS NULL');
                DB::statement('UPDATE messages m INNER JOIN users u ON u.id = m.recipient_id SET m.recipient_role = u.role WHERE m.recipient_role IS NULL');
            } else {
                // SQLite / others: row-by-row via query builder
                foreach (DB::table('messages')->whereNull('sender_role')->orWhereNull('recipient_role')->get() as $row) {
                    $senderRole = DB::table('users')->where('id', $row->sender_id)->value('role');
                    $recipientRole = DB::table('users')->where('id', $row->recipient_id)->value('role');
                    DB::table('messages')->where('id', $row->id)->update([
                        'sender_role' => $row->sender_role ?: $senderRole,
                        'recipient_role' => $row->recipient_role ?: $recipientRole,
                    ]);
                }
            }
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['internship_id', 'sender_role', 'recipient_role'], 'messages_internship_roles_index');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_internship_roles_index');
            $table->dropColumn(['sender_role', 'recipient_role']);
        });
    }
};
