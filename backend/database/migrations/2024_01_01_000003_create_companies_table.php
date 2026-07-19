<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('address')->nullable();
            $table->string('industry')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_number')->nullable();
            $table->enum('moa_status', ['active', 'pending', 'expired', 'for_renewal'])
                  ->default('pending');
            $table->date('moa_start_date')->nullable();
            $table->date('moa_expiry_date')->nullable();
            $table->string('moa_file_path')->nullable();
            $table->integer('slots_available')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('moa_status');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
