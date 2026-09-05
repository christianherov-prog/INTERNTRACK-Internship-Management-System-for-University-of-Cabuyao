<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supervisor_invite_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('supervisor_invite_tokens', 'fo29_file_path')) {
                $table->string('fo29_file_path')->nullable()->after('company_id');
            }
            if (! Schema::hasColumn('supervisor_invite_tokens', 'acceptance_form_paths')) {
                $table->json('acceptance_form_paths')->nullable()->after('fo29_file_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('supervisor_invite_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('supervisor_invite_tokens', 'acceptance_form_paths')) {
                $table->dropColumn('acceptance_form_paths');
            }
        });
    }
};
