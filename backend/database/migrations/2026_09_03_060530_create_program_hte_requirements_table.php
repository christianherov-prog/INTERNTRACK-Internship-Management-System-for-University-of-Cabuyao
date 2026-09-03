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
        if (Schema::hasTable('program_hte_requirements')) {
            return;
        }

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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_hte_requirements');
    }
};
