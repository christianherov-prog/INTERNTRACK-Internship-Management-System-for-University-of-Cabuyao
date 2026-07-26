<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->text('company_profile')->nullable()->after('internship_id');
            $table->string('org_chart_caption')->nullable()->after('org_chart_path');
        });
    }

    public function down(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropColumn(['company_profile', 'org_chart_caption']);
        });
    }
};
