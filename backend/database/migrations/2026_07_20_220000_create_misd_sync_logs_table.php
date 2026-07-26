<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('misd_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 16)->comment('push|pull');
            $table->string('entity_type', 32)->comment('student_assignment|student|faculty');
            $table->string('entity_key')->nullable()->comment('student_number or employee_number');
            $table->string('status', 16)->comment('success|failed');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_key']);
            $table->index(['direction', 'status']);
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('misd_sync_logs');
    }
};
