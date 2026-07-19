<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Removes unused in-app ISO survey tables (feature withdrawn). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('system_evaluation_responses');
        Schema::dropIfExists('system_evaluation_items');
        Schema::dropIfExists('system_evaluation_instruments');
    }

    public function down(): void
    {
        // Intentionally empty — feature was removed from the product.
    }
};
