<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('internship_placements')) {
            return;
        }

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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internship_placements');
    }
};
