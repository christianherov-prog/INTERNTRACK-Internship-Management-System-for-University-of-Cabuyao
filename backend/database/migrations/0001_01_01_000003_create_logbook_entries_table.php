<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logbook_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->decimal('hours_rendered', 5, 2)->default(0);
            $table->text('tasks_completed');
            $table->text('learning_reflection')->nullable();
            $table->enum('status', ['submitted', 'reviewed'])->default('submitted');
            $table->string('reviewed_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logbook_entries');
    }
};
