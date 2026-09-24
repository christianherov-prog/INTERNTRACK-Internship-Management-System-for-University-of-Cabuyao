<?php

namespace App\Services;

use App\Models\Internship;
use App\Models\JournalDeadline;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\ManilaTime;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Weekly Journal deadlines set by the internship's assigned Faculty.
 *
 * Week identities come from JournalPeriodValidator::weekWindows() (the same rule
 * that numbers Student journals, FO-31, and the Journal Review Queue), so a
 * deadline can only target a real week of that student's own internship.
 *
 * Policy: no institutional rule blocks late journals, so a submission after the
 * deadline is still accepted but recorded as late (submitted_late) with its real
 * submission timestamp. Deadlines are entered and displayed in Asia/Manila and
 * stored in UTC. One row per internship + week (unique index).
 */
class JournalDeadlineService
{
    public function __construct(private JournalPeriodValidator $periods) {}

    public function deadlineFor(Internship|int $internship, int $weekNumber): ?JournalDeadline
    {
        $internshipId = $internship instanceof Internship ? $internship->id : $internship;

        return JournalDeadline::where('internship_id', $internshipId)
            ->where('week_number', $weekNumber)
            ->first();
    }

    /** Parse a Manila wall-clock value such as "2026-09-30T23:59" into UTC. */
    public static function parseManila(string $value): CarbonInterface
    {
        return Carbon::parse($value, ManilaTime::TZ)->utc();
    }

    public static function dateRangeLabel(?string $start, ?string $end): string
    {
        if (! $start) {
            return '';
        }
        $s = Carbon::parse($start, ManilaTime::TZ);
        $e = $end ? Carbon::parse($end, ManilaTime::TZ) : null;
        if (! $e || $s->equalTo($e)) {
            return $s->format('M j, Y');
        }

        return $s->year === $e->year
            ? $s->format('M j').' – '.$e->format('M j, Y')
            : $s->format('M j, Y').' – '.$e->format('M j, Y');
    }

    public static function present(JournalDeadline $deadline, ?array $window = null): array
    {
        $manila = $deadline->due_at->copy()->timezone(ManilaTime::TZ);

        return [
            'id' => $deadline->id,
            'internship_id' => $deadline->internship_id,
            'week_number' => $deadline->week_number,
            'week_start' => $window['start_date'] ?? null,
            'week_end' => $window['end_date'] ?? null,
            'week_range_display' => $window ? self::dateRangeLabel($window['start_date'], $window['end_date']) : null,
            'due_at' => $manila->toIso8601String(),
            'due_at_local' => $manila->format('Y-m-d\TH:i'),
            'due_at_display' => $manila->format('F j, Y, g:i A'),
            'is_past' => $deadline->due_at->isPast(),
            'set_by' => $deadline->set_by,
            'timezone' => ManilaTime::TZ,
        ];
    }

    /**
     * Every authoritative week of the internship with its journal (if any) and
     * deadline (if any). Used by the Faculty deadline tool and the Student view.
     *
     * @return list<array>
     */
    public function weeksFor(Internship $internship): array
    {
        $journals = JournalEntry::where('internship_id', $internship->id)
            ->academic()
            ->get()
            ->keyBy(fn (JournalEntry $j) => (int) $j->week_number);
        $deadlines = JournalDeadline::where('internship_id', $internship->id)
            ->get()
            ->keyBy(fn (JournalDeadline $d) => (int) $d->week_number);

        return collect($this->periods->weekWindows($internship))
            ->map(function (array $window) use ($journals, $deadlines) {
                $week = $window['week_number'];
                $journal = $journals->get($week);
                $deadline = $deadlines->get($week);

                return $window + [
                    'label' => "Week {$week} — ".self::dateRangeLabel($window['start_date'], $window['end_date']),
                    'range_display' => self::dateRangeLabel($window['start_date'], $window['end_date']),
                    'journal' => $journal ? self::presentJournal($journal) : null,
                    'deadline' => $deadline ? self::present($deadline, $window) : null,
                ];
            })
            ->values()
            ->all();
    }

