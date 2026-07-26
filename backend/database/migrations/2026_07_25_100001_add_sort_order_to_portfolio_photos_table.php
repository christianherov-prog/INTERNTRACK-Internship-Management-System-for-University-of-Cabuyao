<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('portfolio_photos', function (Blueprint $table) {
            $table->date('date_taken')->nullable()->after('week_number');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('date_taken');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_photos', function (Blueprint $table) {
            $table->dropColumn(['date_taken', 'sort_order']);
        });
    }
};
