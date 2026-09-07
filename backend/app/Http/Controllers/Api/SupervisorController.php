<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Models\Evaluation;
use App\Models\JournalEntry;
use App\Models\Notification;
use App\Models\SupervisorInviteToken;
use App\Models\User;
use App\Services\AbsorptionService;
use App\Support\ApiResponse;
use App\Support\InternshipStatuses;
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
            : \App\Models\AttendanceLog::whereIn('internship_id', $internshipIds)
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
        $recentAttendance = \App\Models\AttendanceLog::whereIn('internship_id', $internshipIds)
            ->where('status', 'validated')
            ->with(['internship.student.studentProfile'])
            ->latest('validated_at')
            ->limit(5)
            ->get()
            ->map(fn($log) => [
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
            ->map(function($internship) {
                $evals = $internship->evaluations;
                $hasMidterm = $evals->contains('evaluation_period', 'midterm');
                $hasFinal = $evals->contains('evaluation_period', 'final');
                $profile = $internship->student?->studentProfile;
                
                return [
                    'id' => $internship->id,
                    'student' => $profile ? trim("{$profile->last_name}, {$profile->first_name}") : $internship->student?->username,
                    'course' => $profile?->program?->name ?? $profile?->course_name ?? 'N/A',
                    'status' => $internship->status,
                    'hours_rendered' => $internship->total_hours_rendered,
                    'target_hours' => $internship->target_hours,
                    'company' => $internship->company?->company_name,
                    'term' => $internship->term,
                    'evaluation_status' => [
                        'midterm' => $hasMidterm,
                        'final' => $hasFinal
                    ]
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
            return [
                'id' => $i->id,
                'term' => $i->term,
                'status' => InternshipStatuses::normalize($i->status),
                'status_label' => InternshipStatuses::label($i->status),
                'status_reason' => $i->status_reason,
                'target_hours' => $i->target_hours,
                'total_hours_rendered' => $i->total_hours_rendered,
                'company' => $i->company ? [
                    'id' => $i->company->id,
                    'company_name' => $i->company->company_name,
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
                'attendance_logs' => \App\Models\AttendanceLog::where('internship_id', $i->id)
                    ->orderBy('date', 'asc')
                    ->get(),
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

    /** GET /api/v1/supervisor/feedback — prior supervisor narrative feedback */
    public function feedback(Request $request)
    {
        $internshipIds = Internship::where('supervisor_id', $request->user()->id)->pluck('id');
        $journals = \App\Models\JournalEntry::whereIn('internship_id', $internshipIds)
            ->whereNotNull('supervisor_feedback')
            ->with('internship.student.studentProfile')
            ->orderByDesc('supervisor_reviewed_at')
            ->paginate(20);

        return ApiResponse::list($journals);
    }

    /**
     * POST /api/v1/supervisor/feedback/{internshipId}
     * Mirrors faculty narrative feedback — stored on latest journal as supervisor_feedback.
     */
    public function submitFeedback(Request $request, int $internshipId)
    {
        $request->validate(['feedback' => 'required|string|min:5|max:2000']);

        $internship = Internship::where('supervisor_id', $request->user()->id)
            ->with('student')
            ->findOrFail($internshipId);

        $journal = $internship->journals()->latest('date')->first();
        if (!$journal) {
            $journal = $internship->journals()->create([
                'entry_number' => 1,
                'week_number' => 1,
                'date' => now()->toDateString(),
                'activities_summary' => 'Supervisor feedback note',
                'status' => 'approved',
            ]);
        }

        $journal->update([
            'supervisor_feedback' => $request->feedback,
            'supervisor_reviewed_by' => $request->user()->id,
            'supervisor_reviewed_at' => now(),
        ]);

        if ($internship->student_id) {
            Notification::notify(
                $internship->student_id,
                'supervisor_feedback',
                'New feedback from Industry Supervisor',
                $request->feedback,
                '/student/logbook',
                ['journal_id' => $journal->id, 'feedback' => $request->feedback]
            );
        }

        // Coordinators can see this via evaluations/feedback oversight
        foreach (User::where('role', 'coordinator')->where('is_active', true)->pluck('id') as $coordId) {
            Notification::notify(
                $coordId,
                'supervisor_feedback_submitted',
                'Supervisor feedback submitted',
                'Industry supervisor submitted feedback for an assigned intern.',
                '/coordinator/reports',
                ['journal_id' => $journal->id, 'student_id' => $internship->student_id]
            );
        }

        audit_log($request->user()->id, 'submit_supervisor_feedback', ['internship_id' => $internshipId]);

        return response()->json([
            'message' => 'Feedback submitted.',
            'journal' => $journal->fresh(),
        ]);
    }

    /** GET /api/v1/supervisor/journals */
    public function journals(Request $request)
    {
        $internshipIds = Internship::where('supervisor_id', $request->user()->id)->pluck('id');
        $journals = JournalEntry::whereIn('internship_id', $internshipIds)
            ->pendingSupervisorReview()
            ->with(['internship.student.studentProfile', 'internship.company'])
            ->orderByDesc('date')
            ->paginate(25);

        return ApiResponse::list($journals);
    }

    /** PATCH /api/v1/supervisor/journals/{id}/review */
    public function reviewJournal(Request $request, int $id)
    {
        $request->validate([
            'action' => 'required|in:approved,needs_revision',
            'feedback' => 'nullable|string|max:1000',
        ]);

        if ($request->action === 'needs_revision') {
            $request->validate(['feedback' => 'required|string|min:3|max:1000']);
        }

        $journal = JournalEntry::with('internship.student.studentProfile')->findOrFail($id);
        if ((int) $journal->internship?->supervisor_id !== (int) $request->user()->id) {
            abort(403, 'You are not assigned to this intern.');
        }

        if ($journal->status === 'approved' && $journal->faculty_reviewed_at) {
            return response()->json(['message' => 'This journal has already been approved by faculty.'], 422);
        }

        $journal = UniqueWrite::retry(fn () => DB::transaction(function () use ($request, $id) {
            $locked = JournalEntry::with('internship.student')->lockForUpdate()->findOrFail($id);
            if ((int) $locked->internship?->supervisor_id !== (int) $request->user()->id) {
                abort(403, 'You are not assigned to this intern.');
            }

            $payload = [
                'supervisor_feedback' => $request->feedback,
                'supervisor_reviewed_by' => $request->user()->id,
                'supervisor_reviewed_at' => now(),
            ];

            if ($request->action === 'needs_revision') {
                $payload['status'] = 'needs_revision';
            } elseif ($locked->status !== 'approved') {
                $payload['status'] = 'submitted';
            }

            $locked->update($payload);

            return $locked->fresh(['internship.student.studentProfile', 'internship.company']);
        }));

        $studentId = $journal->internship?->student_id;
        $weekLabel = 'Week '.($journal->week_number ?? $journal->entry_number ?? '—');
        if ($studentId) {
            Notification::notify(
                $studentId,
                $request->action === 'approved' ? 'journal_reviewed' : 'journal_needs_revision',
                $request->action === 'approved' ? 'Journal validated by Industry Supervisor' : 'Journal needs revision',
                $request->action === 'approved'
                    ? "Your {$weekLabel} journal was validated by your industry supervisor and is awaiting faculty review."
                    : "Your {$weekLabel} journal needs revision: ".($request->feedback ?? 'Please check your entry.'),
                '/student/logbook',
                ['journal_id' => $journal->id, 'week_number' => $journal->week_number, 'action' => $request->action, 'feedback' => $request->feedback]
            );
        }

        $facultyId = $journal->internship?->faculty_id;
        if ($facultyId && $request->action === 'approved') {
            $studentName = $journal->internship?->student?->studentProfile
                ? trim($journal->internship->student->studentProfile->last_name.', '.$journal->internship->student->studentProfile->first_name)
                : ($journal->internship?->student?->username ?? 'Intern');
            Notification::notify(
                (int) $facultyId,
                'journal_submitted',
                'Journal ready for faculty review',
                "{$studentName} — {$weekLabel} was validated by the industry supervisor.",
                '/faculty/journals',
                ['journal_id' => $journal->id, 'internship_id' => $journal->internship_id]
            );
        }

        audit_log($request->user()->id, 'supervisor_review_journal', ['journal_id' => $id, 'action' => $request->action]);

        return response()->json([
            'message' => $request->action === 'approved' ? 'Journal validated.' : 'Journal returned for revision.',
            'journal' => $journal,
        ]);
    }

    /** GET /api/v1/supervisor/attendance */
    public function attendance(Request $request)
    {
        $internshipIds = Internship::where('supervisor_id', $request->user()->id)->pluck('id');

        $pending = \App\Models\AttendanceLog::whereIn('internship_id', $internshipIds)
            ->with(['internship.student.studentProfile', 'internship.company'])
            ->where('status', 'pending')
            ->orderByDesc('date')
            ->paginate(25);

        app(\App\Services\DtrWorkflowService::class)->decorateLogs(collect($pending->items()));

        $recentValidated = \App\Models\AttendanceLog::whereIn('internship_id', $internshipIds)
            ->with(['internship.student.studentProfile'])
            ->where('status', 'validated')
            ->orderByDesc('validated_at')
            ->limit(15)
            ->get();

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
            'action'  => 'required|in:validated,rejected',
            'remarks' => 'nullable|string|max:500',
        ]);

        $log = \App\Models\AttendanceLog::with('internship')->findOrFail($id);

        if ((int) $log->internship->supervisor_id !== (int) $request->user()->id) {
            abort(403, 'You may only verify attendance for your assigned interns.');
        }

        if ($log->status !== 'pending') {
            return response()->json(['message' => 'Only pending attendance records can be validated.'], 422);
        }

        $log->update([
            'status'       => $request->action,
            'remarks'      => $request->remarks,
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
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
            'record'  => $log->fresh(),
        ]);
    }

    /** PATCH /api/v1/supervisor/attendance/bulk-validate */
    public function bulkValidateAttendance(Request $request)
    {
        $request->validate([
            'ids'     => 'required|array|min:1',
            'ids.*'   => 'integer',
            'action'  => 'required|in:validated,rejected',
            'remarks' => 'nullable|string|max:500',
        ]);

        $logs = \App\Models\AttendanceLog::whereIn('id', $request->ids)
            ->whereHas('internship', fn ($q) => $q->where('supervisor_id', $request->user()->id))
            ->where('status', 'pending')
            ->with('internship')
            ->get();

        $eligible = $logs->filter(fn ($log) => (bool) $log->clock_out);
        $affectedInternships = collect();

        foreach ($eligible as $log) {
            $log->update([
                'status'       => $request->action,
                'remarks'      => $request->remarks,
                'validated_by' => $request->user()->id,
                'validated_at' => now(),
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
            'action'  => $request->action,
        ]);

        return response()->json([
            'message'         => "{$eligible->count()} attendance record(s) {$request->action}.",
            'processed_count' => $eligible->count(),
            'skipped_count'   => $logs->count() - $eligible->count(),
        ]);
    }

    /** GET /api/v1/supervisor/journals — REMOVED: Faculty now handles all journal reviews. */
    // journals() method removed per Role-Task Matrix alignment.

    /** PATCH /api/v1/supervisor/journals/{id}/review — REMOVED: Faculty now handles all journal reviews. */
    // reviewJournal() method removed per Role-Task Matrix alignment.

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
                $row['missing_forms'] = $missing;
                $row['student'] = $internship->student;
                $row['company'] = $internship->company;
                $row['program'] = $internship->student?->studentProfile?->program?->name ?: $internship->program;

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
            'form_type'         => 'required|string',
            'responses'         => 'required|array',
            'general_comments'  => 'nullable|string',
        ]);

        $internship = Internship::where('supervisor_id', $request->user()->id)
            ->with(['student.studentProfile', 'coordinator'])
            ->findOrFail($internshipId);

        $period = $request->input('evaluation_period');

        $formType = $request->input('form_type');

        $eval = null;
        $created = false;
        $attempt = 0;
        while ($attempt < 4) {
            try {
                [$eval, $created] = DB::transaction(function () use ($request, $internship, $period, $formType) {
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
                            'responses'         => $request->input('responses'),
                            'general_comments'  => $request->input('general_comments'),
                            'evaluated_by'      => $request->user()->id,
                            'submitted_at'      => now(),
                        ]);
                    } else {
                        $eval = new Evaluation([
                            'internship_id'     => $internship->id,
                            'evaluator_type'    => 'supervisor',
                            'evaluation_period' => $period,
                            'form_type'         => $formType,
                            'responses'         => $request->input('responses'),
                            'general_comments'  => $request->input('general_comments'),
                            'evaluated_by'      => $request->user()->id,
                            'submitted_at'      => now(),
                        ]);
                        $created = true;
                    }

                    $eval->computeScores();
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
        $query = \App\Models\Company::query()
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
        $name = trim(($profile?->last_name ?? 'Supervisor') . ', ' . ($profile?->first_name ?? ''));

        Notification::notify(
            $studentId,
            'supervisor_unlinked',
            'Supervisor Ended Supervision',
            "{$name} is no longer supervising this internship. You can invite a new supervisor.",
            '/student/attendance'
        );

        audit_log($request->user()->id, 'end_supervision', [
            'internship_id' => $internship->id,
            'student_id'    => $studentId,
            'reason'        => $request->input('reason'),
        ]);

        return response()->json(['message' => 'Supervision ended for this intern. Your other internships are unchanged.']);
    }
}
