<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Models\Evaluation;
use App\Models\Notification;
use App\Models\User;
use App\Services\AbsorptionService;
use App\Support\ApiResponse;
use App\Support\InternshipStatuses;
use Illuminate\Http\Request;

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

        $pendingJournals = $internshipIds->isEmpty()
            ? 0
            : \App\Models\JournalEntry::whereIn('internship_id', $internshipIds)
                ->where('status', 'submitted')
                ->count();

        $pendingEvals = $internships->whereNotIn('id',
            Evaluation::where('evaluator_type', 'supervisor')->pluck('internship_id')->toArray()
        )->count();

        // Get recent activity (last 5 validated attendance or approved journals)
        $recentAttendance = \App\Models\AttendanceLog::whereIn('internship_id', $internshipIds)
            ->where('status', 'validated')
            ->with(['internship.student.studentProfile'])
            ->latest('validated_at')
            ->limit(5)
            ->get()
            ->map(fn($log) => [
                'type' => 'attendance',
                'student' => $log->internship->student->studentProfile ? trim("{$log->internship->student->studentProfile->first_name} {$log->internship->student->studentProfile->last_name}") : $log->internship->student->username,
                'date' => $log->date,
                'hours' => $log->hours_rendered,
                'action_at' => $log->validated_at,
            ]);

        $recentJournals = \App\Models\JournalEntry::whereIn('internship_id', $internshipIds)
            ->where('status', 'approved')
            ->with(['internship.student.studentProfile'])
            ->latest('supervisor_reviewed_at')
            ->limit(5)
            ->get()
            ->map(fn($j) => [
                'type' => 'journal',
                'student' => $j->internship->student->studentProfile ? trim("{$j->internship->student->studentProfile->first_name} {$j->internship->student->studentProfile->last_name}") : $j->internship->student->username,
                'week' => $j->week_number ?? $j->entry_number,
                'action_at' => $j->supervisor_reviewed_at,
            ]);

        $recentActivity = $recentAttendance->concat($recentJournals)
            ->sortByDesc('action_at')
            ->take(5)
            ->values();

        return response()->json([
            'stats' => [
                'assigned_interns'    => $internships->count(),
                'pending_validations' => $pendingAttendance,
                'journal_reviews'     => $pendingJournals,
                'pending_evaluations' => $pendingEvals,
            ],
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
                        'course_name' => $p->course_name,
                        'program' => $p->program,
                    ] : null,
                ],
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
                'hours_declared' => 0,
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
                '/student/logbook'
            );
        }

        // Coordinators can see this via evaluations/feedback oversight
        foreach (User::where('role', 'coordinator')->where('is_active', true)->pluck('id') as $coordId) {
            Notification::notify(
                $coordId,
                'supervisor_feedback_submitted',
                'Supervisor feedback submitted',
                'Industry supervisor submitted feedback for an assigned intern.',
                '/coordinator/reports'
            );
        }

        audit_log($request->user()->id, 'submit_supervisor_feedback', ['internship_id' => $internshipId]);

        return response()->json([
            'message' => 'Feedback submitted.',
            'journal' => $journal->fresh(),
        ]);
    }

    /** GET /api/v1/supervisor/attendance */
    public function attendance(Request $request)
    {
        $internshipIds = Internship::where('supervisor_id', $request->user()->id)->pluck('id');

        $logs = \App\Models\AttendanceLog::whereIn('internship_id', $internshipIds)
            ->with(['internship.student.studentProfile'])
            ->where('status', 'pending')
            ->orderByDesc('date')
            ->paginate(25);

        return ApiResponse::list($logs);
    }

    /** PATCH /api/v1/supervisor/attendance/{id}/validate */
    public function validateAttendance(Request $request, int $id)
    {
        $request->validate([
            'action'  => 'required|in:validated,rejected',
            'remarks' => 'nullable|string|max:500',
        ]);

        $log = \App\Models\AttendanceLog::whereHas('internship', fn($q) => $q->where('supervisor_id', $request->user()->id))
            ->findOrFail($id);

        $log->update([
            'status'       => $request->action,
            'remarks'      => $request->remarks,
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
        ]);

        // Refresh internship total hours
        $log->internship->refreshTotalHours();

        audit_log($request->user()->id, 'validate_attendance', ['log_id' => $id, 'action' => $request->action]);

        return response()->json(['message' => 'Attendance ' . $request->action . ' successfully.', 'record' => $log]);
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
            ->whereHas('internship', fn($q) => $q->where('supervisor_id', $request->user()->id))
            ->where('status', 'pending')
            ->get();

        $affectedInternships = collect();

        foreach ($logs as $log) {
            $log->update([
                'status'       => $request->action,
                'remarks'      => $request->remarks,
                'validated_by' => $request->user()->id,
                'validated_at' => now(),
            ]);
            $affectedInternships->push($log->internship);
        }

        // Recalculate totals for every distinct internship touched by this batch
        $affectedInternships->unique('id')->each(fn($internship) => $internship?->refreshTotalHours());

        audit_log($request->user()->id, 'bulk_validate_attendance', ['log_ids' => $logs->pluck('id'), 'action' => $request->action]);

        return response()->json([
            'message'         => "{$logs->count()} attendance record(s) {$request->action}.",
            'processed_count' => $logs->count(),
        ]);
    }

    /** GET /api/v1/supervisor/journals */
    public function journals(Request $request)
    {
        $internshipIds = Internship::where('supervisor_id', $request->user()->id)->pluck('id');
        $journals = \App\Models\JournalEntry::whereIn('internship_id', $internshipIds)
            ->where('status', 'submitted')
            ->with(['internship.student.studentProfile'])
            ->orderByDesc('date')
            ->paginate(25);

        return ApiResponse::list($journals);
    }

    /** PATCH /api/v1/supervisor/journals/{id}/review */
    public function reviewJournal(Request $request, int $id)
    {
        $request->validate([
            'action'   => 'required|in:approved,needs_revision',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $journal = \App\Models\JournalEntry::whereHas('internship', fn($q) => $q->where('supervisor_id', $request->user()->id))
            ->findOrFail($id);

        $journal->update([
            'status'                  => $request->action,
            'supervisor_feedback'     => $request->feedback,
            'supervisor_reviewed_by'  => $request->user()->id,
            'supervisor_reviewed_at'  => now(),
        ]);

        // Notify student of journal review outcome
        $studentId = $journal->internship?->student_id;
        if ($studentId) {
            $weekLabel = 'Week ' . ($journal->week_number ?? $journal->entry_number ?? '—');
            if ($request->action === 'approved') {
                Notification::notify(
                    $studentId,
                    'journal_reviewed',
                    "Journal Approved ✅",
                    "Your {$weekLabel} journal was approved by your company supervisor.",
                    '/student/logbook'
                );
            } else {
                Notification::notify(
                    $studentId,
                    'journal_reviewed',
                    "Journal Needs Revision 🔄",
                    "Your {$weekLabel} journal needs revision: " . ($request->feedback ?? 'Please check your entry.'),
                    '/student/logbook',
                    ['feedback' => $request->feedback]
                );
            }
        }

        audit_log($request->user()->id, 'review_journal', ['journal_id' => $id, 'action' => $request->action]);

        return response()->json(['message' => 'Journal ' . $request->action . ' successfully.', 'journal' => $journal]);
    }

    /** GET /api/v1/supervisor/evaluations */
    public function evaluations(Request $request)
    {
        $internshipIds = Internship::where('supervisor_id', $request->user()->id)
            ->whereIn('status', ['ongoing', 'active', 'for_evaluation', 'completed', 'placed'])
            ->pluck('id');

        $evaluations = Evaluation::whereIn('internship_id', $internshipIds)
            ->where('evaluator_type', 'supervisor')
            ->with('internship.student.studentProfile')
            ->get();

        $pending = Internship::whereIn('id', $internshipIds)
            ->whereNotIn('id', $evaluations->pluck('internship_id'))
            ->with('student.studentProfile')
            ->get();

        return ApiResponse::groups(['completed' => $evaluations, 'pending' => $pending]);
    }

    /** POST /api/v1/supervisor/evaluations/{internshipId} */
    public function submitEvaluation(Request $request, int $internshipId)
    {
        $request->validate([
            'evaluation_period'     => 'required|in:midterm,final',
            'technical_skills'      => 'required|numeric|min:1|max:5',
            'communication_skills'  => 'required|numeric|min:1|max:5',
            'teamwork'              => 'required|numeric|min:1|max:5',
            'initiative'            => 'required|numeric|min:1|max:5',
            'work_ethics'           => 'required|numeric|min:1|max:5',
            'attendance_punctuality'=> 'required|numeric|min:1|max:5',
            'adaptability'          => 'required|numeric|min:1|max:5',
            'problem_solving'       => 'required|numeric|min:1|max:5',
            'strengths'             => 'nullable|string',
            'areas_for_improvement' => 'nullable|string',
            'general_comments'      => 'nullable|string',
        ]);

        $internship = Internship::where('supervisor_id', $request->user()->id)
            ->with(['student.studentProfile', 'coordinator'])
            ->findOrFail($internshipId);

        $eval = Evaluation::updateOrCreate(
            [
                'internship_id' => $internship->id,
                'evaluator_type' => 'supervisor',
                'evaluation_period' => $request->evaluation_period,
            ],
            array_merge($request->only([
                'technical_skills', 'communication_skills', 'teamwork',
                'initiative', 'work_ethics', 'attendance_punctuality', 'adaptability',
                'problem_solving', 'strengths', 'areas_for_improvement', 'general_comments',
            ]), [
                'evaluated_by' => $request->user()->id,
                'submitted_at' => now(),
            ])
        );
        $eval->computeScores();
        $eval->save();

        $studentName = $internship->student?->studentProfile
            ? trim($internship->student->studentProfile->first_name.' '.$internship->student->studentProfile->last_name)
            : ($internship->student?->username ?? 'Intern');

        $notifyIds = User::whereIn('role', ['coordinator', 'director'])
            ->where('is_active', true)
            ->pluck('id');
        if ($internship->coordinator_id) {
            $notifyIds->push($internship->coordinator_id);
        }
        foreach ($notifyIds->unique() as $uid) {
            Notification::notify(
                $uid,
                'supervisor_evaluation_submitted',
                'Industry supervisor evaluation submitted',
                "{$studentName} — {$request->evaluation_period} evaluation (avg {$eval->average_score}).",
                '/coordinator/evaluations'
            );
        }

        audit_log($request->user()->id, 'submit_evaluation', [
            'internship_id' => $internshipId,
            'period' => $request->evaluation_period,
            'evaluation_id' => $eval->id,
        ]);

        return response()->json(['message' => 'Evaluation submitted successfully.', 'evaluation' => $eval], 201);
    }

    /** GET /api/v1/supervisor/notifications */
    public function notifications(Request $request)
    {
        $internshipIds = Internship::where('supervisor_id', $request->user()->id)->pluck('id');

        $pendingAttendance = \App\Models\AttendanceLog::whereIn('internship_id', $internshipIds)->where('status', 'pending')->count();
        $pendingJournals   = \App\Models\JournalEntry::whereIn('internship_id', $internshipIds)->where('status', 'submitted')->count();

        $notifications = collect();
        if ($pendingAttendance > 0) {
            $notifications->push(['type' => 'attendance', 'message' => "{$pendingAttendance} attendance record(s) pending validation.", 'count' => $pendingAttendance]);
        }
        if ($pendingJournals > 0) {
            $notifications->push(['type' => 'journal', 'message' => "{$pendingJournals} journal entry(s) awaiting your review.", 'count' => $pendingJournals]);
        }

        return ApiResponse::list($notifications);
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

    /** PATCH /api/v1/supervisor/internships/{id}/absorption */
    public function recordAbsorption(Request $request, int $id)
    {
        $request->validate([
            'absorption_status' => 'required|in:absorbed,not_hired',
            'absorbed_at'       => 'nullable|date',
            'job_title'         => 'nullable|string|max:255',
            'absorption_notes'  => 'nullable|string|max:2000',
        ]);

        $internship = Internship::where('supervisor_id', $request->user()->id)->findOrFail($id);

        $updated = AbsorptionService::recordOutcome(
            $internship,
            $request->user(),
            'supervisor',
            $request->absorption_status,
            $request->absorbed_at,
            $request->job_title,
            $request->absorption_notes,
        );

        audit_log($request->user()->id, 'record_absorption', [
            'internship_id' => $id,
            'status'        => $request->absorption_status,
        ]);

        return response()->json(['message' => 'Absorption outcome saved.', 'internship' => $updated]);
    }
}
