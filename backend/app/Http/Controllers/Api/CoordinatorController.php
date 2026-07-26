<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Models\Document;
use App\Models\JournalEntry;
use App\Models\Notification;
use App\Models\User;
use App\Models\Company;
use App\Services\AbsorptionService;
use App\Support\ApiResponse;
use App\Support\InternshipStatuses;
use App\Support\SignatureCapture;
use Illuminate\Http\Request;

class CoordinatorController extends Controller
{
    /**
     * Light ownership check using internship.coordinator_id.
     * Null coordinator_id = unclaimed (any coordinator may act / claim).
     */
    private function assertCoordinatorOwns(?Internship $internship, int $actorId): void
    {
        if (!$internship) {
            abort(404, 'Internship not found for this record.');
        }
        if ($internship->coordinator_id !== null && (int) $internship->coordinator_id !== $actorId) {
            abort(403, 'Forbidden. You are not the assigned coordinator for this internship.');
        }
    }

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
        $pendingPlacement = User::where('role', 'student')->where('is_active', true)
            ->where(function ($q) {
                $q->whereDoesntHave('activeInternship')
                  ->orWhereHas('activeInternship', fn($i) => $i->where('status', 'pending_placement'));
            })->count();
            
        $avgHoursCompletion = Internship::whereIn('status', $live)
            ->selectRaw('AVG(total_hours_rendered / NULLIF(target_hours, 0) * 100) as avg_pct')
            ->value('avg_pct') ?? 0;

        // At-risk = below 30% completion
        $atRisk = Internship::whereIn('status', $live)
            ->where('target_hours', '>', 0)
            ->whereRaw('(total_hours_rendered / target_hours) < 0.30')
            ->count();

        $fullyCompleted = Internship::where('status', 'completed')->count();

        $query = User::where('role', 'student')->where('is_active', true)
            ->with([
                'studentProfile',
                'activeInternship.supervisor.supervisorProfile',
                'activeInternship.company',
                'activeInternship.faculty.facultyProfile',
                'activeInternship.journals' => fn($q) => $q->latest('date')->limit(1),
                'activeInternship.documents',
            ]);

        $students = $query->paginate(25);

        // Preload faculty section assignments
        $sections = $students->pluck('studentProfile.section')->filter()->unique();
        $facultyAssignments = \App\Models\FacultySectionAssignment::whereIn('section', $sections)
            ->with('faculty.facultyProfile')
            ->get()
            ->keyBy('section');

        $rows = $students->through(function ($student) use ($facultyAssignments) {
            $profile     = $student->studentProfile;
            $i           = $student->activeInternship;
            $supProfile  = $i?->supervisor?->supervisorProfile;
            $lastJournal = $i?->journals->first();
            $docsApproved= $i?->documents->where('status', 'approved')->count() ?? 0;
            $docsTotal   = \App\Support\RequiredDocuments::count();

            // Resolve faculty
            $facultyName = 'Not Assigned';
            if ($i && $i->faculty && $i->faculty->facultyProfile) {
                $fp = $i->faculty->facultyProfile;
                $facultyName = trim("{$fp->first_name} {$fp->last_name}");
            } elseif ($profile && $profile->section) {
                $assignment = $facultyAssignments->get($profile->section);
                if ($assignment && $assignment->faculty && $assignment->faculty->facultyProfile) {
                    $fp = $assignment->faculty->facultyProfile;
                    $facultyName = trim("{$fp->first_name} {$fp->last_name}");
                }
            }

            return [
                'user_id'            => $student->id,
                'internship_id'      => $i?->id,
                'student_name'       => $profile ? trim("{$profile->first_name} {$profile->last_name}") : $student->username,
                'student_number'     => $profile?->student_number ?? '—',
                'program'            => $profile?->course_name ?? $profile?->program ?? '—',
                'section'            => $profile?->section ?? '—',
                'sex'                => $student->sex ?? $profile?->sex ?? '—',
                'faculty_name'       => $facultyName,
                'status'             => $i?->status ?? 'unplaced',
                'supervisor_name'    => $supProfile ? trim("{$supProfile->first_name} {$supProfile->last_name}") : 'Not Assigned',
                'company'            => $i?->company?->company_name ?? 'Not Assigned',
                'last_journal_date'  => $lastJournal?->date?->toDateString(),
                'journal_status'     => $lastJournal?->status ?? 'none',
                'docs_approved'      => $docsApproved,
                'docs_total'         => $docsTotal,
                'docs_label'         => $docsTotal > 0 && $docsApproved < $docsTotal ? ($docsTotal - $docsApproved) . ' Missing' : 'Complete',
                'docs_status'        => $docsTotal > 0 && $docsApproved >= $docsTotal ? 'complete' : 'missing',
                'progress_percent'   => (float) ($i && $i->target_hours > 0 ? round($i->total_hours_rendered / $i->target_hours * 100, 1) : 0),
                'hours_rendered'     => (float) ($i?->total_hours_rendered ?? 0),
                'target_hours'       => $i?->target_hours ?? 0,
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
            ->with('internship')
            ->findOrFail($id);

        $this->assertCoordinatorOwns($doc->internship, $request->user()->id);

        $sig = SignatureCapture::fromRequest($request, 'signatures/documents');

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
            'signer_name' => $sig['signer_name'],
            'signature_path' => $sig['signature_path'],
            'signed_at' => $sig['signed_at'],
        ]);

        $studentId = $doc->internship?->student_id;
        if ($studentId) {
            Notification::notify(
                $studentId,
                'document_coordinator_approved',
                'Document cleared by Coordinator',
                "Your {$doc->document_type} passed coordinator review and awaits faculty verification.",
                '/student/documents',
                ['document_id' => $doc->id, 'document_type' => $doc->document_type]
            );
        }

        $facultyId = $doc->internship?->faculty_id;
        if ($facultyId) {
            Notification::notify(
                $facultyId,
                'document_pending_faculty',
                'Document awaiting your verification',
                "{$doc->document_type} is ready for faculty verification.",
                '/faculty/documents',
                ['document_id' => $doc->id, 'document_type' => $doc->document_type]
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
            ->with('internship')
            ->findOrFail($id);

        $this->assertCoordinatorOwns($doc->internship, $request->user()->id);

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
                ['document_id' => $doc->id, 'remarks' => $request->remarks, 'document_type' => $doc->document_type]
            );
        }

        audit_log($request->user()->id, 'reject_document', ['document_id' => $id]);
        return response()->json(['message' => 'Document rejected with remarks.', 'document' => $doc]);
    }

