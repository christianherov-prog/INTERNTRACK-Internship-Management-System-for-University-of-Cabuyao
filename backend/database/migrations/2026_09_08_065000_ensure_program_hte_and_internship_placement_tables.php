<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mysql schema dump omits HTE tables that 2026_09_03 migrations already
 * marked as run. Recreate them when missing so progress/placement code can run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('program_hte_requirements')) {
            Schema::create('program_hte_requirements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('program_id');
                $table->integer('sequence_order')->default(1);
                $table->string('label');
                $table->decimal('required_hours', 8, 2)->default(0.00);
                $table->timestamps();

                $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');
                $table->unique(['program_id', 'sequence_order'], 'unique_program_sequence');
            });
        }

        if (! Schema::hasTable('internship_placements')) {
            Schema::create('internship_placements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('internship_id');
                $table->unsignedBigInteger('program_hte_requirement_id');
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('supervisor_id')->nullable();
                $table->integer('sequence_order');
                $table->string('label');
                $table->decimal('required_hours', 8, 2);
                $table->decimal('accumulated_hours', 8, 2)->default(0.00);
                $table->string('status', 50)->default('pending');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->timestamps();

                $table->foreign('internship_id')->references('id')->on('internships')->onDelete('cascade');
                $table->foreign('program_hte_requirement_id')->references('id')->on('program_hte_requirements');
                $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
                $table->foreign('supervisor_id')->references('id')->on('users')->onDelete('set null');
                $table->unique(['internship_id', 'sequence_order'], 'unique_internship_sequence');
            });
        }

        if (Schema::hasTable('internships') && ! Schema::hasColumn('internships', 'current_placement_id')) {
            Schema::table('internships', function (Blueprint $table) {
                $table->unsignedBigInteger('current_placement_id')->nullable()->after('supervisor_id');
            });
        }
    }

    public function down(): void
    {
        // Keep tables; they are required by later HTE code.
    }
};
