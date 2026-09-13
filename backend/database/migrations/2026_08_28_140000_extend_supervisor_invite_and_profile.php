<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE supervisor_invite_tokens MODIFY COLUMN status ENUM('pending','registered','approved','rejected','expired','pending_accept','declined') NOT NULL DEFAULT 'pending'");

        Schema::table('supervisor_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('supervisor_profiles', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('position')->constrained('companies')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('supervisor_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('supervisor_profiles', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });

        DB::statement("ALTER TABLE supervisor_invite_tokens MODIFY COLUMN status ENUM('pending','registered','approved','rejected','expired') NOT NULL DEFAULT 'pending'");
    }
};
