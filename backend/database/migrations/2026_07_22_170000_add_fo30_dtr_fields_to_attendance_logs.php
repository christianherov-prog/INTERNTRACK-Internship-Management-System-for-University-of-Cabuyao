<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PNC:AA-FO-30 digital DTR fields — AM/PM pairs + Prepared-by / Verified-by e-sign.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->time('am_time_in')->nullable()->after('clock_out');
            $table->time('am_time_out')->nullable()->after('am_time_in');
            $table->time('pm_time_in')->nullable()->after('am_time_out');
            $table->time('pm_time_out')->nullable()->after('pm_time_in');

            $table->string('student_signature_path')->nullable()->after('clock_out_location');
            $table->string('student_signed_name')->nullable()->after('student_signature_path');
            $table->timestamp('student_signed_at')->nullable()->after('student_signed_name');
            $table->timestamp('student_privacy_accepted_at')->nullable()->after('student_signed_at');

            $table->string('hte_signature_path')->nullable()->after('student_privacy_accepted_at');
            $table->string('hte_signed_name')->nullable()->after('hte_signature_path');
            $table->timestamp('hte_signed_at')->nullable()->after('hte_signed_name');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn([
                'am_time_in', 'am_time_out', 'pm_time_in', 'pm_time_out',
                'student_signature_path', 'student_signed_name', 'student_signed_at',
                'student_privacy_accepted_at',
                'hte_signature_path', 'hte_signed_name', 'hte_signed_at',
            ]);
        });
    }
};
