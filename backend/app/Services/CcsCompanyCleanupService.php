<?php

namespace App\Services;

use App\Http\Controllers\Api\InternshipStatusController;
use App\Models\Company;
use App\Models\Internship;
use App\Support\CcsCompanyDirectory;
use App\Support\CompanyNameNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent merge/cleanup of the controlled CCS company directory.
 *
 *  1. Every company whose identity key equals a canonical directory company
 *     (case/whitespace variants and explicit aliases such as "Accenture PH")
 *     is a duplicate of that canonical row. Its dependent records are
 *     repointed to the canonical company id, then the duplicate row is removed.
 *  2. Retired demo companies (CcsCompanyDirectory::OBSOLETE) are removed after
 *     their dependents are cleared. A retired company that still owns a real
 *     internship is left in place and reported, never deleted blindly.
 *  3. Records whose parent no longer exists are cleaned up, and the final
 *     state is verified (no company_id that points at a missing company).
 *
 * References are discovered from the schema (every table with a company_id
 * column), so newly added dependents are covered automatically.
 */
class CcsCompanyCleanupService
{
    /** Application status precedence when two applications collide on (student, company). */
    private const APPLICATION_RANK = ['approved' => 3, 'pending' => 2, 'rejected' => 1, 'withdrawn' => 0];

