<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\JournalEntry;
use App\Models\Notification;
use App\Models\User;
use App\Models\Company;
use App\Services\AbsorptionService;
use App\Support\ApiResponse;
use App\Support\InternshipStatuses;
use Illuminate\Http\Request;

class CoordinatorController extends Controller
{
    /** GET /api/v1/coordinator/dashboard */
    public function dashboard(Request $request)
    {
        return $this->monitoring($request);
    }

    /** GET /api/v1/coordinator/monitoring */
    public function monitoring(Request $request)
    {
        $term = config('interntrack.current_term');
        $live = InternshipStatuses::liveMonitoring();

        $activeInterns    = Internship::whereIn('status', $live)->count();
        $pendingPlacement = Internship::where('status', 'pending_placement')->count();
        $avgHoursCompletion = Internship::whereIn('status', $live)
            ->selectRaw('AVG(total_hours_rendered / NULLIF(target_hours, 0) * 100) as avg_pct')
            ->value('avg_pct') ?? 0;

        // At-risk = below 30% completion
        $atRisk = Internship::whereIn('status', $live)
            ->where('target_hours', '>', 0)
            ->whereRaw('(total_hours_rendered / target_hours) < 0.30')
            ->count();

        $fullyCompleted = Internship::where('status', 'completed')->count();

        $internships = Internship::whereIn('status', array_merge(['pending_placement'], $live))
            ->with([
                'student.studentProfile',
                'supervisor.supervisorProfile',
                'company',
                'journals' => fn($q) => $q->latest('date')->limit(1),
                'documents',
            ])
            ->paginate(15);

        $rows = $internships->through(function ($i) {
            $profile     = $i->student?->studentProfile;
            $supProfile  = $i->supervisor?->supervisorProfile;
            $lastJournal = $i->journals->first();
            $docsApproved= $i->documents->where('status', 'approved')->count();
            $docsTotal   = 13;

            return [
                'internship_id'      => $i->id,
                'student_name'       => $profile ? trim("{$profile->first_name} {$profile->last_name}") : '—',
                'student_number'     => $profile?->student_number ?? '—',
                'program'            => $profile?->course_name ?? '—',
                'status'             => $i->status,
                'supervisor_name'    => $supProfile ? trim("{$supProfile->first_name} {$supProfile->last_name}") : 'Not Assigned',
                'company'            => $i->company?->company_name ?? 'Not Assigned',
                'last_journal_date'  => $lastJournal?->date?->toDateString(),
                'journal_status'     => $lastJournal?->status ?? 'none',
                'docs_approved'      => $docsApproved,
                'docs_total'         => $docsTotal,
                'docs_label'         => $docsApproved < $docsTotal ? ($docsTotal - $docsApproved) . ' Missing' : 'Complete',
                'docs_status'        => $docsApproved >= $docsTotal ? 'complete' : 'missing',
                'progress_percent'   => (float) ($i->target_hours > 0 ? round($i->total_hours_rendered / $i->target_hours * 100, 1) : 0),
                'hours_rendered'     => (float) $i->total_hours_rendered,
                'target_hours'       => $i->target_hours,
            ];
        });

        return response()->json([
            'stats' => [
                'active_interns'        => $activeInterns,
                'pending_placement'     => $pendingPlacement,
                'avg_hours_completion'  => round($avgHoursCompletion, 1),
                'at_risk_students'      => $atRisk,
                'fully_completed'       => $fullyCompleted,
            ],
        ] + ApiResponse::list($rows)->getData(true));
    }

    /** GET /api/v1/coordinator/announcements */
    public function announcements(Request $request)
    {
        $items = Announcement::with('author')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate(15);
        return ApiResponse::list($items);
    }

