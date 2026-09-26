<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow meaningful custom organization types (up to 100 chars).
 * Do not invent types for legacy "other" rows — Directors must specify.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'organization_type')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('organization_type', 100)->nullable()->change();
            });
        }

        if (Schema::hasTable('hte_requests') && Schema::hasColumn('hte_requests', 'organization_type')) {
            Schema::table('hte_requests', function (Blueprint $table) {
                $table->string('organization_type', 100)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'organization_type')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('organization_type', 50)->nullable()->change();
            });
        }

        if (Schema::hasTable('hte_requests') && Schema::hasColumn('hte_requests', 'organization_type')) {
            Schema::table('hte_requests', function (Blueprint $table) {
                $table->string('organization_type', 50)->nullable()->change();
            });
        }
    }
};
