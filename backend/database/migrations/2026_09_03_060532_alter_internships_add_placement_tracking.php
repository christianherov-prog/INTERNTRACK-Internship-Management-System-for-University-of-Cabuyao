<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('internships', 'current_placement_id')) {
            return;
        }

        Schema::table('internships', function (Blueprint $table) {
            $table->unsignedBigInteger('current_placement_id')->nullable()->after('supervisor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('internships', function (Blueprint $table) {
            // FK constraint is dropped in add_internship_placements_foreign_keys migration
            $table->dropColumn('current_placement_id');
        });
    }
};
