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
        Schema::table('documents', function (Blueprint $table) {
            $table->string('drive_link')->nullable()->after('week_number');
            $table->string('file_path')->nullable()->change();
            $table->string('file_name')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('drive_link');
            // Reverting change to nullable is tricky as it might cause data loss if there are nulls.
            // Leaving file_path and file_name as nullable in the down method to avoid rollback crash, 
            // or making them non-nullable which could crash if there are records.
            // Safe approach is to not revert the nullable change.
        });
    }
};
