<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\Internship;
use App\Models\JournalEntry;
use App\Models\OjtRequirementTemplate;
use App\Models\User;
use App\Support\RequiredDocuments;
use App\Support\RequirementAudience;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Single authoritative document-compliance resolver for InternTrack.
 *
 * Matching priority:
 * 1) requirement_template_id on documents (when present)
 * 2) exact document_type === template display name
 * 3) aliases via system_code / canonical names
 * 4) system-generated satisfaction (attendance / evaluation / journal)
 */
class DocumentComplianceService
{
    /** @return list<array{code: string, name: string}> */
    public static function systemCodeCatalog(): array
    {
        return [
            ['code' => 'application_letter', 'name' => 'Application Letter'],
            ['code' => 'curriculum_vitae', 'name' => 'Curriculum Vitae'],
            ['code' => 'recommendation_letter', 'name' => 'Recommendation Letter'],
            ['code' => 'acceptance_form', 'name' => 'Acceptance Form'],
            ['code' => 'consent_form', 'name' => 'Consent Form'],
            ['code' => 'training_plan', 'name' => 'Training Plan'],
            ['code' => 'daily_time_record', 'name' => 'Daily Time Record'],
            ['code' => 'performance_evaluation', 'name' => 'Performance Evaluation'],
            ['code' => 'memorandum_of_agreement', 'name' => 'Memorandum of Agreement'],
            ['code' => 'visitation_form', 'name' => 'Visitation Form'],
            ['code' => 'certificate_of_completion', 'name' => 'Certificate of Completion'],
            ['code' => 'host_evaluation', 'name' => 'Host Evaluation'],
            ['code' => 'ojt_photos', 'name' => 'OJT Photos'],
        ];
    }

    public static function normalizeLabel(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_');
    }

    /**
     * Alias labels that may appear on documents.document_type for a system code.
     *
     * @return list<string>
     */
    public static function aliasesForCode(?string $systemCode, ?string $displayName = null): array
    {
        $aliases = [];
        if ($displayName) {
            $aliases[] = $displayName;
        }

        foreach (self::systemCodeCatalog() as $row) {
            if ($systemCode && $row['code'] === $systemCode) {
                $aliases[] = $row['name'];
            }
        }

        // Legacy / near-duplicate labels observed in local data
        $extra = match ($systemCode) {
            'curriculum_vitae' => ['CV', 'Resume', 'Curriculum Vitae (CV)', 'student_cv', 'Student CV'],
            'application_letter' => ['Application Letter', 'Internship Application Letter', 'application_letter'],
            'ojt_photos' => ['OJT Photo', 'OJT Photos', 'Photos', 'ojt_photo'],
            'acceptance_form' => ['Acceptance Form', 'acceptance_form'],
            'consent_form' => ['Consent Form', 'consent_form'],
            'training_plan' => ['Training Plan', 'training_plan'],
            'visitation_form' => ['Visitation Form', 'visitation_form'],
            'certificate_of_completion' => ['Certificate of Completion', 'completion_certificate'],
            'recommendation_letter' => ['Recommendation Letter', 'recommendation_letter', 'recommendation_request'],
            'daily_time_record' => ['DTR', 'Daily Time Record', 'FO-30'],
            'performance_evaluation' => ['Performance Evaluation', 'FO-24', 'Student Intern Performance'],
            'memorandum_of_agreement' => ['MOA', 'MOA2', 'Memorandum of Agreement (MOA)', 'Moa', 'moa_document'],
            'host_evaluation' => ['Host Evaluation', 'host_evaluation'],
            default => [],
        };

        return array_values(array_unique(array_filter(array_merge($aliases, $extra))));
    }

    /**
     * Applicable requirements for one student (system-wide + audience customs).
     * Deduped by system_code when present.
     *
     * @return Collection<int, OjtRequirementTemplate>
     */
    public function applicableTemplatesForStudent(User $student): Collection
    {
        $query = OjtRequirementTemplate::query()
            ->with('targets')
            ->active();

        RequirementAudience::scopeTemplatesForStudent($query, $student);

        $templates = $query->orderBy('sort_order')->orderBy('id')->get();

        $seenCodes = [];
        $deduped = collect();

        foreach ($templates as $template) {
            $code = $template->system_code ?: null;
            if ($code) {
                if (isset($seenCodes[$code])) {
                    continue;
                }
                $seenCodes[$code] = true;
            } else {
                // Non-system customs: also skip exact duplicate names when both are system-less
                $key = 'name:'.self::normalizeLabel($template->name);
                if (isset($seenCodes[$key])) {
                    continue;
                }
                $seenCodes[$key] = true;
            }
            $deduped->push($template);
        }

        return new \Illuminate\Database\Eloquent\Collection($deduped->all());
    }

