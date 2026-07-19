<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained('internships')->cascadeOnDelete();
            
            // Chapter I: Host Company Profile
            $table->text('company_background')->nullable();
            $table->text('company_vision')->nullable();
            $table->text('company_mission')->nullable();
            $table->string('org_chart_path')->nullable();
            
            // Chapter III: Assessment of the Program
            $table->text('prof_ethical_responsibilities')->nullable();
            $table->text('things_learned')->nullable();
            $table->text('experience_with_people')->nullable();
            $table->text('industry_best_practices')->nullable();
            $table->text('recommendations')->nullable();
            $table->text('advice')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolios');
    }
};