    public static function presentJournal(JournalEntry $journal): array
    {
        $submitted = $journal->submitted_at?->copy()->timezone(ManilaTime::TZ);

        return [
            'id' => $journal->id,
            'status' => $journal->status,
            'date' => $journal->date?->toDateString(),
            'end_date' => $journal->end_date?->toDateString(),
            'range_display' => self::dateRangeLabel($journal->date?->toDateString(), $journal->end_date?->toDateString()),
            'submitted_at' => $submitted?->toIso8601String(),
            'submitted_at_display' => $submitted?->format('F j, Y, g:i A'),
            'submitted_late' => (bool) $journal->submitted_late,
        ];
    }

    /** Student-facing deadline list (only weeks that have a deadline). */
    public function listFor(Internship $internship): Collection
    {
        return collect($this->weeksFor($internship))
            ->filter(fn (array $week) => $week['deadline'] !== null)
            ->map(fn (array $week) => $week['deadline'] + [
                'journal_status' => $week['journal']['status'] ?? null,
                'submitted_late' => $week['journal']['submitted_late'] ?? null,
                'submitted_at_display' => $week['journal']['submitted_at_display'] ?? null,
            ])
            ->values();
    }

    /**
     * Save deadlines row by row. Each row must reference an internship already
     * authorized by the caller and a week that exists for THAT internship.
     *
     * @param  Collection<int, Internship>  $internships  keyed by id
     * @param  list<array{internship_id: int, week_number: int, due_at: CarbonInterface}>  $rows
     * @return Collection<int, array>
     */
    public function saveRows(Collection $internships, array $rows, User $faculty): Collection
    {
        foreach ($rows as $index => $row) {
            $internship = $internships->get($row['internship_id']);
            if (! $this->periods->weekWindow($internship, $row['week_number'])) {
                throw ValidationException::withMessages([
                    "deadlines.{$index}.week_number" => [
                        "Week {$row['week_number']} is not a valid journal week for this student's internship.",
                    ],
                ]);
            }
        }

        return DB::transaction(function () use ($internships, $rows, $faculty) {
            return collect($rows)->map(function (array $row) use ($internships, $faculty) {
                $internship = $internships->get($row['internship_id']);
                $deadline = JournalDeadline::updateOrCreate(
                    ['internship_id' => $internship->id, 'week_number' => $row['week_number']],
                    ['due_at' => $row['due_at'], 'set_by' => $faculty->id]
                );
                $this->reevaluate($internship->id, $row['week_number'], $deadline->due_at);

                return self::present($deadline->fresh(), $this->periods->weekWindow($internship, $row['week_number']));
            })->values();
        });
    }

    public function remove(JournalDeadline $deadline): void
    {
        DB::transaction(function () use ($deadline) {
            $this->reevaluate($deadline->internship_id, $deadline->week_number, null);
            $deadline->delete();
        });
    }

    /**
     * Stamp a journal at submission time. The first submission timestamp is
     * preserved across revisions so resubmitting a returned journal does not
     * turn an on-time submission into a late one.
     */
    public function stampSubmission(JournalEntry $journal, CarbonInterface $now): array
    {
        $submittedAt = $journal->submitted_at ?? $now;
        $deadline = $this->deadlineFor($journal->internship_id, (int) $journal->week_number);

        return [
            'submitted_at' => $submittedAt,
            'deadline_at' => $deadline?->due_at,
            'submitted_late' => $deadline !== null && $submittedAt->greaterThan($deadline->due_at),
        ];
    }

    private function reevaluate(int $internshipId, int $weekNumber, ?CarbonInterface $dueAt): void
    {
        JournalEntry::where('internship_id', $internshipId)
            ->where('week_number', $weekNumber)
            ->get()
            ->each(function (JournalEntry $journal) use ($dueAt) {
                $journal->forceFill([
                    'deadline_at' => $dueAt,
                    'submitted_late' => $dueAt !== null
                        && $journal->submitted_at !== null
                        && $journal->submitted_at->greaterThan($dueAt),
                ])->saveQuietly();
            });
    }
}
