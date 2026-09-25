<?php

use App\Support\CompanyNameNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 of persistence-level duplicate protection for companies/HTEs:
 * companies.normalized_name = CompanyNameNormalizer::key(company_name)
 * (trimmed, whitespace-collapsed, lowercased, explicit aliases only).
 *
 * This step never fails on existing duplicates, so the merge command
 * (`php artisan interntrack:cleanup-ccs-companies`) can run before the unique
 * index of step 2 (2026_09_25_100100_add_company_active_name_unique) is added.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'normalized_name')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('normalized_name')->nullable()->after('company_name');
            });
        }

        DB::table('companies')->orderBy('id')->each(function ($company) {
            DB::table('companies')->where('id', $company->id)->update([
                'normalized_name' => CompanyNameNormalizer::key($company->company_name),
            ]);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('companies', 'normalized_name')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('normalized_name');
            });
        }
    }
};
