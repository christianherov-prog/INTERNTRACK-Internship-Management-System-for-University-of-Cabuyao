<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\Internship;
use App\Services\ControlledAttendanceWriter;
use App\Support\InternshipStatuses;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time, idempotent data repair for the controlled CCS dataset
 * (interntrack:seed-ccs-demo). It does not change production logic.
 *
 *  1. Removes attendance dated after a completed internship's end date —
 *     entries recorded after completion (e.g. before completed internships
 *     were locked against new attendance).
 *  2. Ensures the approved Asia/Manila 08:00–17:00 schedule exists.
 *  3. Rewrites rows from the earlier generator, which stored Manila
 *     wall-clock times as app-timezone times (FO-30 showed 4:00 PM / 1:00 AM),
 *     into correctly stored times with a recorded lunch break.
 *
 * Validated hour totals must be unchanged afterwards; otherwise the whole
 * repair rolls back.
 */
class RepairControlledAttendance extends Command
{
    protected $signature = 'interntrack:repair-controlled-attendance
                            {--dry-run : Report what would change without writing}
                            {--student=* : Limit to these student numbers (default: the controlled CCS roster)}';

    protected $description = 'Repair controlled-dataset attendance times (Asia/Manila storage) and remove post-completion entries.';

    /** Placed students of the controlled CCS dataset (finished + ongoing). */
    public const CONTROLLED_STUDENTS = [
        '2300592', '2300590', '2300595', '2300613', // finished
        '2300600', '2300609', '2300610', '2300611', // ongoing
    ];

    public function handle(ControlledAttendanceWriter $writer): int
    {
        $students = $this->option('student') ?: self::CONTROLLED_STUDENTS;
        $dryRun = (bool) $this->option('dry-run');

        $internships = Internship::query()
            ->with('student')
            ->whereHas('student', fn ($q) => $q->whereIn('student_number', $students))
            ->whereNotNull('supervisor_id')
            ->whereIn('status', ['completed', 'active', 'ongoing'])
            ->orderBy('id')
            ->get();

        $rows = [];
        $failed = false;

        DB::beginTransaction();
        try {
            foreach ($internships as $internship) {
                $before = $this->validatedHours($internship);

                $removed = $this->postCompletionRows($internship);
                $removedIds = $removed->pluck('id')->all();
                $removed->each(fn (AttendanceLog $log) => $log->forceDelete());

                $firstDay = $internship->start_date?->toDateString()
                    ?? Carbon::parse($internship->attendance()->min('date'))->toDateString();
                $writer->ensureStandardSchedule($internship, (int) $internship->supervisor_id, $firstDay);
                $repaired = $writer->repairLegacyRows($internship, (int) $internship->supervisor_id);

                $internship->refresh()->refreshTotalHours();
                $after = $this->validatedHours($internship);

                if (abs($before - $after) > 0.001) {
                    $failed = true;
                }

                $rows[] = [
                    $internship->student?->student_number,
                    InternshipStatuses::normalize($internship->status),
                    number_format($before, 2),
                    number_format($after, 2),
                    $repaired,
                    $removedIds ? implode(',', $removedIds) : '—',
                ];
            }

            if ($failed || $dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->table(['Student', 'Status', 'Validated h (before)', 'Validated h (after)', 'Rows retimed', 'Removed post-completion IDs'], $rows);

        if ($failed) {
            $this->error('Validated totals would change — nothing was written.');

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry run: no changes written.' : 'Controlled attendance repaired.');

        return self::SUCCESS;
    }

    private function validatedHours(Internship $internship): float
    {
        return round((float) AttendanceLog::query()
            ->where('internship_id', $internship->id)
            ->where('status', 'validated')
            ->sum('hours_rendered'), 2);
    }

    /** Entries dated after a completed internship's official end date. */
    private function postCompletionRows(Internship $internship)
    {
        if (! $internship->isCompleted() || ! $internship->end_date) {
            return collect();
        }

        return AttendanceLog::withTrashed()
            ->where('internship_id', $internship->id)
            ->whereDate('date', '>', $internship->end_date->toDateString())
            ->get();
    }
}
