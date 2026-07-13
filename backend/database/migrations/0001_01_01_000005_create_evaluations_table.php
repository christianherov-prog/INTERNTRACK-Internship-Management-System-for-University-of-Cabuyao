<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('evaluation_type');
            $table->string('evaluator_name')->nullable();
            $table->string('evaluator_role')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('max_score', 5, 2)->default(100);
            $table->decimal('work_quality', 5, 2)->nullable();
            $table->decimal('punctuality', 5, 2)->nullable();
            $table->decimal('communication', 5, 2)->nullable();
            $table->decimal('initiative', 5, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', ['pending', 'received'])->default('pending');
            $table->date('evaluated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