    /**
     * @return array{
     *   required_count: int,
     *   satisfied_count: int,
     *   pending_count: int,
     *   missing_count: int,
     *   rejected_count: int,
     *   compliance_pct: int,
     *   required_types: list<string>,
     *   satisfied: list<string>,
     *   pending: list<string>,
     *   missing: list<string>,
     *   rejected: list<string>,
     *   details: list<array<string, mixed>>
     * }
     */
    public function evaluateStudent(User $student, ?Internship $internship = null): array
    {
        $internship = $internship
            ?: ($student->relationLoaded('activeInternship')
                ? $student->activeInternship
                : Internship::query()
                    ->where('student_id', $student->id)
                    ->whereNotIn('status', ['cancelled', 'withdrawn', 'terminated'])
                    ->orderByDesc('id')
                    ->first());

        $templates = $this->applicableTemplatesForStudent($student);
        $documents = collect();
        if ($internship) {
            $documents = Document::query()
                ->where('internship_id', $internship->id)
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->get();
        }

        $satisfied = [];
        $pending = [];
        $missing = [];
        $rejected = [];
        $details = [];

        foreach ($templates as $template) {
            $state = $this->resolveTemplateState($template, $internship, $documents);
            $status = $state['status'];
            $label = match ($status) {
                'approved' => ($state['source'] ?? 'upload') === 'upload' ? 'Approved' : 'Completed',
                'pending' => 'Pending Review',
                'rejected' => 'Rejected',
                default => 'Missing',
            };

            $details[] = [
                'template_id' => $template->id,
                'system_code' => $template->system_code,
                'name' => $template->name,
                'is_system' => (bool) $template->is_system,
                'status' => $status,
                'status_label' => $label,
                'source' => $state['source'],
                'document_id' => $state['document_id'] ?? null,
                'approved' => $status === 'approved',
                'pending' => $status === 'pending',
                'missing' => $status === 'missing',
                'rejected' => $status === 'rejected',
            ];

            if ($status === 'approved') {
                $satisfied[] = $template->name;
            } elseif ($status === 'pending') {
                $pending[] = $template->name;
            } elseif ($status === 'rejected') {
                $rejected[] = $template->name;
            } else {
                $missing[] = $template->name;
            }
        }

        $requiredCount = $templates->count();
        $satisfiedCount = count($satisfied);
        $pendingCount = count($pending);
        $missingCount = count($missing);
        $rejectedCount = count($rejected);

        return [
            'required_count' => $requiredCount,
            'satisfied_count' => $satisfiedCount,
            'pending_count' => $pendingCount,
            'missing_count' => $missingCount,
            'rejected_count' => $rejectedCount,
            'compliance_pct' => $requiredCount > 0 ? (int) round($satisfiedCount / $requiredCount * 100) : 0,
            'required_types' => $templates->pluck('name')->values()->all(),
            'satisfied' => $satisfied,
            'pending' => $pending,
            'missing' => $missing,
            'rejected' => $rejected,
            'details' => $details,
        ];
    }

    /**
     * Documents that satisfy a template via template_id or alias labels.
     *
     * @param  Collection<int, Document>  $documents
     * @return Collection<int, Document>
     */
    public function matchingDocumentsForTemplate(OjtRequirementTemplate $template, Collection $documents): Collection
    {
        $aliases = collect(self::aliasesForCode($template->system_code, $template->name))
            ->map(fn ($n) => self::normalizeLabel($n))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $documents->filter(function (Document $doc) use ($template, $aliases) {
            if (
                Schema::hasColumn('documents', 'requirement_template_id')
                && $doc->requirement_template_id
                && (int) $doc->requirement_template_id === (int) $template->id
            ) {
                return true;
            }

            $type = self::normalizeLabel($doc->document_type);

            return $type !== '' && in_array($type, $aliases, true);
        })->values();
    }

