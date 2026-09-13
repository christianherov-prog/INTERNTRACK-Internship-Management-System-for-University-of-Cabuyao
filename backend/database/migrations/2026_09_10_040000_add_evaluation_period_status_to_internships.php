<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('internships') || Schema::hasColumn('internships', 'evaluation_period_status')) {
            return;
        }

        Schema::table('internships', function (Blueprint $table) {
            $table->string('evaluation_period_status', 20)->default('pending')->after('status_reason');
            $table->foreignId('evaluation_period_approved_by')->nullable()->after('evaluation_period_status')->constrained('users')->nullOnDelete();
            $table->timestamp('evaluation_period_approved_at')->nullable()->after('evaluation_period_approved_by');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('internships') || ! Schema::hasColumn('internships', 'evaluation_period_status')) {
            return;
        }

        Schema::table('internships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evaluation_period_approved_by');
            $table->dropColumn(['evaluation_period_status', 'evaluation_period_approved_at']);
        });
    }
};
