<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ojt_requirement_templates', function (Blueprint $table) {
            $table->string('template_file_name')->nullable()->after('template_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('ojt_requirement_templates', function (Blueprint $table) {
            $table->dropColumn('template_file_name');
        });
    }
};
