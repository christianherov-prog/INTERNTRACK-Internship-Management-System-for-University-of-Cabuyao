<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internship_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('internship_applications', 'moa_path')) {
                $table->string('moa_path')->nullable()->after('coordinator_remarks');
            }
            if (! Schema::hasColumn('internship_applications', 'moa_original_name')) {
                $table->string('moa_original_name')->nullable()->after('moa_path');
            }
        });

        Schema::table('hte_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('hte_requests', 'moa_path')) {
                $table->string('moa_path')->nullable()->after('remarks');
            }
            if (! Schema::hasColumn('hte_requests', 'moa_original_name')) {
                $table->string('moa_original_name')->nullable()->after('moa_path');
            }
            if (! Schema::hasColumn('hte_requests', 'coordinator_remarks')) {
                $table->text('coordinator_remarks')->nullable()->after('moa_original_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('internship_applications', function (Blueprint $table) {
            foreach (['moa_original_name', 'moa_path'] as $column) {
                if (Schema::hasColumn('internship_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('hte_requests', function (Blueprint $table) {
            foreach (['coordinator_remarks', 'moa_original_name', 'moa_path'] as $column) {
                if (Schema::hasColumn('hte_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
