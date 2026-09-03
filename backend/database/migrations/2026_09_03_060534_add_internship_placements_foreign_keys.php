<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add non-cyclic indexes/FKs after placement tables exist.
     */
    public function up(): void
    {
        if (Schema::hasColumn('internships', 'current_placement_id')) {
            try {
                Schema::table('internships', function (Blueprint $table) {
                    $table->index('current_placement_id');
                });
            } catch (\Throwable) {
                // Index already exists.
            }
        }

        if (Schema::hasTable('internship_placements') && Schema::hasColumn('attendance_logs', 'placement_id')) {
            try {
                Schema::table('attendance_logs', function (Blueprint $table) {
                    $table->foreign('placement_id')->references('id')->on('internship_placements')->nullOnDelete();
                });
            } catch (\Throwable) {
                // FK already exists or engine does not allow it.
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendance_logs', 'placement_id')) {
            try {
                Schema::table('attendance_logs', function (Blueprint $table) {
                    $table->dropForeign(['placement_id']);
                });
            } catch (\Throwable) {
            }
        }

        if (Schema::hasColumn('internships', 'current_placement_id')) {
            try {
                Schema::table('internships', function (Blueprint $table) {
                    $table->dropIndex(['current_placement_id']);
                });
            } catch (\Throwable) {
            }
        }
    }
};
