<?php

namespace App\Console\Commands;

use App\Services\CcsCompanyCleanupService;
use Illuminate\Console\Command;

class CleanupCcsCompanies extends Command
{
    protected $signature = 'interntrack:cleanup-ccs-companies
        {--dry-run : Report what would change, then roll everything back}
        {--json : Emit the report as JSON}';

    protected $description = 'Merge duplicate CCS companies (e.g. "Accenture PH") into their canonical record and remove retired demo companies. Idempotent.';

    public function handle(CcsCompanyCleanupService $service): int
    {
        $report = $service->run((bool) $this->option('dry-run'));
        $slots = $service->slotReport();

        if ($this->option('json')) {
            $this->line(json_encode(['report' => $report, 'slots' => $slots], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info($report['dry_run'] ? 'DRY RUN — nothing was changed.' : 'Company cleanup applied.');

            $this->table(
                ['Record ID', 'Company Name', 'Canonical?', 'Referenced By', 'Action', 'Final Company ID'],
                array_map(fn ($c) => [
                    $c['id'],
                    $c['name'],
                    $c['canonical'] ? 'Yes' : 'No',
                    $c['references'] ? collect($c['references'])->map(fn ($n, $t) => "$t=$n")->implode(', ') : '—',
                    $c['action'],
                    $c['final_company_id'] ?? '—',
                ], $report['companies'])
            );

            foreach ($report['applications_deleted'] as $a) {
                $this->line("Deleted stale application #{$a['application_id']} (student {$a['student_id']} → {$a['company']}, {$a['status']}).");
            }
            foreach ($report['orphans_deleted'] as $o) {
                $this->line("Deleted orphan {$o['table']} #{$o['id']} (internship {$o['internship_id']} no longer exists).");
            }
            foreach ($report['blocked'] as $b) {
                $this->warn("Blocked: #{$b['id']} {$b['name']} — {$b['reason']}");
            }

            $this->table(
                ['Company', 'Capacity', 'Used Slots', 'Available Slots'],
                array_map(fn ($s) => [$s['company'], $s['capacity'], $s['used'], $s['available']], $slots)
            );
        }

        $orphanRefs = array_filter($report['orphan_company_refs']);
        if ($orphanRefs) {
            $this->error('Orphan company references remain: '.json_encode($orphanRefs));

            return self::FAILURE;
        }

        return $report['blocked'] ? self::FAILURE : self::SUCCESS;
    }
}
