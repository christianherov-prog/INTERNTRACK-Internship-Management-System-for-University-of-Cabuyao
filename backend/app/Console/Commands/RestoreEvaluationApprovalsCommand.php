<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Internship;
use App\Support\InternshipStatuses;
use Illuminate\Console\Command;

/**
 * Restore evaluation-period approvals that the audit log records but the
 * internships table no longer holds (lost outside the app, e.g. by database
 * repair or restore work).
 *
 * Conservative: an approval is restored only when the latest logged approval
 * for the internship came from the Faculty still assigned to it, no deliberate
 * reset was logged afterwards, the internship is not closed, and it has not
 * been sent back to pending placement. Everything else is reported, not changed.
 */
class RestoreEvaluationApprovalsCommand extends Command
{
    protected $signature = 'interntrack:restore-evaluation-approvals {--dry-run : Report only; change nothing}';

    protected $description = 'Restore evaluation-period approvals recorded in the audit log but missing from internships';

    private const CLOSED = ['cancelled', 'terminated', 'withdrawn', 'failed'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $latestApproval = [];
        $latestReset = [];
        AuditLog::query()
            ->whereIn('action', ['approve_evaluation_period', 'evaluation_period_reset'])
            ->orderBy('id')
            ->get()
            ->each(function (AuditLog $log) use (&$latestApproval, &$latestReset) {
                $internshipId = (int) ($log->new_values['internship_id'] ?? 0);
                if ($internshipId <= 0) {
                    return;
                }
                if ($log->action === 'approve_evaluation_period') {
                    $latestApproval[$internshipId] = $log;
                } else {
                    $latestReset[$internshipId] = $log;
                }
            });

        $rows = [];
        $restored = 0;

        foreach ($latestApproval as $internshipId => $log) {
            $internship = Internship::withTrashed()->find($internshipId);
            $status = $internship ? InternshipStatuses::normalize($internship->status) : null;

            $reason = match (true) {
                ! $internship => 'internship no longer exists',
                $internship->trashed() => 'internship is deleted',
                ($internship->evaluation_period_status ?: 'pending') === 'approved' => 'already approved',
                isset($latestReset[$internshipId]) && $latestReset[$internshipId]->id > $log->id => 'deliberately reset after approval',
                in_array($status, self::CLOSED, true) => "internship is {$status}",
                $status === 'pending_placement' => 'internship was sent back to pending placement',
                (int) $internship->faculty_id !== (int) $log->user_id => 'approving faculty is no longer assigned',
                default => null,
            };

            if ($reason === null) {
                if (! $dryRun) {
                    $internship->forceFill([
                        'evaluation_period_status' => 'approved',
                        'evaluation_period_approved_by' => $log->user_id,
                        'evaluation_period_approved_at' => $log->created_at,
                    ])->saveQuietly();
                    audit_log(null, 'evaluation_period_restored', [
                        'internship_id' => $internshipId,
                        'approved_by' => $log->user_id,
                        'approved_at' => $log->created_at?->toIso8601String(),
                        'audit_log_id' => $log->id,
                    ]);
                }
                $restored++;
            }

            $rows[] = [
                $internshipId,
                $internship?->student_id ?? '—',
                $status ?? '—',
                $log->user_id,
                $log->created_at?->toDateTimeString(),
                $reason === null ? ($dryRun ? 'would restore' : 'restored') : "skipped: {$reason}",
            ];
        }

        $this->table(['Internship', 'Student', 'Status', 'Approved by', 'Approved at (UTC)', 'Result'], $rows);
        $this->info(($dryRun ? 'Would restore' : 'Restored')." {$restored} approval(s).");

        return self::SUCCESS;
    }
}
