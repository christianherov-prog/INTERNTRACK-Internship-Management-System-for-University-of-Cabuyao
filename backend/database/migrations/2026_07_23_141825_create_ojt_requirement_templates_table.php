<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ojt_requirement_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // e.g. "MOA", "Endorsement Letter"
            $table->text('description')->nullable();
            $table->string('category')->default('general'); // e.g. pre-ojt, during, post-ojt
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed with official requirements
        DB::table('ojt_requirement_templates')->insert([
            ['name' => 'Curriculum Vitae (PNC:AA-FO-27)', 'category' => 'pre-ojt', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Medical Clearance', 'category' => 'pre-ojt', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Psychological Assessment Certificate', 'category' => 'pre-ojt', 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Notarized Student Internship Consent Form (PNC:AA-FO-28)', 'category' => 'pre-ojt', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Student Internship Acceptance Form (PNC:AA-FO-29)', 'category' => 'pre-ojt', 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Application Letter', 'category' => 'pre-ojt', 'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Recommendation Letter', 'category' => 'pre-ojt', 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'MOA / LOA / TOR', 'category' => 'pre-ojt', 'sort_order' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Company Profile', 'category' => 'pre-ojt', 'sort_order' => 9, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Training Plan', 'category' => 'during', 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Midterm Evaluation', 'category' => 'during', 'sort_order' => 11, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Final Report', 'category' => 'post-ojt', 'sort_order' => 12, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Certificate of Completion', 'category' => 'post-ojt', 'sort_order' => 13, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ojt_requirement_templates');
    }
};

