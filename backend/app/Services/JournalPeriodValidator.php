<?php

namespace App\Services;

use App\Models\Internship;
use App\Models\JournalEntry;
use App\Support\InternshipStatuses;
use App\Support\ManilaTime;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Canonical weekly-journal period rules for any student internship.
 *
 * Authoritative bounds: internships.start_date / end_date|expected_end_date.
 * Week numbers are derived from calendar distance from internship start (7-day blocks),
 * not from creation order or journal count.
 */
class JournalPeriodValidator
{
    public const MSG_NO_ACTIVE = 'An active internship is required before creating a weekly journal.';

    public const MSG_BEFORE_START = 'Journal dates cannot be earlier than your internship start date.';

    public const MSG_AFTER_END = 'Journal dates cannot extend beyond your internship end date.';

    public const MSG_FUTURE = 'Journal entries cannot be submitted for a future period.';

    public const MSG_OVERLAP = 'This journal period overlaps an existing weekly journal.';

    public const MSG_RANGE_ORDER = 'The journal end date must be on or after the start date.';

    public const MSG_NO_START = 'Your internship start date is not set yet. Journal entries are unavailable until deployment begins.';

    /**
     * @return array{can_create: bool, reason: ?string, start_date: ?string, end_date: ?string, today: string, max_date: string, min_date: ?string}
     */
    public function bounds(Internship $internship): array
    {
        $today = ManilaTime::todayDateString();
        $start = $this->dateString($internship->start_date);
        $end = $this->resolvedInternshipEnd($internship);
        $gate = $this->creationGateMessage($internship);

        $maxDate = $today;
        if ($end !== null && $end < $maxDate) {
            $maxDate = $end;
        }

        return [
            'can_create' => $gate === null,
            'reason' => $gate,
            'start_date' => $start,
            'end_date' => $end,
            'today' => $today,
            'max_date' => $maxDate,
            'min_date' => $start,
        ];
    }

    public function assertEligible(Internship $internship): void
    {
        $message = $this->creationGateMessage($internship);
        if ($message !== null) {
            throw ValidationException::withMessages(['date' => $message]);
        }
    }

    /**
     * Validate date range + overlap and return the chronological week number.
     *
     * @return array{week_number: int, start: string, end: string}
     */
    public function validateAndResolveWeek(
        Internship $internship,
        mixed $startRaw,
        mixed $endRaw,
        ?int $excludeJournalId = null
    ): array {
        $this->assertEligible($internship);

        $start = $this->dateString($startRaw);
        $end = $this->dateString($endRaw);

        if ($start === null || $end === null) {
            throw ValidationException::withMessages([
                'date' => 'A valid journal start and end date are required.',
            ]);
        }

        if ($end < $start) {
            throw ValidationException::withMessages(['end_date' => self::MSG_RANGE_ORDER]);
        }

        $internshipStart = $this->dateString($internship->start_date);
        if ($internshipStart === null) {
            throw ValidationException::withMessages(['date' => self::MSG_NO_START]);
        }

        // Full range must fall on/after internship start (rejects partial cross).
        if ($start < $internshipStart) {
            throw ValidationException::withMessages(['date' => self::MSG_BEFORE_START]);
        }

        $internshipEnd = $this->resolvedInternshipEnd($internship);
        if ($internshipEnd !== null && $end > $internshipEnd) {
            throw ValidationException::withMessages(['end_date' => self::MSG_AFTER_END]);
        }

        $today = ManilaTime::todayDateString();
        if ($start > $today || $end > $today) {
            throw ValidationException::withMessages(['date' => self::MSG_FUTURE]);
        }

        $weekNumber = $this->weekNumberFor($internshipStart, $start);
        if ($weekNumber < 1 || $weekNumber > 52) {
            throw ValidationException::withMessages([
                'date' => 'The journal period is outside the supported internship week range.',
            ]);
        }

        $excludeId = $excludeJournalId;
        if (! $excludeId) {
            $excludeId = $internship->journals()
                ->academic()
                ->where('week_number', $weekNumber)
                ->value('id');
        }

        $overlap = $internship->journals()
            ->academic()
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->whereNotNull('date')
            ->whereNotNull('end_date')
            ->whereDate('date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['date' => self::MSG_OVERLAP]);
        }

        return [
            'week_number' => $weekNumber,
            'start' => $start,
            'end' => $end,
            'upsert_journal_id' => $excludeId ? (int) $excludeId : null,
        ];
    }

