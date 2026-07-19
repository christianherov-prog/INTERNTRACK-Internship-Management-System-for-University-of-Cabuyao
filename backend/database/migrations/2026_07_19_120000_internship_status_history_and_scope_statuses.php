<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('internships', 'status_reason')) {
            Schema::table('internships', function (Blueprint $table) {
                $table->text('status_reason')->nullable();
            });
        }

        // Widen status beyond original enum (MySQL). SQLite stores enums as TEXT already.
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE internships MODIFY status VARCHAR(40) NOT NULL DEFAULT 'pending_placement'");
        }

        DB::table('internships')->where('status', 'ongoing')->update(['status' => 'active']);

        if (!Schema::hasTable('internship_status_histories')) {
            Schema::create('internship_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('internship_id')->constrained('internships')->cascadeOnDelete();
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40);
                $table->text('reason');
                $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->index(['internship_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('internship_status_histories');

        if (Schema::hasColumn('internships', 'status_reason')) {
            Schema::table('internships', function (Blueprint $table) {
                $table->dropColumn('status_reason');
            });
        }

        DB::table('internships')->where('status', 'active')->update(['status' => 'ongoing']);
    }
};
