<?php

namespace App\Support;

use App\Models\Internship;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Composite at-risk rules for coordinator monitoring.
 */
final class AtRiskInternship
{
    public const PACE_RATIO = 0.30;

    public const PACE_MIN_DAYS = 28;

    public const JOURNAL_LAG_DAYS = 14;

    public const DOCS_MIN_APPROVED = 3;

    public const DOCS_LAG_DAYS = 21;

    /**
     * @return list<string> Reason codes; empty = not at risk
     */
    public static function reasons(Internship $internship, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : now();
        $status = strtolower((string) $internship->status);
        if (!in_array($status, ['ongoing', 'active'], true)) {
            return [];
        }

        $reasons = [];
        $start = $internship->start_date ? Carbon::parse($internship->start_date)->startOfDay() : null;
        $target = (float) ($internship->target_hours ?: 0);
        $hours = (float) ($internship->total_hours_rendered ?: 0);
        $ratio = $target > 0 ? ($hours / $target) : 0.0;

        if ($start && $start->lte($now->copy()->subDays(self::PACE_MIN_DAYS)) && $ratio < self::PACE_RATIO) {
            $reasons[] = 'low_hours_pace';
        }

        $lastJournal = $internship->relationLoaded('journals')
            ? $internship->journals->sortByDesc(fn ($j) => $j->date?->timestamp ?? 0)->first()
            : $internship->journals()->latest('date')->first();

        $lastJournalDate = $lastJournal?->date ? Carbon::parse($lastJournal->date)->startOfDay() : null;
        if (!$lastJournalDate || $lastJournalDate->lt($now->copy()->subDays(self::JOURNAL_LAG_DAYS)->startOfDay())) {
            $reasons[] = 'journal_lag';
        }

        $docsApproved = $internship->relationLoaded('documents')
            ? $internship->documents->where('status', 'approved')->count()
            : $internship->documents()->where('status', 'approved')->count();

        if (
            $start
            && $start->lte($now->copy()->subDays(self::DOCS_LAG_DAYS))
            && $docsApproved < self::DOCS_MIN_APPROVED
        ) {
            $reasons[] = 'docs_lag';
        }

        return $reasons;
    }

    public static function isAtRisk(Internship $internship, ?CarbonInterface $now = null): bool
    {
        return self::reasons($internship, $now) !== [];
    }

    /**
     * DB-side count for dashboard stats — does not hydrate all active internships.
     */
    public static function countActiveAtRisk(?CarbonInterface $now = null): int
    {
        return self::activeAtRiskQuery($now)->count();
    }

    public static function activeAtRiskQuery(?CarbonInterface $now = null): Builder
    {
        $now = $now ? Carbon::instance($now) : now();
        $paceCutoff = $now->copy()->subDays(self::PACE_MIN_DAYS)->toDateString();
        $journalCutoff = $now->copy()->subDays(self::JOURNAL_LAG_DAYS)->toDateString();
        $docsCutoff = $now->copy()->subDays(self::DOCS_LAG_DAYS)->toDateString();

        return Internship::query()
            ->whereIn('status', ['ongoing', 'active'])
            ->where(function (Builder $q) use ($paceCutoff, $journalCutoff, $docsCutoff) {
                $q->where(function (Builder $pace) use ($paceCutoff) {
                    $pace->whereNotNull('start_date')
                        ->whereDate('start_date', '<=', $paceCutoff)
                        ->whereRaw(
                            '(CASE WHEN COALESCE(target_hours, 0) > 0 THEN total_hours_rendered / target_hours ELSE 0 END) < ?',
                            [self::PACE_RATIO]
                        );
                })->orWhereDoesntHave('journals', function (Builder $jq) use ($journalCutoff) {
                    $jq->whereDate('date', '>=', $journalCutoff);
                })->orWhere(function (Builder $docs) use ($docsCutoff) {
                    $docs->whereNotNull('start_date')
                        ->whereDate('start_date', '<=', $docsCutoff)
                        ->whereRaw(
                            '(SELECT COUNT(*) FROM documents WHERE documents.internship_id = internships.id AND documents.status = ?) < ?',
                            ['approved', self::DOCS_MIN_APPROVED]
                        );
                });
            });
    }

    /** Human labels for UI chips. */
    public static function labels(array $reasons): array
    {
        $map = [
            'low_hours_pace' => 'Low hours pace',
            'journal_lag' => 'Journal lag',
            'docs_lag' => 'Documents behind',
        ];

        return array_values(array_filter(array_map(fn ($r) => $map[$r] ?? $r, $reasons)));
    }
}
