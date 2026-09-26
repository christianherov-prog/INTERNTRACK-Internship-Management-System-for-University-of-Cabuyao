<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * System-wide InternTrack workflow fields:
 * - Supervisor login_username (unique, case-insensitive)
 * - Company organization_type
 * - Attendance break_start / break_end
 * - Attendance correction_type
 * - Requirement templates is_system flag
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'login_username')) {
                $table->string('login_username', 50)->nullable()->after('faculty_number');
            }
        });

        // Case-insensitive uniqueness via generated/functional approach: store normalized lowercase
        // and add unique index on login_username.
        if (Schema::hasColumn('users', 'login_username')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->unique('login_username', 'users_login_username_unique');
                });
            } catch (\Throwable) {
                // Index may already exist.
            }
        }

        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'organization_type')) {
                $table->string('organization_type', 50)->nullable();
            }
        });

        Schema::table('hte_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('hte_requests', 'organization_type')) {
                $table->string('organization_type', 50)->nullable();
            }
        });

        Schema::table('attendance_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_logs', 'break_start')) {
                $table->dateTime('break_start')->nullable()->after('clock_out');
            }
            if (! Schema::hasColumn('attendance_logs', 'break_end')) {
                $table->dateTime('break_end')->nullable()->after('break_start');
            }
            if (! Schema::hasColumn('attendance_logs', 'on_break')) {
                $table->boolean('on_break')->default(false)->after('break_end');
            }
        });

        Schema::table('attendance_correction_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_correction_requests', 'correction_type')) {
                $table->string('correction_type', 30)->nullable()->after('date');
            }
            if (! Schema::hasColumn('attendance_correction_requests', 'original_break_start')) {
                $table->dateTime('original_break_start')->nullable()->after('original_clock_out');
            }
            if (! Schema::hasColumn('attendance_correction_requests', 'original_break_end')) {
                $table->dateTime('original_break_end')->nullable()->after('original_break_start');
            }
            if (! Schema::hasColumn('attendance_correction_requests', 'requested_break_start')) {
                $table->dateTime('requested_break_start')->nullable()->after('requested_clock_out');
            }
            if (! Schema::hasColumn('attendance_correction_requests', 'requested_break_end')) {
                $table->dateTime('requested_break_end')->nullable()->after('requested_break_start');
            }
        });

        if (Schema::hasTable('ojt_requirement_templates')) {
            Schema::table('ojt_requirement_templates', function (Blueprint $table) {
                if (! Schema::hasColumn('ojt_requirement_templates', 'is_system')) {
                    $table->boolean('is_system')->default(false)->after('is_active');
                }
                if (! Schema::hasColumn('ojt_requirement_templates', 'system_code')) {
                    $table->string('system_code', 60)->nullable()->after('is_system');
                }
            });
            try {
                Schema::table('ojt_requirement_templates', function (Blueprint $table) {
                    $table->unique('system_code', 'ojt_requirement_templates_system_code_unique');
                });
            } catch (\Throwable) {
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'login_username')) {
                try {
                    $table->dropUnique('users_login_username_unique');
                } catch (\Throwable) {
                }
                $table->dropColumn('login_username');
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'organization_type')) {
                $table->dropColumn('organization_type');
            }
        });

        Schema::table('hte_requests', function (Blueprint $table) {
            if (Schema::hasColumn('hte_requests', 'organization_type')) {
                $table->dropColumn('organization_type');
            }
        });

        Schema::table('attendance_logs', function (Blueprint $table) {
            foreach (['break_start', 'break_end', 'on_break'] as $col) {
                if (Schema::hasColumn('attendance_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('attendance_correction_requests', function (Blueprint $table) {
            foreach ([
                'correction_type',
                'original_break_start',
                'original_break_end',
                'requested_break_start',
                'requested_break_end',
            ] as $col) {
                if (Schema::hasColumn('attendance_correction_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasTable('ojt_requirement_templates')) {
            Schema::table('ojt_requirement_templates', function (Blueprint $table) {
                if (Schema::hasColumn('ojt_requirement_templates', 'system_code')) {
                    try {
                        $table->dropUnique('ojt_requirement_templates_system_code_unique');
                    } catch (\Throwable) {
                    }
                    $table->dropColumn('system_code');
                }
                if (Schema::hasColumn('ojt_requirement_templates', 'is_system')) {
                    $table->dropColumn('is_system');
                }
            });
        }
    }
};
