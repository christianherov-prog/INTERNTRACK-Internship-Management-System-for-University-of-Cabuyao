<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Evaluation;
use App\Models\Internship;
use App\Models\JournalEntry;
use App\Models\Notification;
use App\Models\SupervisorInviteToken;
use App\Models\User;
use App\Services\DtrWorkflowService;
use App\Services\InternshipProgressService;
use App\Services\OfficialFormDataService;
use App\Services\SupervisorFeedbackService;
use App\Support\ApiResponse;
use App\Support\InternshipStatuses;
use App\Support\NameParts;
use App\Support\SignatureCapture;
use App\Support\UniqueWrite;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupervisorController extends Controller
{
    private const ACTIVE_STATUSES = ['ongoing', 'active', 'for_evaluation', 'placed'];

    /** GET /api/v1/supervisor/dashboard */
    public function dashboard(Request $request)
    {
        $supervisorId = $request->user()->id;

        $internships = Internship::where('supervisor_id', $supervisorId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->with(['student.studentProfile', 'company'])
            ->get();

        $internshipIds = $internships->pluck('id');

        $pendingAttendance = $internshipIds->isEmpty()
            ? 0
            : AttendanceLog::whereIn('internship_id', $internshipIds)
                ->where('status', 'pending')
                ->count();

        $pendingEvals = Internship::where('supervisor_id', $supervisorId)
            ->whereNotIn('status', ['terminated', 'withdrawn', 'cancelled'])
            ->with(['evaluations' => fn ($q) => $q->where('evaluator_type', 'supervisor')])
            ->get()
            ->filter(function (Internship $internship) {
                $evals = $internship->evaluations;

                return ! $evals->contains('form_type', 'FO-24') || ! $evals->contains('form_type', 'FO-03');
            })
            ->count();

        // Recent activity — last 5 validated attendance records
        $recentAttendance = AttendanceLog::whereIn('internship_id', $internshipIds)
            ->where('status', 'validated')
            ->with(['internship.student.studentProfile'])
            ->latest('validated_at')
            ->limit(5)
            ->get()
            ->map(fn ($log) => [
                'type' => 'attendance',
                'student' => $log->internship->student->studentProfile ? trim("{$log->internship->student->studentProfile->last_name}, {$log->internship->student->studentProfile->first_name}") : $log->internship->student->username,
                'date' => $log->date,
                'hours' => $log->hours_rendered,
                'action_at' => $log->validated_at,
            ]);

        $recentActivity = $recentAttendance->sortByDesc('action_at')->take(5)->values();

        $supervisor = $request->user()->load('supervisorProfile.company');

        $allInterns = Internship::where('supervisor_id', $supervisorId)
            ->with(['student.studentProfile.program', 'company', 'evaluations' => function ($query) {
                $query->where('evaluator_type', 'supervisor');
            }])
            ->get()
            ->map(function ($internship) {
                $evals = $internship->evaluations;
                $hasMidterm = $evals->contains('evaluation_period', 'midterm');
                $hasFinal = $evals->contains('evaluation_period', 'final');
                $profile = $internship->student?->studentProfile;
                $progress = InternshipProgressService::snapshot($internship);
                $eligibility = InternshipProgressService::evaluationEligibility($internship);

                return [
                    'id' => $internship->id,
                    'student' => $profile ? trim("{$profile->last_name}, {$profile->first_name}") : $internship->student?->username,
                    'course' => $profile?->program?->name ?? $profile?->course_name ?? 'N/A',
                    'status' => $internship->status,
                    'hours_rendered' => $progress['hours_rendered'],
                    'target_hours' => $progress['target_hours'],
                    'remaining_hours' => $progress['remaining_hours'],
                    'progress_pct' => $progress['progress_pct'],
                    'company' => $internship->company?->company_name,
                    'term' => $internship->term,
                    'evaluation_status' => [
                        'midterm' => $hasMidterm,
                        'final' => $hasFinal,
                    ],
                    'evaluation_eligibility' => $eligibility,
                ];
            });

        $completedEvaluations = Evaluation::where('evaluated_by', $supervisorId)
            ->with(['internship.student.studentProfile'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'profile' => $supervisor->supervisorProfile,
            'company_name' => $internships->first()?->company?->company_name ?? null,
            'assigned_interns' => $allInterns,
            'completed_evaluations' => $completedEvaluations,
            'pending_attendance' => $pendingAttendance,
            'pending_evals' => $pendingEvals,
            'recent_activity' => $recentActivity,
        ]);
    }

    /**
     * GET /api/v1/supervisor/assigned-interns
     * GET /api/v1/supervisor/assigned-students (alias)
     *
     * Read-only roster with placement + official internship status (no status mutation).
     */
    public function assignedInterns(Request $request)
    {
        $rows = Internship::where('supervisor_id', $request->user()->id)
            ->with(['student.studentProfile', 'company'])
            ->orderBy('status')
            ->paginate(20);

        $mapped = $rows->getCollection()->map(function (Internship $i) {
            $p = $i->student?->studentProfile;
            $progress = InternshipProgressService::snapshot($i);
            $fo30 = app(OfficialFormDataService::class)->fo30($i);

            return [
                'id' => $i->id,
                'term' => $i->term,
                'status' => InternshipStatuses::normalize($i->status),
                'status_label' => InternshipStatuses::label($i->status),
                'status_reason' => $i->status_reason,
                'target_hours' => $progress['target_hours'],
                'total_hours_rendered' => $progress['hours_rendered'],
                'remaining_hours' => $progress['remaining_hours'],
                'progress_pct' => $progress['progress_pct'],
                'company' => $i->company ? [
                    'id' => $i->company->id,
                    'company_name' => $i->company->company_name,
                    'company_logo_path' => $fo30['company_logo_path'] ?? null,
                ] : null,
                'student' => [
                    'id' => $i->student_id,
                    'username' => $i->student?->username,
                    'student_profile' => $p ? [
                        'first_name' => $p->first_name,
                        'last_name' => $p->last_name,
                        'student_number' => $p->student_number,
                        'course_name' => $p->program?->name,
                        'program' => $p->program?->name,
                    ] : null,
                ],
                'supervisor_name' => $fo30['supervisor_name'] ?? null,
                'student_signature_path' => $fo30['student_signature_path'] ?? null,
                'supervisor_signature_path' => $fo30['supervisor_signature_path'] ?? null,
                'attendance_logs' => $fo30['logs'] ?? [],
                'evaluation_eligibility' => InternshipProgressService::evaluationEligibility($i),
            ];
        });
        $rows->setCollection($mapped);

        return ApiResponse::list($rows);
    }

    /** Alias for manuscript wording / FE consistency with faculty. */
    public function assignedStudents(Request $request)
    {
        return $this->assignedInterns($request);
    }

    /** GET /api/v1/supervisor/feedback — intern narrative feedback for assigned students */
    public function feedback(Request $request)
    {
        $internshipIds = Internship::where('supervisor_id', $request->user()->id)->pluck('id');
        $notes = JournalEntry::whereIn('internship_id', $internshipIds)
            ->where('status', SupervisorFeedbackService::NOTE_STATUS)
            ->whereNotNull('supervisor_feedback')
            ->with(['internship.student.studentProfile', 'internship.company', 'internship.supervisor.supervisorProfile'])
            ->orderByDesc('supervisor_reviewed_at')
            ->paginate(20);

        $service = app(SupervisorFeedbackService::class);
        $notes->getCollection()->transform(fn (JournalEntry $note) => $service->serialize($note) + [
            'id' => $note->id,
            'week_number' => $note->week_number,
            'internship' => $note->internship,
        ]);

        return ApiResponse::list($notes);
    }

    /**
     * POST /api/v1/supervisor/feedback/{internshipId}
     * Intern-level narrative feedback (not journal validation).
     */
    public function submitFeedback(Request $request, int $internshipId)
    {
        $min = (int) config('interntrack.supervisor_feedback_min_length', 5);
        $max = (int) config('interntrack.supervisor_feedback_max_length', 1000);
        $request->validate([
            'feedback' => "required|string|min:{$min}|max:{$max}",
        ]);

        $internship = Internship::with('student.studentProfile')->findOrFail($internshipId);
        $service = app(SupervisorFeedbackService::class);
        $service->assertAssignedSupervisor($request->user(), $internship);
        $note = $service->upsert($internship, $request->user(), trim((string) $request->feedback));

        audit_log($request->user()->id, 'submit_supervisor_feedback', ['internship_id' => $internshipId, 'journal_id' => $note->id]);

        return response()->json([
            'message' => 'Feedback submitted.',
            'feedback' => $service->serialize($note),
            'journal' => $note,
        ]);
    }

    /** PATCH /api/v1/supervisor/feedback/{id} */
    public function updateFeedback(Request $request, int $id)
    {
        $min = (int) config('interntrack.supervisor_feedback_min_length', 5);
        $max = (int) config('interntrack.supervisor_feedback_max_length', 1000);
        $request->validate([
            'feedback' => "required|string|min:{$min}|max:{$max}",
        ]);
        $note = JournalEntry::with('internship.student.studentProfile')->findOrFail($id);
        $service = app(SupervisorFeedbackService::class);
        $updated = $service->updateNote($note, $request->user(), trim((string) $request->feedback));
        audit_log($request->user()->id, 'update_supervisor_feedback', ['journal_id' => $id]);

        return response()->json([
            'message' => 'Feedback updated.',
            'feedback' => $service->serialize($updated),
        ]);
    }

    /** DELETE /api/v1/supervisor/feedback/{id} */
    public function deleteFeedback(Request $request, int $id)
    {
        $note = JournalEntry::with('internship')->findOrFail($id);
        app(SupervisorFeedbackService::class)->deleteNote($note, $request->user());
        audit_log($request->user()->id, 'delete_supervisor_feedback', ['journal_id' => $id]);

        return response()->json(['message' => 'Feedback removed.']);
    }

    /** GET /api/v1/supervisor/attendance */
    public function attendance(Request $request)
    {
        $internshipIds = Internship::where('supervisor_id', $request->user()->id)->pluck('id');

        $pending = AttendanceLog::whereIn('internship_id', $internshipIds)
            ->with(['internship.student.studentProfile', 'internship.company'])
            ->where('status', 'pending')
            ->orderByDesc('date')
            ->paginate(25);

        app(DtrWorkflowService::class)->decorateLogs(collect($pending->items()));

        $recentValidated = AttendanceLog::whereIn('internship_id', $internshipIds)
            ->with(['internship.student.studentProfile'])
            ->where('status', 'validated')
            ->orderByDesc('validated_at')
            ->limit(15)
            ->get();

        app(DtrWorkflowService::class)->decorateLogs($recentValidated);

        return response()->json([
            'data' => $pending->items(),
            'meta' => [
                'current_page' => $pending->currentPage(),
                'last_page' => $pending->lastPage(),
                'per_page' => $pending->perPage(),
                'total' => $pending->total(),
            ],
            'recent_validated' => $recentValidated,
        ]);
    }

    /** PATCH /api/v1/supervisor/attendance/{id}/validate */
    public function validateAttendance(Request $request, int $id)
    {
        $request->validate([
            'action' => 'required|in:validated,rejected',
            'remarks' => 'nullable|string|max:500',
        ]);

        $log = AttendanceLog::with('internship')->findOrFail($id);

        if ((int) $log->internship->supervisor_id !== (int) $request->user()->id) {
            abort(403, 'You may only verify attendance for your assigned interns.');
        }

        $request->user()->loadMissing('supervisorProfile');

        if ($log->status !== 'pending') {
            return response()->json(['message' => 'Only pending attendance records can be validated.'], 422);
        }

        $htePath = SignatureCapture::profilePath($request->user());
        $log->update([
            'status' => $request->action,
            'remarks' => $request->remarks,
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
            'hte_signature_path' => $request->action === 'validated'
                ? ($log->hte_signature_path ?: $htePath)
                : $log->hte_signature_path,
            'hte_signed_name' => $request->action === 'validated'
                ? ($log->hte_signed_name ?: NameParts::fromProfile($request->user()->supervisorProfile))
                : $log->hte_signed_name,
            'hte_signed_at' => $request->action === 'validated' ? now() : $log->hte_signed_at,
        ]);

        // Refresh placement hours (if placement is linked) then internship total
        if ($log->placement_id && $request->action === 'validated') {
            $log->placement?->refreshAccumulatedHours();
        }
        $log->internship->refreshTotalHours();

        audit_log($request->user()->id, 'validate_attendance', [
            'log_id' => $id,
            'action' => $request->action,
        ]);

        return response()->json([
            'message' => 'Attendance '.$request->action.' successfully.',
            'record' => $log->fresh(),
        ]);
    }

    /** PATCH /api/v1/supervisor/attendance/bulk-validate */
    public function bulkValidateAttendance(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'action' => 'required|in:validated,rejected',
            'remarks' => 'nullable|string|max:500',
        ]);

        $logs = AttendanceLog::whereIn('id', $request->ids)
            ->whereHas('internship', fn ($q) => $q->where('supervisor_id', $request->user()->id))
            ->where('status', 'pending')
            ->with('internship')
            ->get();

        $eligible = $logs->filter(fn ($log) => (bool) $log->clock_out);
        $affectedInternships = collect();

        $request->user()->loadMissing('supervisorProfile');
        $htePath = SignatureCapture::profilePath($request->user());
        $hteName = NameParts::fromProfile($request->user()->supervisorProfile);

        foreach ($eligible as $log) {
            $log->update([
                'status' => $request->action,
                'remarks' => $request->remarks,
                'validated_by' => $request->user()->id,
                'validated_at' => now(),
                'hte_signature_path' => $request->action === 'validated'
                    ? ($log->hte_signature_path ?: $htePath)
                    : $log->hte_signature_path,
                'hte_signed_name' => $request->action === 'validated'
                    ? ($log->hte_signed_name ?: $hteName)
                    : $log->hte_signed_name,
                'hte_signed_at' => $request->action === 'validated' ? now() : $log->hte_signed_at,
            ]);

            // Refresh placement hours if placement is linked
            if ($log->placement_id && $request->action === 'validated') {
                $log->placement?->refreshAccumulatedHours();
            }

            $affectedInternships->push($log->internship);
        }

        $affectedInternships->unique('id')->each(fn ($internship) => $internship?->refreshTotalHours());

        audit_log($request->user()->id, 'bulk_validate_attendance', [
            'log_ids' => $eligible->pluck('id'),
            'action' => $request->action,
        ]);

        return response()->json([
            'message' => "{$eligible->count()} attendance record(s) {$request->action}.",
            'processed_count' => $eligible->count(),
            'skipped_count' => $logs->count() - $eligible->count(),
        ]);
    }

    /** GET /api/v1/supervisor/evaluations */
    public function evaluations(Request $request)
    {
        $internships = Internship::where('supervisor_id', $request->user()->id)
            ->whereNotIn('status', ['terminated', 'withdrawn', 'cancelled'])
            ->with([
                'student.studentProfile.program',
                'company',
                'supervisor.supervisorProfile',
                'faculty.facultyProfile',
                'evaluations' => fn ($q) => $q->where('evaluator_type', 'supervisor'),
            ])
            ->get();

        $evaluations = Evaluation::whereIn('internship_id', $internships->pluck('id'))
            ->where('evaluator_type', 'supervisor')
            ->with(['internship.student.studentProfile.program', 'internship.company', 'internship.supervisor.supervisorProfile', 'internship.faculty.facultyProfile'])
            ->get();

        $pending = $internships
            ->map(function (Internship $internship) {
                $internshipEvals = $internship->evaluations;
                $missing = [];
                if (! $internshipEvals->contains('form_type', 'FO-24')) {
                    $missing[] = 'FO-24';
                }
                if (! $internshipEvals->contains('form_type', 'FO-03')) {
                    $missing[] = 'FO-03';
                }

                $row = $internship->toArray();
                $progress = InternshipProgressService::snapshot($internship);
                $eligibility = InternshipProgressService::evaluationEligibility($internship);
                $row['missing_forms'] = $missing;
                $row['student'] = $internship->student;
                $row['company'] = $internship->company;
                $row['program'] = $internship->student?->studentProfile?->program?->name ?: $internship->program;
                $row['hours_rendered'] = $progress['hours_rendered'];
                $row['target_hours'] = $progress['target_hours'];
                $row['remaining_hours'] = $progress['remaining_hours'];
                $row['progress_pct'] = $progress['progress_pct'];
                $row['evaluation_eligibility'] = $eligibility;

                return $row;
            })
            ->filter(fn ($row) => count($row['missing_forms']) > 0)
            ->values();

        return ApiResponse::groups(['completed' => $evaluations, 'pending' => $pending]);
    }

    /** POST /api/v1/supervisor/evaluations/{internshipId} */
    public function submitEvaluation(Request $request, int $internshipId)
    {
        $request->validate([
            'evaluation_period' => 'required|string',
            'form_type' => 'required|string',
            'responses' => 'required|array',
            'general_comments' => 'nullable|string',
        ]);

        $internship = Internship::where('supervisor_id', $request->user()->id)
            ->with(['student.studentProfile', 'coordinator'])
            ->findOrFail($internshipId);

        $internship->abortUnlessEvaluationPeriodApproved();

        $period = $request->input('evaluation_period');

        $formType = $request->input('form_type');

        $responses = $request->input('responses');
        if ($request->filled('recommendations') && empty($responses['recommendations'])) {
            $responses['recommendations'] = $request->input('recommendations');
        }

        $eval = null;
        $created = false;
        $attempt = 0;
        while ($attempt < 4) {
            try {
                [$eval, $created] = DB::transaction(function () use ($request, $internship, $period, $formType, $responses) {
                    Internship::whereKey($internship->id)->lockForUpdate()->firstOrFail();

                    $eval = Evaluation::withTrashed()
                        ->where('internship_id', $internship->id)
                        ->where('evaluator_type', 'supervisor')
                        ->where('evaluation_period', $period)
                        ->where('form_type', $formType)
                        ->first();

                    $created = false;
                    if ($eval) {
                        if ($eval->trashed()) {
                            $eval->restore();
                        }
                        $eval->fill([
                            'responses' => $responses,
                            'general_comments' => $request->input('general_comments'),
                            'evaluated_by' => $request->user()->id,
                            'submitted_at' => now(),
                        ]);
                    } else {
                        $eval = new Evaluation([
                            'internship_id' => $internship->id,
                            'evaluator_type' => 'supervisor',
                            'evaluation_period' => $period,
                            'form_type' => $formType,
                            'responses' => $responses,
                            'general_comments' => $request->input('general_comments'),
                            'evaluated_by' => $request->user()->id,
                            'submitted_at' => now(),
                        ]);
                        $created = true;
                    }

                    $eval->computeScores();
                    $sig = SignatureCapture::profilePath($request->user());
                    if ($sig) {
                        $eval->signature_path = $eval->signature_path ?: $sig;
                    }
                    if (! $eval->signer_name) {
                        $eval->signer_name = NameParts::fromProfile($request->user()->supervisorProfile);
                    }
                    $eval->save();

                    return [$eval, $created];
                });
                break;
            } catch (QueryException $e) {
                if (UniqueWrite::isDeadlock($e) && $attempt < 3) {
                    $attempt++;
                    usleep(25000 * $attempt);

                    continue;
                }
                if (! UniqueWrite::isDuplicate($e)) {
                    throw $e;
                }
                $eval = Evaluation::where('internship_id', $internship->id)
                    ->where('evaluator_type', 'supervisor')
                    ->where('evaluation_period', $period)
                    ->where('form_type', $formType)
                    ->firstOrFail();
                $created = false;
                break;
            }
        }

        $studentName = $internship->student?->studentProfile
            ? trim($internship->student->studentProfile->last_name.', '.$internship->student->studentProfile->first_name)
            : ($internship->student?->username ?? 'Intern');

        $notifyIds = User::whereIn('role', ['coordinator', 'director'])
            ->where('is_active', true)
            ->pluck('id');
        if ($internship->coordinator_id) {
            $notifyIds->push($internship->coordinator_id);
        }
        if ($created) {
            foreach ($notifyIds->unique() as $uid) {
                Notification::notify(
                    $uid,
                    'supervisor_evaluation_submitted',
                    'Industry supervisor evaluation submitted',
                    "{$studentName} — {$period} evaluation (avg {$eval->average_score}).",
                    '/coordinator/evaluations',
                    ['evaluation_id' => $eval->id, 'internship_id' => $internshipId, 'student_id' => $internship->student_id]
                );
            }
        }

        audit_log($request->user()->id, 'submit_evaluation', [
            'internship_id' => $internshipId,
            'period' => $period,
            'evaluation_id' => $eval->id,
        ]);

        return response()->json(['message' => 'Evaluation submitted successfully.', 'evaluation' => $eval], 201);
    }

    /** GET /api/v1/supervisor/absorption */
    public function absorptionList(Request $request)
    {
        $items = Internship::where('supervisor_id', $request->user()->id)
            ->where('status', 'completed')
            ->with(['student.studentProfile', 'company'])
            ->orderByDesc('end_date')
            ->get();

        return response()->json(['internships' => $items]);
    }

    /** PATCH /api/v1/supervisor/internships/{id}/absorption — blocked; Director only */
    public function recordAbsorption(Request $request, int $id)
    {
        return response()->json([
            'message' => 'Only the PALD Director may finalize absorption.',
        ], 403);
    }

    /** GET /api/v1/supervisor/companies — active HTEs for profile editing */
    public function companies()
    {
        $query = Company::query()
            ->where(function ($q) {
                $q->where('is_active', true)
                    ->orWhere('moa_status', 'active');
            })
            ->orderBy('company_name');

        $currentId = auth()->user()?->supervisorProfile?->company_id;
        if ($currentId) {
            $query->orWhere('id', $currentId);
        }

        return response()->json(['companies' => $query->get(['id', 'company_name'])]);
    }

    /**
     * POST /api/v1/supervisor/internships/{id}/end-supervision
     * Unlinks this supervisor from one internship without deleting the account
     * or affecting other internships they supervise.
     */
    public function endSupervision(Request $request, int $id)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $internship = Internship::where('id', $id)
            ->where('supervisor_id', $request->user()->id)
            ->firstOrFail();

        $studentId = $internship->student_id;
        $internship->update(['supervisor_id' => null]);

        SupervisorInviteToken::where('internship_id', $internship->id)
            ->whereIn('status', ['pending', 'pending_accept', 'registered'])
            ->update(['status' => 'expired']);

        $profile = $request->user()->supervisorProfile;
        $name = trim(($profile?->last_name ?? 'Supervisor').', '.($profile?->first_name ?? ''));

        Notification::notify(
            $studentId,
            'supervisor_unlinked',
            'Supervisor Ended Supervision',
            "{$name} is no longer supervising this internship. You can invite a new supervisor.",
            '/student/attendance'
        );

        audit_log($request->user()->id, 'end_supervision', [
            'internship_id' => $internship->id,
            'student_id' => $studentId,
            'reason' => $request->input('reason'),
        ]);

        return response()->json(['message' => 'Supervision ended for this intern. Your other internships are unchanged.']);
    }
}
