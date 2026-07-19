<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE documents MODIFY status VARCHAR(40) NOT NULL DEFAULT 'pending_review'");
        }

        if (!Schema::hasColumn('documents', 'current_stage')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->string('current_stage', 30)->default('coordinator');
            });
        }

        // Existing pending docs sit at coordinator stage.
        DB::table('documents')
            ->whereIn('status', ['pending_review', 'resubmitted', 'under_review'])
            ->update(['current_stage' => 'coordinator']);
        DB::table('documents')
            ->where('status', 'approved')
            ->update(['current_stage' => 'done']);
        DB::table('documents')
            ->where('status', 'rejected')
            ->update(['current_stage' => 'done']);

        if (!Schema::hasTable('document_reviews')) {
            Schema::create('document_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
                $table->string('stage', 30);
                $table->string('action', 30);
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40);
                $table->text('remarks')->nullable();
                $table->foreignId('reviewed_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->index(['document_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_reviews');

        if (Schema::hasColumn('documents', 'current_stage')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn('current_stage');
            });
        }
    }
};
