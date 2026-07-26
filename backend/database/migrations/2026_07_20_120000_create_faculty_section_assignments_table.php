<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('faculty_section_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('program')->nullable()->comment('e.g. BS Information Technology');
            $table->string('section')->comment('UC section code e.g. 4ITA, 4ITB, 4ITC, 4ITD');
            $table->string('academic_year')->comment('e.g. 2025-2026');
            $table->unsignedTinyInteger('semester')->comment('1 or 2');
            $table->foreignId('faculty_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['program', 'section', 'academic_year', 'semester'], 'fsa_program_section_term_unique');
            $table->index(['section', 'academic_year', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_section_assignments');
    }
};