    /** Highest supported internship week (same bound used when resolving journals). */
    public const MAX_WEEKS = 52;

    /**
     * Authoritative journal week windows for an internship, derived from the same
     * rule as weekNumberFor(): Week N covers start_date + 7(N-1) .. +6 days,
     * clipped to the internship end. Without an end date, weeks run through the
     * week after the current Asia/Manila week (never an arbitrary future week).
     *
     * @return list<array{week_number: int, start_date: string, end_date: string}>
     */
    public function weekWindows(Internship $internship): array
    {
        $start = $this->dateString($internship->start_date);
        if ($start === null) {
            return [];
        }

        $end = $this->resolvedInternshipEnd($internship);
        if ($end !== null && $end >= $start) {
            $lastWeek = $this->weekNumberFor($start, $end);
        } else {
            $today = ManilaTime::todayDateString();
            $lastWeek = $this->weekNumberFor($start, max($start, $today)) + 1;
        }
        $lastWeek = min(self::MAX_WEEKS, max(1, $lastWeek));

        $origin = Carbon::parse($start, ManilaTime::TZ)->startOfDay();
        $weeks = [];
        for ($week = 1; $week <= $lastWeek; $week++) {
            $weekStart = $origin->copy()->addDays(7 * ($week - 1));
            $weekEnd = $weekStart->copy()->addDays(6);
            $endString = $weekEnd->toDateString();
            if ($end !== null && $end >= $start && $endString > $end) {
                $endString = $end;
            }
            $weeks[] = [
                'week_number' => $week,
                'start_date' => $weekStart->toDateString(),
                'end_date' => $endString,
            ];
        }

        return $weeks;
    }

    /** The authoritative window for one week, or null when the week does not exist. */
    public function weekWindow(Internship $internship, int $weekNumber): ?array
    {
        foreach ($this->weekWindows($internship) as $window) {
            if ($window['week_number'] === $weekNumber) {
                return $window;
            }
        }

        return null;
    }

    /**
     * Chronological internship week: Week 1 begins on internship start_date.
     */
    public function weekNumberFor(string $internshipStart, string $journalStart): int
    {
        $origin = Carbon::parse($internshipStart, ManilaTime::TZ)->startOfDay();
        $day = Carbon::parse($journalStart, ManilaTime::TZ)->startOfDay();

        if ($day->lt($origin)) {
            return 0;
        }

        return (int) intdiv($origin->diffInDays($day), 7) + 1;
    }

    public function creationGateMessage(Internship $internship): ?string
    {
        $status = InternshipStatuses::normalize($internship->status);
        $live = ['placed', 'active', 'for_evaluation'];

        if (! in_array($status, $live, true)) {
            return self::MSG_NO_ACTIVE;
        }

        if (! $internship->company_id) {
            return self::MSG_NO_ACTIVE;
        }

        if ($this->dateString($internship->start_date) === null) {
            return self::MSG_NO_START;
        }

        return null;
    }

    public function resolvedInternshipEnd(Internship $internship): ?string
    {
        return $this->dateString($internship->end_date)
            ?? $this->dateString($internship->expected_end_date);
    }

    public function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof Carbon) {
                return $value->timezone(ManilaTime::TZ)->toDateString();
            }

            return Carbon::parse((string) $value, ManilaTime::TZ)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function findEditableJournal(Internship $internship, ?int $journalId, ?int $clientWeek): ?JournalEntry
    {
        if ($journalId) {
            $journal = $internship->journals()->academic()->whereKey($journalId)->first();
            if ($journal) {
                return $journal;
            }
        }

        if ($clientWeek) {
            return $internship->journals()->academic()->where('week_number', $clientWeek)->first();
        }

        return null;
    }
}