    /**
     * @return array{
     *   dry_run: bool,
     *   companies: list<array<string, mixed>>,
     *   applications_deleted: list<array<string, mixed>>,
     *   orphans_deleted: list<array<string, mixed>>,
     *   blocked: list<array<string, mixed>>,
     *   orphan_company_refs: array<string, int>
     * }
     */
    public function run(bool $dryRun = false): array
    {
        $report = [
            'dry_run' => $dryRun,
            'companies' => [],
            'applications_deleted' => [],
            'orphans_deleted' => [],
            'blocked' => [],
            'orphan_company_refs' => [],
        ];

        DB::beginTransaction();
        try {
            $touched = $this->mergeDuplicates($report);
            $this->removeObsolete($report);
            $this->recalculateSlots($touched);
            $this->removeOrphans($report);
            $report['orphan_company_refs'] = $this->orphanCompanyReferences();

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $report;
    }

    /**
     * Authoritative slot state for the controlled companies.
     *
     * @return list<array{company_id: int, company: string, capacity: int, used: int, available: int, expected_capacity: int|null}>
     */
    public function slotReport(): array
    {
        $rows = [];
        foreach (CcsCompanyDirectory::names() as $name) {
            $company = Company::where('company_name', $name)->first();
            if (! $company) {
                continue;
            }
            $used = $this->occupiedSlots($company->id);
            $rows[] = [
                'company_id' => $company->id,
                'company' => $company->company_name,
                'capacity' => (int) $company->slots_available + $used,
                'used' => $used,
                'available' => (int) $company->slots_available,
                'expected_capacity' => CcsCompanyDirectory::baselineSlots($name),
            ];
        }

        return $rows;
    }

    // ── Step 1: duplicates → canonical ─────────────────────────────────

    /** @return list<int> canonical company ids that received merged records */
    private function mergeDuplicates(array &$report): array
    {
        $touched = [];

        foreach (CcsCompanyDirectory::names() as $canonicalName) {
            $key = CompanyNameNormalizer::key($canonicalName);
            $group = Company::query()->orderBy('id')->get()
                ->filter(fn (Company $c) => CompanyNameNormalizer::key($c->company_name) === $key)
                ->values();

            if ($group->isEmpty()) {
                continue;
            }

            // Canonical: the row that already carries the exact canonical name
            // (lowest id if several); otherwise the lowest id, renamed.
            $canonical = $group->first(fn (Company $c) => $c->company_name === $canonicalName) ?? $group->first();
            $renamed = $canonical->company_name !== $canonicalName;
            if ($renamed) {
                $canonical->update(['company_name' => $canonicalName]);
            }

            $duplicates = $group->reject(fn (Company $c) => $c->id === $canonical->id)->values();

            $report['companies'][] = [
                'id' => $canonical->id,
                'name' => $canonicalName,
                'canonical' => true,
                'references' => $this->referenceCounts($canonical->id),
                'action' => $renamed ? 'Kept (renamed to canonical name)' : 'Kept as canonical',
                'final_company_id' => $canonical->id,
            ];

            foreach ($duplicates as $duplicate) {
                $references = $this->referenceCounts($duplicate->id);
                $this->repointReferences($duplicate, $canonical);
                $this->renameDenormalizedNames($canonicalName, $key);
                $duplicate->forceDelete();

                $touched[] = $canonical->id;
                $report['companies'][] = [
                    'id' => $duplicate->id,
                    'name' => $duplicate->company_name,
                    'canonical' => false,
                    'references' => $references,
                    'action' => 'Merged into canonical, duplicate row removed',
                    'final_company_id' => $canonical->id,
                ];
            }
        }

        return array_values(array_unique($touched));
    }

    private function repointReferences(Company $from, Company $to): void
    {
        foreach ($this->companyIdTables() as $table) {
            if ($table === 'internship_applications') {
                $this->mergeApplications($from->id, $to->id);

                continue;
            }
            DB::table($table)->where('company_id', $from->id)->update(['company_id' => $to->id]);
        }
    }

    /**
     * (student_id, company_id) is unique, so a student who applied to both the
     * duplicate and the canonical row keeps ONE application: the highest-ranked
     * status wins and is written onto the canonical row.
     */
    private function mergeApplications(int $fromId, int $toId): void
    {
        $rows = DB::table('internship_applications')->where('company_id', $fromId)->orderBy('id')->get();

        foreach ($rows as $dup) {
            $keep = DB::table('internship_applications')
                ->where('student_id', $dup->student_id)
                ->where('company_id', $toId)
                ->first();

            if (! $keep) {
                DB::table('internship_applications')->where('id', $dup->id)->update(['company_id' => $toId]);

                continue;
            }

            $updates = [];
            if ((self::APPLICATION_RANK[$dup->status] ?? 0) > (self::APPLICATION_RANK[$keep->status] ?? 0)) {
                $updates['status'] = $dup->status;
                $updates['coordinator_remarks'] = $dup->coordinator_remarks;
            }
            if (blank($keep->moa_path) && filled($dup->moa_path)) {
                $updates['moa_path'] = $dup->moa_path;
                $updates['moa_original_name'] = $dup->moa_original_name;
            }
            if ($updates) {
                DB::table('internship_applications')->where('id', $keep->id)->update($updates);
            }
            DB::table('internship_applications')->where('id', $dup->id)->delete();
        }
    }

    /** Portfolios snapshot the company name as text; keep it consistent with the canonical name. */
    private function renameDenormalizedNames(string $canonicalName, string $key): void
    {
        if (! Schema::hasColumn('student_portfolios', 'company_name')) {
            return;
        }

        DB::table('student_portfolios')->whereNotNull('company_name')->orderBy('id')->each(function ($row) use ($canonicalName, $key) {
            if ($row->company_name !== $canonicalName && CompanyNameNormalizer::key($row->company_name) === $key) {
                DB::table('student_portfolios')->where('id', $row->id)->update(['company_name' => $canonicalName]);
            }
        });
    }

    // ── Step 2: retired demo companies ─────────────────────────────────

    private function removeObsolete(array &$report): void
    {
        foreach (CcsCompanyDirectory::OBSOLETE as $name) {
            $key = CompanyNameNormalizer::key($name);
            $companies = Company::query()->orderBy('id')->get()
                ->filter(fn (Company $c) => CompanyNameNormalizer::key($c->company_name) === $key);

            foreach ($companies as $company) {
                $references = $this->referenceCounts($company->id);

                $internships = Internship::where('company_id', $company->id)->count();
                if ($internships > 0) {
                    $report['blocked'][] = [
                        'id' => $company->id,
                        'name' => $company->company_name,
                        'reason' => "$internships internship record(s) still reference this company; not deleted.",
                    ];
                    $report['companies'][] = [
                        'id' => $company->id,
                        'name' => $company->company_name,
                        'canonical' => false,
                        'references' => $references,
                        'action' => 'BLOCKED — internships still reference it',
                        'final_company_id' => null,
                    ];

                    continue;
                }

                // Stale demo applications (any status) would otherwise keep the
                // retired company "current" for the student.
                $stale = DB::table('internship_applications')->where('company_id', $company->id)->get(['id', 'student_id', 'status']);
                foreach ($stale as $application) {
                    $report['applications_deleted'][] = [
                        'application_id' => $application->id,
                        'student_id' => $application->student_id,
                        'company_id' => $company->id,
                        'company' => $company->company_name,
                        'status' => $application->status,
                    ];
                }
                DB::table('internship_applications')->where('company_id', $company->id)->delete();

                // Everything else keeps its row but loses the link (same effect
                // as the supervisor_profiles ON DELETE SET NULL foreign key).
                foreach ($this->companyIdTables() as $table) {
                    if ($table !== 'internship_applications') {
                        DB::table($table)->where('company_id', $company->id)->update(['company_id' => null]);
                    }
                }

                $company->forceDelete();
                $report['companies'][] = [
                    'id' => $company->id,
                    'name' => $company->company_name,
                    'canonical' => false,
                    'references' => $references,
                    'action' => 'Removed (retired demo company)',
                    'final_company_id' => null,
                ];
            }
        }
    }

    // ── Step 3: slots ──────────────────────────────────────────────────

    /** @param list<int> $companyIds */
    private function recalculateSlots(array $companyIds): void
    {
        foreach ($companyIds as $id) {
            $company = Company::find($id);
            $baseline = $company ? CcsCompanyDirectory::baselineSlots($company->company_name) : null;
            if ($baseline === null) {
                continue;
            }
            $company->update(['slots_available' => max(0, $baseline - $this->occupiedSlots($id))]);
        }
    }

    private function occupiedSlots(int $companyId): int
    {
        return Internship::where('company_id', $companyId)
            ->whereIn('status', InternshipStatusController::OCCUPYING)
            ->count();
    }

    // ── Step 4: orphans ────────────────────────────────────────────────

    private function removeOrphans(array &$report): void
    {
        // Supervisor requests whose internship no longer exists.
        $orphans = DB::table('supervisor_invite_tokens')
            ->whereNotNull('internship_id')
            ->whereNotIn('internship_id', DB::table('internships')->select('id'))
            ->get(['id', 'student_id', 'company_id', 'internship_id', 'status']);

        foreach ($orphans as $token) {
            $report['orphans_deleted'][] = [
                'table' => 'supervisor_invite_tokens',
                'id' => $token->id,
                'student_id' => $token->student_id,
                'company_id' => $token->company_id,
                'internship_id' => $token->internship_id,
                'status' => $token->status,
            ];
            DB::table('supervisor_invite_tokens')->where('id', $token->id)->delete();
        }
    }

    /** @return array<string, int> table => rows whose company_id points at a missing company */
    public function orphanCompanyReferences(): array
    {
        $result = [];
        foreach ($this->companyIdTables() as $table) {
            $result[$table] = DB::table($table)
                ->whereNotNull('company_id')
                ->whereNotIn('company_id', DB::table('companies')->select('id'))
                ->count();
        }

        return $result;
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /** @return list<string> every table (other than companies) that has a company_id column */
    private function companyIdTables(): array
    {
        $tables = [];
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            if ($name !== 'companies' && Schema::hasColumn($name, 'company_id')) {
                $tables[] = $name;
            }
        }
        sort($tables);

        return $tables;
    }

    /** @return array<string, int> */
    private function referenceCounts(int $companyId): array
    {
        $counts = [];
        foreach ($this->companyIdTables() as $table) {
            $n = DB::table($table)->where('company_id', $companyId)->count();
            if ($n > 0) {
                $counts[$table] = $n;
            }
        }

        return $counts;
    }
}
