<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 2 of persistence-level duplicate protection for companies/HTEs.
 *
 * active_name_key is a STORED generated column equal to normalized_name while
 * the row is not soft-deleted and NULL otherwise. A UNIQUE index on it forbids
 * two live companies with the same identity key, yet a soft-deleted company's
 * name can be reused (NULLs never collide in a unique index).
 *
 * Existing duplicates must be merged first with
 * `php artisan interntrack:cleanup-ccs-companies`; this migration refuses to
 * guess which duplicate row is authoritative.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('companies')
            ->whereNull('deleted_at')
            ->select('normalized_name', DB::raw('COUNT(*) AS n'), DB::raw('GROUP_CONCAT(id) AS ids'))
            ->groupBy('normalized_name')
            ->having('n', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $detail = $duplicates->map(fn ($d) => "\"{$d->normalized_name}\" (ids {$d->ids})")->implode('; ');

            throw new RuntimeException(
                'Duplicate companies exist: '.$detail.'. Run `php artisan interntrack:cleanup-ccs-companies` '
                .'(or merge them manually), then run `php artisan migrate` again.'
            );
        }

        if (! Schema::hasColumn('companies', 'active_name_key')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('active_name_key')->nullable()
                    ->storedAs('IF(deleted_at IS NULL, normalized_name, NULL)')
                    ->after('normalized_name');
            });
            Schema::table('companies', function (Blueprint $table) {
                $table->unique('active_name_key', 'companies_active_name_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('companies', 'active_name_key')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropUnique('companies_active_name_key_unique');
            });
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('active_name_key');
            });
        }
    }
};
