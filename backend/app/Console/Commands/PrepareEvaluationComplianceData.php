<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\Internship;
use App\Models\User;
use App\Services\DocumentComplianceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Idempotent local evaluation prep for Clarence (2300592) compliance.
 * Does not modify other students. Safe to re-run.
 */
class PrepareEvaluationComplianceData extends Command
{
    protected $signature = 'interntrack:prepare-evaluation-data
                            {--student=2300592 : Student number to prepare}
                            {--dry-run : Report only; do not write}';

    protected $description = 'Prepare controlled compliance backing data for one evaluation student (default Clarence 2300592)';

    public function handle(DocumentComplianceService $compliance): int
    {
        $number = (string) $this->option('student');
        $dry = (bool) $this->option('dry-run');

        $student = User::query()
            ->where('student_number', $number)
            ->where('role', 'student')
            ->with('studentProfile.program')
            ->first();

        if (! $student) {
            $this->error("Student {$number} not found.");

            return self::FAILURE;
        }

        $internship = Internship::query()
            ->where('student_id', $student->id)
            ->whereNotIn('status', ['cancelled', 'withdrawn', 'terminated'])
            ->orderByDesc('id')
            ->first();

        if (! $internship) {
            $this->error("No active internship for student {$number}.");

            return self::FAILURE;
        }

        $before = $compliance->evaluateStudent($student, $internship);
        $this->info("Before: {$before['satisfied_count']} / {$before['required_count']} ({$before['compliance_pct']}%)");

        if ($dry) {
            foreach ($before['details'] as $d) {
                $this->line(($d['status'] ?? '?').' | '.($d['source'] ?? '?').' | '.($d['name'] ?? ''));
            }

            return self::SUCCESS;
        }

        $created = [];
        DB::transaction(function () use ($student, $internship, $compliance, &$created) {
            $documents = Document::query()
                ->where('internship_id', $internship->id)
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->get();

            $templates = $compliance->applicableTemplatesForStudent($student);

            foreach ($templates as $template) {
                $state = $compliance->resolveTemplateState($template, $internship, $documents);
                if (($state['status'] ?? '') === 'approved') {
                    continue;
                }

                $code = $template->system_code ?: DocumentComplianceService::normalizeLabel($template->name);

                if (in_array($code, ['daily_time_record', 'daily_time_record_dtr'], true)
                    || DocumentComplianceService::normalizeLabel($template->name) === 'daily_time_record') {
                    $exists = AttendanceLog::query()->where('internship_id', $internship->id)->exists();
                    if (! $exists) {
                        AttendanceLog::create([
                            'internship_id' => $internship->id,
                            'date' => now()->toDateString(),
                            'am_time_in' => '08:00:00',
                            'am_time_out' => '12:00:00',
                            'hours_rendered' => 4,
                            'status' => 'validated',
                        ]);
                        $created[] = 'attendance:seed';
                    }
                    continue;
                }

                if (in_array($code, ['performance_evaluation', 'host_evaluation'], true)
                    || in_array(DocumentComplianceService::normalizeLabel($template->name), ['performance_evaluation', 'host_evaluation'], true)) {
                    $evalQuery = Evaluation::query()->where('internship_id', $internship->id);
                    if (Schema::hasColumn('evaluations', 'status')) {
                        $evalQuery->whereIn('status', ['submitted', 'approved', 'finalized', 'completed']);
                    }
                    if (! $evalQuery->exists()) {
                        $payload = [
                            'internship_id' => $internship->id,
                            'evaluated_by' => $internship->supervisor_id ?: $internship->faculty_id,
                            'evaluator_type' => $code === 'host_evaluation' ? 'supervisor' : 'faculty',
                            'form_type' => $code === 'host_evaluation' ? 'host' : 'FO-24',
                            'submitted_at' => now(),
                            'general_comments' => 'Evaluation fixture for compliance testing.',
                        ];
                        Evaluation::create($payload);
                        $created[] = 'evaluation:'.$code;
                    }
                    continue;
                }

                // Manual / custom upload requirements — update-or-create approved placeholder.
                $match = $compliance->bestMatchingDocument($template, $documents);
                if ($match && strtolower((string) $match->status) === 'approved') {
                    continue;
                }

                if ($match) {
                    $match->update([
                        'document_type' => $template->name,
                        'status' => 'approved',
                        'current_stage' => 'completed',
                        'submitted_at' => $match->submitted_at ?: now(),
                        'remarks' => $match->remarks ?: 'Evaluation fixture (approved)',
                    ]);
                    $doc = $match->fresh();
                    $created[] = 'document:update:'.$template->name.'#'.$doc->id;
                } else {
                    $doc = $internship->documents()->create([
                        'document_type' => $template->name,
                        'status' => 'approved',
                        'current_stage' => 'completed',
                        'submitted_at' => now(),
                        'remarks' => 'Evaluation fixture (approved)',
                    ]);
                    $created[] = 'document:create:'.$template->name.'#'.$doc->id;
                }

                $doc->load('attachments');
                if ($doc->attachments->isEmpty()) {
                    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
                    $path = "internships/{$internship->id}/documents/eval_".Str::slug($template->name).'_'.Str::uuid().'.png';
                    Storage::disk('local')->put($path, $png ?: 'x');
                    $doc->attachments()->create([
                        'file_path' => $path,
                        'file_name' => 'evaluation_placeholder.png',
                        'file_size' => strlen($png ?: 'x'),
                        'mime_type' => 'image/png',
                    ]);
                }

                $documents = Document::query()
                    ->where('internship_id', $internship->id)
                    ->orderByDesc('submitted_at')
                    ->orderByDesc('id')
                    ->get();
            }
        });

        $after = $compliance->evaluateStudent($student->fresh(['studentProfile.program']), $internship->fresh());
        $this->info("After: {$after['satisfied_count']} / {$after['required_count']} ({$after['compliance_pct']}%)");
        $this->table(['Action'], collect($created)->map(fn ($c) => [$c])->all() ?: [['(no writes — already complete)']]);

        foreach ($after['details'] as $d) {
            $this->line(($d['status'] ?? '?').' | '.($d['source'] ?? '?').' | '.($d['name'] ?? ''));
        }

        return $after['satisfied_count'] === $after['required_count'] && $after['required_count'] > 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