    /**
     * Prefer an approved match; otherwise the newest matching submission.
     *
     * @param  Collection<int, Document>  $documents
     */
    public function bestMatchingDocument(OjtRequirementTemplate $template, Collection $documents): ?Document
    {
        $matching = $this->matchingDocumentsForTemplate($template, $documents);
        if ($matching->isEmpty()) {
            return null;
        }

        $active = $matching->filter(fn (Document $d) => ! method_exists($d, 'trashed') || ! $d->trashed())->values();
        $pool = $active->isNotEmpty() ? $active : $matching;

        $approved = $pool->first(fn (Document $d) => strtolower((string) $d->status) === 'approved');

        return $approved ?: $pool->first();
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return array{status: string, source: string, document_id?: int|null}
     */
    public function resolveTemplateState(
        OjtRequirementTemplate $template,
        ?Internship $internship,
        Collection $documents
    ): array {
        if ($system = $this->systemGeneratedSatisfaction($template, $internship)) {
            return $system;
        }

        $matching = $this->matchingDocumentsForTemplate($template, $documents);

        if ($matching->isEmpty()) {
            return ['status' => 'missing', 'source' => 'upload', 'document_id' => null];
        }

        // Prefer approved; rejected historical must not override later approved.
        $approved = $matching->first(fn (Document $d) => strtolower((string) $d->status) === 'approved');
        if ($approved) {
            return ['status' => 'approved', 'source' => 'upload', 'document_id' => $approved->id];
        }

        $latest = $matching->first();
        $status = strtolower((string) ($latest->status ?? ''));
        if (in_array($status, ['pending', 'pending_review', 'under_review', 'pending_faculty', 'resubmitted', 'submitted'], true)) {
            return ['status' => 'pending', 'source' => 'upload', 'document_id' => $latest->id];
        }

        if (in_array($status, ['rejected', 'declined'], true)) {
            return ['status' => 'rejected', 'source' => 'upload', 'document_id' => $latest->id];
        }

        return ['status' => 'missing', 'source' => 'upload', 'document_id' => $latest->id];
    }

    /**
     * @return array{status: string, source: string}|null
     */
    private function systemGeneratedSatisfaction(OjtRequirementTemplate $template, ?Internship $internship): ?array
    {
        if (! $internship) {
            return null;
        }

        $code = $template->system_code ?: self::normalizeLabel($template->name);

        if (in_array($code, ['daily_time_record', 'daily_time_record_dtr'], true)
            || self::normalizeLabel($template->name) === 'daily_time_record') {
            $hasAttendance = AttendanceLog::query()
                ->where('internship_id', $internship->id)
                ->exists();

            return $hasAttendance
                ? ['status' => 'approved', 'source' => 'attendance']
                : null;
        }

        if (in_array($code, ['performance_evaluation', 'host_evaluation'], true)
            || in_array(self::normalizeLabel($template->name), ['performance_evaluation', 'host_evaluation'], true)) {
            $formType = $code === 'host_evaluation' ? 'FO-22' : 'FO-24';
            $evalQuery = Evaluation::query()
                ->where('internship_id', $internship->id)
                ->where('form_type', $formType);
            if (Schema::hasColumn('evaluations', 'status')) {
                $evalQuery->whereIn('status', ['submitted', 'approved', 'finalized', 'completed']);
            } elseif (Schema::hasColumn('evaluations', 'submitted_at')) {
                $evalQuery->whereNotNull('submitted_at');
            }
            $hasEval = $evalQuery->exists();

            return $hasEval
                ? ['status' => 'approved', 'source' => 'evaluation']
                : null;
        }

        if ($code === 'weekly_journal' || self::normalizeLabel($template->name) === 'weekly_journal') {
            $hasJournal = JournalEntry::query()
                ->where('internship_id', $internship->id)
                ->whereIn('status', ['approved', 'submitted'])
                ->exists();

            return $hasJournal
                ? ['status' => 'approved', 'source' => 'journal']
                : null;
        }

        return null;
    }

    /**
     * Report rows for a collection of students (shared by Faculty + Coordinator).
     *
     * @param  Collection<int, User>  $students
     * @return array{rows: list<array<string, mixed>>, required_types: list<string>, generated_at: string}
     */
    public function reportForStudents(Collection $students): array
    {
        $unionTypes = [];
        $rows = $students->map(function (User $u) use (&$unionTypes) {
            $result = $this->evaluateStudent($u);
            foreach ($result['required_types'] as $type) {
                $unionTypes[$type] = true;
            }

            return [
                'student_name' => trim((optional($u->studentProfile)->last_name ?? '').', '.(optional($u->studentProfile)->first_name ?? '')),
                'student_number' => $u->student_number,
                'program' => $u->studentProfile?->program?->name ?? '-',
                'approved_docs' => $result['satisfied_count'],
                'pending_docs' => $result['pending_count'],
                'required_docs' => $result['required_count'],
                'compliance_pct' => $result['compliance_pct'],
                'missing_docs' => $result['missing'],
                'pending_doc_types' => $result['pending'],
                'satisfied_docs' => $result['satisfied'],
                'rejected_docs' => $result['rejected'],
                'rejected_count' => $result['rejected_count'],
                // Full per-requirement status list (same resolver as progress counts).
                'requirements' => $result['details'],
            ];
        })->values()->all();

        return [
            'rows' => $rows,
            'required_types' => array_keys($unionTypes),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Compact compliance counts for dashboards / monitoring cards.
     *
     * @return array{
     *   approved: int,
     *   pending: int,
     *   missing: int,
     *   rejected: int,
     *   total: int,
     *   pct: int,
     *   complete: bool,
     *   label: string,
     *   status: string,
     *   details: list<array<string, mixed>>
     * }
     */
    public function summaryForStudent(User $student, ?Internship $internship = null): array
    {
        $result = $this->evaluateStudent($student, $internship);
        $complete = $result['required_count'] > 0
            && $result['satisfied_count'] === $result['required_count'];

        return [
            'approved' => $result['satisfied_count'],
            'pending' => $result['pending_count'],
            'missing' => $result['missing_count'],
            'rejected' => $result['rejected_count'],
            'total' => $result['required_count'],
            'pct' => $result['compliance_pct'],
            'complete' => $complete,
            'label' => $complete
                ? 'Complete'
                : (
                    $result['required_count'] > 0
                        ? $result['missing_count'] + $result['rejected_count'] + $result['pending_count'].' Remaining'
                        : 'No Requirements'
                ),
            'status' => $complete ? 'complete' : 'pending',
            'details' => $result['details'],
        ];
    }
}
