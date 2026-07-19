<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->date('date')->nullable()->change();
            $table->text('activities_summary')->nullable()->change();
            
            $table->unsignedInteger('week_number')->nullable()->after('internship_id');
            $table->string('file_path')->nullable()->after('status');
            $table->text('notes')->nullable()->after('file_path');
        });

        Schema::table('portfolio_photos', function (Blueprint $table) {
            $table->string('type')->default('ojt_photo')->after('portfolio_id');
            $table->unsignedInteger('week_number')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['week_number', 'file_path', 'notes']);
        });

        Schema::table('portfolio_photos', function (Blueprint $table) {
            $table->dropColumn(['type', 'week_number']);
        });
    }
};
