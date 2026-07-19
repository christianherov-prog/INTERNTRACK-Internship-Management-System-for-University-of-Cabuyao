<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Student profiles (synchronized from iEnroll / MISD)
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('student_number')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->date('birthday')->nullable();
            $table->enum('sex', ['Male', 'Female', 'Other'])->nullable();
            $table->string('program')->nullable()->comment('e.g. BS Information Technology');
            $table->string('college')->nullable();
            $table->string('department')->nullable();
            $table->string('course_name')->nullable();
            $table->tinyInteger('year_level')->nullable();
            $table->string('section')->nullable()->comment('e.g. 4-D');
            $table->string('academic_year')->nullable()->comment('e.g. 2024-2025');
            $table->tinyInteger('semester')->nullable()->comment('1 or 2');
            $table->string('enrollment_status')->nullable()->comment('e.g. Enrolled, Irregular');
            $table->timestamp('synced_at')->nullable()->comment('Last sync from iEnroll');
            $table->timestamps();
        });

        // Faculty profiles (synchronized from iEnroll / MISD)
        Schema::create('faculty_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('employee_number')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('department')->nullable();
            $table->string('college')->nullable();
            $table->string('position')->nullable();
            $table->string('employment_status')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // Supervisor profiles (industry-side, not from iEnroll)
        Schema::create('supervisor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('position')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_profiles');
        Schema::dropIfExists('faculty_profiles');
        Schema::dropIfExists('student_profiles');
    }
};