    /** POST /api/v1/coordinator/announcements */
    public function createAnnouncement(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'content'     => 'required|string',
            'target_role' => 'required|string|in:all,student,supervisor,faculty,coordinator,director',
            'is_pinned'   => 'boolean',
            'expires_at'  => 'nullable|date|after:now',
        ]);

        $announcement = Announcement::create([
            'created_by'  => $request->user()->id,
            'title'       => $request->title,
            'content'     => $request->content,
            'target_role' => $request->target_role,
            'is_pinned'   => $request->boolean('is_pinned', false),
            'expires_at'  => $request->expires_at,
        ]);

        audit_log($request->user()->id, 'create_announcement', ['title' => $request->title]);

        return response()->json(['message' => 'Announcement created.', 'announcement' => $announcement], 201);
    }

    /** PUT /api/v1/coordinator/announcements/{id} */
    public function updateAnnouncement(Request $request, int $id)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'content'     => 'required|string',
            'target_role' => 'required|in:all,student,supervisor,faculty,coordinator,director',
            'is_pinned'   => 'boolean',
            'expires_at'  => 'nullable|date',
        ]);

        $announcement = Announcement::findOrFail($id);
        $announcement->update($request->only(['title', 'content', 'target_role', 'is_pinned', 'expires_at']));

        return response()->json(['message' => 'Announcement updated.', 'announcement' => $announcement]);
    }

    /** DELETE /api/v1/coordinator/announcements/{id} */
    public function deleteAnnouncement(Request $request, int $id)
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->delete();
        audit_log($request->user()->id, 'delete_announcement', ['id' => $id]);
        return response()->json(['message' => 'Announcement deleted.']);
    }

    /** GET /api/v1/coordinator/documents — coordinator-stage queue only */
    public function documents(Request $request)
    {
        $docs = Document::where('current_stage', 'coordinator')
            ->whereIn('status', ['pending_review', 'resubmitted', 'under_review'])
            ->with(['internship.student.studentProfile', 'reviews'])
            ->orderBy('submitted_at')
            ->paginate(25);
        return ApiResponse::list($docs);
    }

    /** PATCH /api/v1/coordinator/documents/{id}/approve — advances to faculty stage */
    public function approveDocument(Request $request, int $id)
    {
        $doc = Document::where('current_stage', 'coordinator')
            ->whereIn('status', ['pending_review', 'resubmitted', 'under_review'])
            ->findOrFail($id);

        $from = $doc->status;
        $doc->update([
            'status' => 'pending_faculty',
            'current_stage' => 'faculty',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'remarks' => $request->remarks,
        ]);

        \App\Models\DocumentReview::create([
            'document_id' => $doc->id,
            'stage' => 'coordinator',
            'action' => 'approve',
            'from_status' => $from,
            'to_status' => 'pending_faculty',
            'remarks' => $request->remarks,
            'reviewed_by' => $request->user()->id,
        ]);

        $studentId = $doc->internship?->student_id;
        if ($studentId) {
            Notification::notify(
                $studentId,
                'document_coordinator_approved',
                'Document cleared by Coordinator',
                "Your {$doc->document_type} passed coordinator review and awaits faculty verification.",
                '/student/documents'
            );
        }

        $facultyId = $doc->internship?->faculty_id;
        if ($facultyId) {
            Notification::notify(
                $facultyId,
                'document_pending_faculty',
                'Document awaiting your verification',
                "{$doc->document_type} is ready for faculty verification.",
                '/faculty/documents'
            );
        }

        audit_log($request->user()->id, 'approve_document_coordinator', ['document_id' => $id]);
        return response()->json(['message' => 'Document forwarded to faculty verification.', 'document' => $doc]);
    }

    /** PATCH /api/v1/coordinator/documents/{id}/reject */
    public function rejectDocument(Request $request, int $id)
    {
        $request->validate(['remarks' => 'required|string|max:500']);

        $doc = Document::where('current_stage', 'coordinator')
            ->whereIn('status', ['pending_review', 'resubmitted', 'under_review'])
            ->findOrFail($id);

        $from = $doc->status;
        $doc->update([
            'status' => 'rejected',
            'current_stage' => 'done',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'remarks' => $request->remarks,
        ]);

        \App\Models\DocumentReview::create([
            'document_id' => $doc->id,
            'stage' => 'coordinator',
            'action' => 'reject',
            'from_status' => $from,
            'to_status' => 'rejected',
            'remarks' => $request->remarks,
            'reviewed_by' => $request->user()->id,
        ]);

        $studentId = $doc->internship?->student_id;
        if ($studentId) {
            Notification::notify(
                $studentId,
                'document_rejected',
                'Document Rejected',
                "Your {$doc->document_type} was rejected by the coordinator: {$request->remarks}",
                '/student/documents',
                ['remarks' => $request->remarks, 'document_type' => $doc->document_type]
            );
        }

        audit_log($request->user()->id, 'reject_document', ['document_id' => $id]);
        return response()->json(['message' => 'Document rejected with remarks.', 'document' => $doc]);
    }

    /** PATCH /api/v1/coordinator/documents/bulk-approve — forward each to faculty stage */
    public function bulkApproveDocuments(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);

        $docs = Document::whereIn('id', $request->ids)
            ->where('current_stage', 'coordinator')
            ->whereIn('status', ['pending_review', 'resubmitted', 'under_review'])
            ->get();

        foreach ($docs as $doc) {
            $from = $doc->status;
            $doc->update([
                'status' => 'pending_faculty',
                'current_stage' => 'faculty',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            \App\Models\DocumentReview::create([
                'document_id' => $doc->id,
                'stage' => 'coordinator',
                'action' => 'approve',
                'from_status' => $from,
                'to_status' => 'pending_faculty',
                'reviewed_by' => $request->user()->id,
            ]);
            if ($doc->internship?->student_id) {
                Notification::notify(
                    $doc->internship->student_id,
                    'document_coordinator_approved',
                    'Document cleared by Coordinator',
                    "Your {$doc->document_type} awaits faculty verification.",
                    '/student/documents'
                );
            }
        }

        audit_log($request->user()->id, 'bulk_approve_documents', ['document_ids' => $docs->pluck('id')]);

        return response()->json(['message' => "{$docs->count()} document(s) forwarded to faculty.", 'approved_count' => $docs->count()]);
    }

    /** PATCH /api/v1/coordinator/documents/bulk-reject */
    public function bulkRejectDocuments(Request $request)
    {
        $request->validate([
            'ids'     => 'required|array|min:1',
            'ids.*'   => 'integer',
            'remarks' => 'required|string|max:500',
        ]);

        $docs = Document::whereIn('id', $request->ids)
            ->where('current_stage', 'coordinator')
            ->get();

        foreach ($docs as $doc) {
            $from = $doc->status;
            $doc->update([
                'status' => 'rejected',
                'current_stage' => 'done',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'remarks' => $request->remarks,
            ]);
            \App\Models\DocumentReview::create([
                'document_id' => $doc->id,
                'stage' => 'coordinator',
                'action' => 'reject',
                'from_status' => $from,
                'to_status' => 'rejected',
                'remarks' => $request->remarks,
                'reviewed_by' => $request->user()->id,
            ]);

            $studentId = $doc->internship?->student_id;
            if ($studentId) {
                Notification::notify(
                    $studentId,
                    'document_rejected',
                    'Document Rejected',
                    "Your {$doc->document_type} was rejected: {$request->remarks}",
                    '/student/documents',
                    ['remarks' => $request->remarks, 'document_type' => $doc->document_type]
                );
            }
        }

        audit_log($request->user()->id, 'bulk_reject_documents', ['document_ids' => $docs->pluck('id')]);

        return response()->json(['message' => "{$docs->count()} document(s) rejected.", 'rejected_count' => $docs->count()]);
    }

    /** GET /api/v1/coordinator/logbook */
    public function logbook(Request $request)
    {
        $journals = JournalEntry::where('status', 'submitted')
            ->with(['internship.student.studentProfile'])
            ->orderByDesc('date')
            ->paginate(25);
        return ApiResponse::list($journals);
    }

    /** PATCH /api/v1/coordinator/logbook/{id}/review */
    public function reviewLogbook(Request $request, int $id)
    {
        $request->validate([
            'action'   => 'required|in:approved,needs_revision',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $journal = JournalEntry::findOrFail($id);
        $journal->update([
            'status'                 => $request->action,
            'faculty_feedback'       => $request->feedback,
            'faculty_reviewed_by'    => $request->user()->id,
            'faculty_reviewed_at'    => now(),
        ]);

        return response()->json(['message' => 'Journal ' . $request->action . '.', 'journal' => $journal]);
    }

    /** GET /api/v1/coordinator/records */
    public function records(Request $request)
    {
        $students = User::where('role', 'student')
            ->with([
                'studentProfile',
                'activeInternship.company',
                'internshipsAsStudent' => fn($q) => $q->withCount(['attendance as validated_days' => fn($a) => $a->where('status', 'validated')]),
            ])
            ->paginate(20);

        return ApiResponse::list($students);
    }

    /** GET /api/v1/coordinator/placement-options */
    public function placementOptions(Request $request)
    {
        $companies = Company::where('moa_status', 'active')->get();
        $faculty = User::where('role', 'faculty')->with('facultyProfile')->get();
        $supervisors = User::where('role', 'supervisor')->with('supervisorProfile')->get();

        return response()->json([
            'companies' => $companies,
            'faculty' => $faculty,
            'supervisors' => $supervisors,
        ]);
    }

    /** POST /api/v1/coordinator/internships/{id}/place */
    public function assignPlacement(Request $request, $id)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'faculty_id' => 'required|exists:users,id',
            'supervisor_id' => 'required|exists:users,id',
        ]);

        $internship = Internship::with('student.studentProfile')->findOrFail($id);

        if ($internship->status !== 'pending_placement') {
            return response()->json(['message' => 'Student is already placed or cannot be placed at this time.'], 422);
        }

        $profile = $internship->student?->studentProfile;
        $program = $internship->program
            ?: ($profile?->program ?: $profile?->course_name);

        $internship->update([
            'company_id'     => $request->company_id,
            'faculty_id'     => $request->faculty_id,
            'supervisor_id'  => $request->supervisor_id,
            'coordinator_id' => $request->user()->id, // same assignment row that stores company_id
            'program'        => $program,
            'status'         => 'active',
            'status_reason'  => 'Authorized deployment / placement by coordinator.',
            'start_date'     => now(),
        ]);

        \App\Models\InternshipStatusHistory::create([
            'internship_id' => $internship->id,
            'from_status' => 'pending_placement',
            'to_status' => 'active',
            'reason' => 'Authorized deployment / placement by coordinator.',
            'changed_by' => $request->user()->id,
        ]);

        Notification::notify(
            $internship->student_id,
            'placement_assigned',
            'Internship Placement Assigned',
            'You have been officially deployed. You may now start logging your hours.',
            '/student/dashboard'
        );

        audit_log($request->user()->id, 'assign_placement', ['internship_id' => $internship->id]);

        return response()->json(['message' => 'Placement assigned successfully.', 'internship' => $internship]);
    }

    /** GET /api/v1/coordinator/reports/overview */
    public function reportsOverview(Request $request)
    {
        $total    = Internship::count();
        $ongoing  = Internship::whereIn('status', ['ongoing', 'active'])->count();
        $done     = Internship::where('status', 'completed')->count();
        $docsOk   = Document::where('status', 'approved')->count();
        $docsPending = Document::where('status', 'pending_review')->count();
        $journalsSubmitted = JournalEntry::where('status', 'submitted')->count();
        $attendancePending = \App\Models\AttendanceLog::where('status', 'pending')->count();

        return response()->json([
            'internships'        => compact('total', 'ongoing', 'done'),
            'documents'          => ['approved' => $docsOk, 'pending' => $docsPending],
            'journals_submitted' => $journalsSubmitted,
            'attendance_pending' => $attendancePending,
            'absorption'         => AbsorptionService::analytics(),
        ]);
    }

    /** GET /api/v1/coordinator/absorption — completed internships needing / with outcomes */
    public function absorptionList(Request $request)
    {
        $items = Internship::where('status', 'completed')
            ->with(['student.studentProfile', 'company', 'supervisor.supervisorProfile'])
            ->orderByDesc('end_date')
            ->get();

        return response()->json(['internships' => $items]);
    }

    /** PATCH /api/v1/coordinator/internships/{id}/absorption */
    public function recordAbsorption(Request $request, int $id)
    {
        $request->validate([
            'absorption_status' => 'required|in:absorbed,not_hired',
            'absorbed_at'       => 'nullable|date',
            'job_title'         => 'nullable|string|max:255',
            'absorption_notes'  => 'nullable|string|max:2000',
        ]);

        $internship = Internship::findOrFail($id);
        $updated = AbsorptionService::recordOutcome(
            $internship,
            $request->user(),
            'coordinator',
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

    /** GET /api/v1/coordinator/reports/student-summary */
    public function reportStudentSummary(Request $request)
    {
        $students = Internship::with(['student.studentProfile', 'company'])
            ->selectRaw('
                internships.*,
                (SELECT COUNT(*) FROM attendance_logs WHERE internship_id = internships.id AND status = "validated") as validated_days,
                (SELECT COUNT(*) FROM journal_entries WHERE internship_id = internships.id AND status = "approved") as approved_journals,
                (SELECT COUNT(*) FROM documents WHERE internship_id = internships.id AND status = "approved") as approved_docs
            ')
            ->orderBy('status')
            ->get()
            ->map(fn($i) => [
                'student_name'     => optional($i->student?->studentProfile)->first_name . ' ' . optional($i->student?->studentProfile)->last_name,
                'student_number'   => $i->student?->username,
                'program'          => $i->student?->studentProfile?->course_name ?? $i->program ?? '—',
                'company'          => $i->company?->company_name ?? '—',
                'status'           => $i->status,
                'hours_rendered'   => (float) $i->total_hours_rendered,
                'target_hours'     => $i->target_hours,
                'progress_pct'     => $i->target_hours > 0 ? round($i->total_hours_rendered / $i->target_hours * 100, 1) : 0,
                'validated_days'   => $i->validated_days,
                'approved_journals'=> $i->approved_journals,
                'approved_docs'    => $i->approved_docs,
                'start_date'       => $i->start_date?->toDateString(),
                'end_date'         => $i->end_date?->toDateString(),
                'final_grade'      => $i->final_grade,
            ]);

        return response()->json(['students' => $students, 'generated_at' => now()->toDateTimeString()]);
    }

    /** GET /api/v1/coordinator/reports/compliance */
    public function reportCompliance(Request $request)
    {
        $internships = Internship::with(['student.studentProfile', 'documents'])->get();

        $requiredTypes = [
            'Endorsement Letter', 'Application Form', 'MOA Document',
            'Acceptance Letter', 'Medical Certificate', 'Parent Consent',
            'Training Plan', 'Midterm Evaluation', 'Final Report',
        ];
        $requiredCount = count($requiredTypes);

        $rows = $internships->map(fn($i) => [
            'student_name'    => trim(optional($i->student?->studentProfile)->first_name . ' ' . optional($i->student?->studentProfile)->last_name),
            'program'         => $i->student?->studentProfile?->course_name ?? '—',
            'approved_docs'   => $i->documents->where('status', 'approved')->count(),
            'required_docs'   => $requiredCount,
            'compliance_pct'  => round($i->documents->where('status', 'approved')->count() / $requiredCount * 100),
            'missing_docs'    => collect($requiredTypes)->diff($i->documents->where('status', 'approved')->pluck('document_type'))->values(),
        ]);

        return response()->json(['rows' => $rows, 'required_types' => $requiredTypes, 'generated_at' => now()->toDateTimeString()]);
    }

    /** GET /api/v1/coordinator/reports/performance */
    public function reportPerformance(Request $request)
    {
        $byProgram = Internship::with('student.studentProfile')
            ->get()
            ->groupBy(function (Internship $i) {
                $p = $i->student?->studentProfile;
                foreach ([$i->program, $p?->program, $p?->course_name] as $value) {
                    $value = trim((string) $value);
                    if ($value !== '') {
                        return $value;
                    }
                }
                return 'Unknown';
            })
            ->map(fn ($rows, $program) => [
                'program' => $program,
                'total' => $rows->count(),
                'completed' => $rows->where('status', 'completed')->count(),
                'avg_hours' => round((float) $rows->avg('total_hours_rendered'), 2),
                'avg_grade' => round((float) $rows->avg('final_grade'), 2),
            ])
            ->sortBy('program')
            ->values();

        $evalAvg = \App\Models\Evaluation::selectRaw('
            evaluator_type,
            AVG(technical_skills) as avg_technical,
            AVG(communication_skills) as avg_communication,
            AVG(teamwork) as avg_teamwork,
            AVG(initiative) as avg_initiative,
            AVG(work_ethics) as avg_work_ethics,
            AVG(average_score) as avg_overall
        ')
        ->groupBy('evaluator_type')
        ->get();

        return response()->json([
            'by_program'   => $byProgram,
            'eval_averages'=> $evalAvg,
            'generated_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * GET /api/v1/coordinator/evaluations
     * Oversight of faculty + industry supervisor evaluations (read-only).
     */
    public function evaluations(Request $request)
    {
        $evals = \App\Models\Evaluation::with([
            'internship.student.studentProfile',
            'internship.company',
            'evaluator.supervisorProfile',
            'evaluator.facultyProfile',
        ])
            ->orderByDesc('submitted_at')
            ->paginate(40);

        return ApiResponse::list($evals);
    }

    /** GET /api/v1/coordinator/supervisor-feedback — narrative feedback from industry supervisors */
    public function supervisorFeedback(Request $request)
    {
        $journals = JournalEntry::whereNotNull('supervisor_feedback')
            ->with(['internship.student.studentProfile', 'internship.company', 'internship.supervisor.supervisorProfile'])
            ->orderByDesc('supervisor_reviewed_at')
            ->paginate(40);

        return ApiResponse::list($journals);
    }
}