    /** PATCH /api/v1/coordinator/documents/bulk-approve — forward each to faculty stage */
    public function bulkApproveDocuments(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);

        $actorId = $request->user()->id;
        $docs = Document::whereIn('id', $request->ids)
            ->where('current_stage', 'coordinator')
            ->whereIn('status', ['pending_review', 'resubmitted', 'under_review'])
            ->with('internship')
            ->get()
            ->filter(function (Document $doc) use ($actorId) {
                $internship = $doc->internship;
                return $internship
                    && ($internship->coordinator_id === null || (int) $internship->coordinator_id === $actorId);
            });

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
                    '/student/documents',
                    ['document_id' => $doc->id, 'document_type' => $doc->document_type]
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

        $actorId = $request->user()->id;
        $docs = Document::whereIn('id', $request->ids)
            ->where('current_stage', 'coordinator')
            ->with('internship')
            ->get()
            ->filter(function (Document $doc) use ($actorId) {
                $internship = $doc->internship;
                return $internship
                    && ($internship->coordinator_id === null || (int) $internship->coordinator_id === $actorId);
            });

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
                    ['document_id' => $doc->id, 'remarks' => $request->remarks, 'document_type' => $doc->document_type]
                );
            }
        }

        audit_log($request->user()->id, 'bulk_reject_documents', ['document_ids' => $docs->pluck('id')]);

        return response()->json(['message' => "{$docs->count()} document(s) rejected.", 'rejected_count' => $docs->count()]);
    }

    /** GET /api/v1/coordinator/logbook */
    public function logbook(Request $request)
    {
        $coordId = $request->user()->id;
        $journals = JournalEntry::where('status', 'submitted')
            ->whereHas('internship', fn ($q) => $q->where('coordinator_id', $coordId))
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

        $journal = JournalEntry::whereHas(
            'internship',
            fn ($q) => $q->where('coordinator_id', $request->user()->id)
        )->findOrFail($id);

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
        $coordId = $request->user()->id;
        $query = User::where('role', 'student')
            ->whereHas('internshipsAsStudent', fn ($q) => $q->where('coordinator_id', $coordId))
            ->with([
                'studentProfile',
                'activeInternship.company',
                'internshipsAsStudent' => fn ($q) => $q
                    ->where('coordinator_id', $coordId)
                    ->withCount(['attendance as validated_days' => fn ($a) => $a->where('status', 'validated')]),
            ]);

        if ($request->boolean('archived')) {
            $query->where('is_active', false);
        } else {
            $query->where('is_active', true);
        }

        return ApiResponse::list($query->paginate(20));
    }

    /**
     * PATCH /api/v1/coordinator/students/{userId}/archive
     * Body: { archived: true|false }
     */
    public function setStudentArchived(Request $request, int $userId)
    {
        $request->validate(['archived' => 'required|boolean']);

        $student = User::where('role', 'student')->findOrFail($userId);
        $student->is_active = !$request->boolean('archived');
        $student->save();

        audit_log($request->user()->id, $request->boolean('archived') ? 'archive_student' : 'unarchive_student', [
            'student_id' => $userId,
        ]);

        return response()->json([
            'message' => $request->boolean('archived') ? 'Student archived.' : 'Student restored to active.',
            'student' => [
                'id'        => $student->id,
                'username'  => $student->username,
                'is_active' => $student->is_active,
            ],
        ]);
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

        $this->assertCoordinatorOwns($internship, $request->user()->id);

        if ($internship->status !== 'pending_placement') {
            return response()->json(['message' => 'Student is already placed or cannot be placed at this time.'], 422);
        }

        $faculty = User::findOrFail((int) $request->faculty_id);
        if ($faculty->role !== 'faculty') {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['faculty_id' => ['The selected faculty must have the faculty role.']],
            ], 422);
        }

        $supervisor = User::findOrFail((int) $request->supervisor_id);
        if ($supervisor->role !== 'supervisor') {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['supervisor_id' => ['The selected user must be a company supervisor.']],
            ], 422);
        }

        $company = Company::findOrFail((int) $request->company_id);
        if (!$company->isEligibleForPlacement()) {
            return response()->json([
                'message' => $company->ineligibilityReason(),
                'errors' => ['company_id' => [$company->ineligibilityReason()]],
            ], 422);
        }

        $profile = $internship->student?->studentProfile;
        $program = $internship->program
            ?: ($profile?->program ?: $profile?->course_name);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $internship, $company, $program) {
            $internship->update([
                'company_id'     => $company->id,
                'faculty_id'     => $request->faculty_id,
                'supervisor_id'  => $request->supervisor_id,
                'coordinator_id' => $request->user()->id,
                'program'        => $program,
                'status'         => 'active',
                'status_reason'  => 'Authorized deployment / placement by coordinator.',
                'start_date'     => now(),
            ]);

            $company->consumeSlot();

            \App\Models\InternshipStatusHistory::create([
                'internship_id' => $internship->id,
                'from_status' => 'pending_placement',
                'to_status' => 'active',
                'reason' => 'Authorized deployment / placement by coordinator.',
                'changed_by' => $request->user()->id,
            ]);
        });

        Notification::notify(
            $internship->student_id,
            'placement_assigned',
            'Internship Placement Assigned',
            'You have been officially deployed. You may now start logging your hours.',
            '/student/dashboard',
            ['internship_id' => $internship->id]
        );

        audit_log($request->user()->id, 'assign_placement', ['internship_id' => $internship->id]);

        return response()->json([
            'message' => 'Placement assigned successfully.',
            'internship' => $internship->fresh(),
        ]);
    }

    /** GET /api/v1/coordinator/absorption — completed internships needing / with outcomes */
    public function absorptionList(Request $request)
    {
        $perPage = min(100, max(10, (int) $request->query('per_page', 50)));

        $paginator = Internship::where('status', 'completed')
            ->where('coordinator_id', $request->user()->id)
            ->with(['student.studentProfile', 'company', 'supervisor.supervisorProfile'])
            ->orderByDesc('end_date')
            ->paginate($perPage);

        return response()->json([
            'internships' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
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
        $this->assertCoordinatorOwns($internship, $request->user()->id);
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

    /** GET /api/v1/coordinator/reports/overview — lightweight owned-internship stats */
    public function reportsOverview(Request $request)
    {
        $coordId = $request->user()->id;

        $owned = Internship::where('coordinator_id', $coordId);

        $countsByStatus = (clone $owned)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $totalStudents = (clone $owned)->distinct()->count('student_id');

        $pendingDocs = Document::query()
            ->whereIn('status', ['pending_review', 'resubmitted', 'under_review'])
            ->whereHas('internship', fn ($q) => $q->where('coordinator_id', $coordId))
            ->count();

        return response()->json([
            'counts_by_status' => $countsByStatus,
            'total_students'   => $totalStudents,
            'pending_docs'     => $pendingDocs,
            'generated_at'     => now()->toDateTimeString(),
        ]);
    }

    /** GET /api/v1/coordinator/reports/student-summary */
    public function reportStudentSummary(Request $request)
    {
        $query = Internship::with(['student.studentProfile', 'company'])
            ->selectRaw('
                internships.*,
                (SELECT COUNT(*) FROM attendance_logs WHERE internship_id = internships.id AND status = "validated") as validated_days,
                (SELECT COUNT(*) FROM journal_entries WHERE internship_id = internships.id AND status = "approved") as approved_journals,
                (SELECT COUNT(*) FROM documents WHERE internship_id = internships.id AND status = "approved") as approved_docs
            ');

        $this->applyReportFilters($query, $request);

        $students = $query->orderBy('status')
            ->get()
            ->map(fn($i) => [
                'student_name'     => optional($i->student?->studentProfile)->first_name . ' ' . optional($i->student?->studentProfile)->last_name,
                'student_number'   => $i->student?->username,
                'program'          => $i->student?->studentProfile?->course_name ?? $i->student?->studentProfile?->program ?? $i->program ?? '—',
                'company'          => $i->company?->company_name ?? '—',
                'industry'         => $i->company?->industry ?? '—',
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

        return response()->json([
            'students'     => $students,
            'filters'      => $this->reportFilterOptions(),
            'applied'      => [
                'program'  => $request->input('program'),
                'industry' => $request->input('industry'),
            ],
            'generated_at' => now()->toDateTimeString(),
        ]);
    }

    /** GET /api/v1/coordinator/reports/compliance */
    public function reportCompliance(Request $request)
    {
        $query = Internship::with(['student.studentProfile', 'documents', 'company']);
        $this->applyReportFilters($query, $request);
        $internships = $query->get();

        $requiredTypes = \App\Support\RequiredDocuments::types();
        $requiredCount = \App\Support\RequiredDocuments::count();

        $rows = $internships->map(fn($i) => [
            'student_name'    => trim(optional($i->student?->studentProfile)->first_name . ' ' . optional($i->student?->studentProfile)->last_name),
            'program'         => $i->student?->studentProfile?->course_name ?? $i->student?->studentProfile?->program ?? '—',
            'industry'        => $i->company?->industry ?? '—',
            'approved_docs'   => $i->documents->where('status', 'approved')->count(),
            'required_docs'   => $requiredCount,
            'compliance_pct'  => round($i->documents->where('status', 'approved')->count() / $requiredCount * 100),
            'missing_docs'    => collect($requiredTypes)->diff($i->documents->where('status', 'approved')->pluck('document_type'))->values(),
        ]);

        return response()->json([
            'rows'           => $rows,
            'required_types' => $requiredTypes,
            'filters'        => $this->reportFilterOptions(),
            'applied'        => [
                'program'  => $request->input('program'),
                'industry' => $request->input('industry'),
            ],
            'generated_at'   => now()->toDateTimeString(),
        ]);
    }

    /** GET /api/v1/coordinator/reports/performance */
    public function reportPerformance(Request $request)
    {
        $query = Internship::with(['student.studentProfile', 'company']);
        $this->applyReportFilters($query, $request);

        $byProgram = $query->get()
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
            'by_program'    => $byProgram,
            'eval_averages' => $evalAvg,
            'filters'       => $this->reportFilterOptions(),
            'applied'       => [
                'program'  => $request->input('program'),
                'industry' => $request->input('industry'),
            ],
            'generated_at'  => now()->toDateTimeString(),
        ]);
    }

    /** Apply optional program / industry filters to internship report queries. */
    private function applyReportFilters($query, Request $request): void
    {
        if ($request->filled('program')) {
            $program = trim((string) $request->program);
            $query->where(function ($q) use ($program) {
                $q->where('internships.program', $program)
                    ->orWhereHas('student.studentProfile', function ($p) use ($program) {
                        $p->where('program', $program)->orWhere('course_name', $program);
                    });
            });
        }

        if ($request->filled('industry')) {
            $industry = trim((string) $request->industry);
            $query->whereHas('company', function ($c) use ($industry) {
                $c->where('industry', $industry);
            });
        }
    }

    /** Distinct program / industry values for report filter dropdowns. */
    private function reportFilterOptions(): array
    {
        $programs = Internship::query()
            ->leftJoin('users', 'users.id', '=', 'internships.student_id')
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->selectRaw("DISTINCT COALESCE(NULLIF(TRIM(internships.program), ''), NULLIF(TRIM(student_profiles.program), ''), NULLIF(TRIM(student_profiles.course_name), '')) as program")
            ->havingRaw('program IS NOT NULL AND program <> ""')
            ->orderBy('program')
            ->pluck('program')
            ->values()
            ->all();

        $industries = Company::query()
            ->whereNotNull('industry')
            ->where('industry', '<>', '')
            ->distinct()
            ->orderBy('industry')
            ->pluck('industry')
            ->values()
            ->all();

        return [
            'programs'   => $programs,
            'industries' => $industries,
        ];
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
