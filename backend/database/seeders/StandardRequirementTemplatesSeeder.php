<?php

namespace Database\Seeders;

use App\Models\OjtRequirementTemplate;
use App\Support\RequiredDocuments;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Idempotently upserts system-wide OJT requirement templates.
 */
class StandardRequirementTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $order = 1;
        foreach (RequiredDocuments::canonicalTypeNames() as $name) {
            $code = Str::slug($name, '_');
            if ($code === '') {
                continue;
            }

            OjtRequirementTemplate::updateOrCreate(
                ['system_code' => $code],
                [
                    'name' => $name,
                    'description' => 'Standard InternTrack requirement: '.$name,
                    'category' => 'general',
                    'sort_order' => $order++,
                    'is_active' => true,
                    'is_system' => true,
                ]
            );
        }

        RequiredDocuments::clearCache();
    }
}
