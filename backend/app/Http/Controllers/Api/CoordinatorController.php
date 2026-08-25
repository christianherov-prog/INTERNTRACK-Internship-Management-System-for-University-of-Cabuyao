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

    /**
     * GET /api/v1/coordinator/students/{userId}/progress
     * Returns aggregated progress data for a single student in the department.
     */
    public function studentProgress(Request $request, int $userId)
    {
        $student = User::where('role', 'student')->with('studentProfile.program')->findOrFail($userId);
        if (!User::inDepartment()->where('id', $userId)->exists()) {
            abort(403, 'Access Denied: You do not have permission to access resources from this department.');
        }

        $internship = Internship::inDepartment()->where('student_id', $userId)
            ->with(['company', 'supervisor.supervisorProfile', 'documents', 'journals'])
            ->latest()
            ->first();

        if (!$internship) {
            return response()->json([
                'student'         => [
                    'id'            => $student->id,
                    'name'          => $student->studentProfile?->full_name ?? $student->username,
                    'student_number'=> $student->studentProfile?->student_number,
                    'program'       => $student->studentProfile?->program?->name,
                    'section'       => $student->studentProfile?->section,
                ],
                'internship'      => null,
                'progress'        => [
                    'hours_rendered'  => 0,
                    'target_hours'    => 500,
                    'progress_pct'    => 0,
                ],
                'documents'       => [
                    'submitted'  => 0,
                    'approved'   => 0,
                    'total'      => \App\Support\RequiredDocuments::count(),
                    'items'      => [],
                ],
                'journals'        => [
                    'count'       => 0,
                    'last_date'   => null,
                    'last_status' => null,
                    'items'       => [],
                ],
                'attendance_logs' => [],
            ]);
        }

        $totalHours    = $internship->total_hours_rendered ?? 0;
        $targetHours   = $internship->target_hours ?? 500;
        $progressPct   = $targetHours > 0 ? min(100, round(($totalHours / $targetHours) * 100, 1)) : 0;

        $journals      = $internship->journals;
        $documents     = $internship->documents;

        $docsSubmitted = $documents->whereNotNull('file_path')->count();
        $docsApproved  = $documents->where('status', 'approved')->count();
        $docsTotal     = $documents->count();

        $journalCount  = $journals->count();
        $lastJournal   = $journals->sortByDesc('created_at')->first();

        // Get all attendance logs for the DTR preview
        $attendanceLogs = \App\Models\AttendanceLog::where('internship_id', $internship->id)
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'student'         => [
                'id'            => $internship->student->id,
                'name'          => $internship->student->studentProfile?->full_name ?? $internship->student->username,
                'student_number'=> $internship->student->studentProfile?->student_number,
                'program'       => $internship->student->studentProfile?->program?->name,
                'section'       => $internship->student->studentProfile?->section,
            ],
            'internship'      => [
                'id'            => $internship->id,
                'status'        => $internship->status,
                'company'       => $internship->company?->name,
                'supervisor'    => $internship->supervisor?->supervisorProfile?->full_name,
                'start_date'    => $internship->start_date,
                'end_date'      => $internship->end_date,
            ],
            'progress'        => [
                'hours_rendered'  => (float) $totalHours,
                'target_hours'    => (float) $targetHours,
                'progress_pct'    => $progressPct,
            ],
            'documents'       => [
                'submitted'  => $docsSubmitted,
                'approved'   => $docsApproved,
                'total'      => $docsTotal,
                'items'      => $documents->map(fn($d) => [
                    'id'     => $d->id,
                    'name'   => $d->document_type,
                    'status' => $d->status,
                ]),
            ],
            'journals'        => [
                'count'       => $journalCount,
                'last_date'   => $lastJournal?->date,
                'last_status' => $lastJournal?->status,
                'items'       => $journals->sortByDesc('week_number')->take(10)->map(fn($j) => [
                    'id'           => $j->id,
                    'week'         => $j->week_number,
                    'date'         => $j->date,
                    'end_date'     => $j->end_date,
                    'status'       => $j->status,
                    'accomplishment' => $j->activities_summary,
                    'difficulties'   => $j->challenges,
                    'insights'       => $j->learnings,
                ])->values(),
            ],
            'attendance_logs' => $attendanceLogs,
        ]);
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

        $activeInterns    = Internship::inDepartment()->whereIn('status', $live)->count();
        $pendingPlacement = User::inDepartment()->where('role', 'student')->where('is_active', true)
            ->where(function ($q) {
                $q->whereDoesntHave('activeInternship')
                  ->orWhereHas('activeInternship', fn($i) => $i->where('status', 'pending_placement'));
            })->count();
            
        $avgHoursCompletion = Internship::inDepartment()->whereIn('status', $live)
            ->selectRaw('AVG(total_hours_rendered / NULLIF(target_hours, 0) * 100) as avg_pct')
            ->value('avg_pct') ?? 0;

        // At-risk = below 30% completion
        $atRisk = Internship::inDepartment()->whereIn('status', $live)
            ->where('target_hours', '>', 0)
            ->whereRaw('(total_hours_rendered / target_hours) < 0.30')
            ->count();

        $fullyCompleted = Internship::inDepartment()->where('status', 'completed')->count();

        $query = User::inDepartment()->where('role', 'student')->where('is_active', true)
            ->with([
                'studentProfile.program',
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
                $facultyName = trim("{$fp->last_name}, {$fp->first_name}");
            } elseif ($profile && $profile->section) {
                $assignment = $facultyAssignments->get($profile->section);
                if ($assignment && $assignment->faculty && $assignment->faculty->facultyProfile) {
                    $fp = $assignment->faculty->facultyProfile;
                    $facultyName = trim("{$fp->last_name}, {$fp->first_name}");
                }
            }

            return [
                'user_id'            => $student->id,
                'internship_id'      => $i?->id,
                'student_name'       => $profile ? trim("{$profile->last_name}, {$profile->first_name}") : $student->username,
                'student_number'     => $profile?->student_number ?? '—',
                'program'            => $profile?->program?->name ?? '-',
                'section'            => $profile?->section ?? '-',
                'sex'                => $student->sex ?? $profile?->sex ?? '-',
                'faculty_name'       => $facultyName,
                'status'             => $i?->status ?? 'unplaced',
                'supervisor_name'    => $supProfile ? trim("{$supProfile->last_name}, {$supProfile->first_name}") : 'Not Assigned',
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

    /** GET /api/v1/coordinator/documents */
    public function documents(Request $request)
    {
        $coordId = $request->user()->id;

        // Only show documents for requirements created by this coordinator
        $coordinatorReqs = \App\Models\OjtRequirementTemplate::where('created_by', $coordId)->pluck('name');

        $docs = \App\Models\Document::whereIn('document_type', $coordinatorReqs)
            ->with(['internship.student.studentProfile'])
            ->orderByDesc('submitted_at')
            ->paginate(25);

        return ApiResponse::list($docs);
    }

    // Document verification methods removed (approveDocument, rejectDocument, bulkApprove, bulkReject)

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
        $query = User::inDepartment()->where('role', 'student')
            ->whereHas('internshipsAsStudent', fn ($q) => $q->where('coordinator_id', $coordId))
            ->with([
                'studentProfile.program',
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
        if (!User::inDepartment()->where('id', $userId)->exists()) {
            abort(403, 'Access Denied: You do not have permission to access resources from this department.');
        }
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

        $sections = \App\Services\FacultySectionAssignmentService::SECTIONS;
        $map = [];
        $service = app(\App\Services\FacultySectionAssignmentService::class);
        foreach ($sections as $sec) {
            $fac = $service->suggestFacultyForSection($sec);
            $map[$sec] = $fac ? $service->formatFaculty($fac) : null;
        }

        return response()->json([
            'companies' => $companies,
            'faculty' => $faculty,
            'supervisors' => $supervisors,
            'sections' => $sections,
            'section_faculty_map' => $map,
        ]);
    }

    /** POST /api/v1/coordinator/internships/{id}/place */
    public function assignPlacement(Request $request, $id)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'supervisor_id' => 'required|exists:users,id',
            'section' => 'nullable|string',
        ]);

        $internship = Internship::with('student.studentProfile')->findOrFail($id);
        if (!Internship::inDepartment()->where('id', $id)->exists()) {
            abort(403, 'Access Denied: You do not have permission to access resources from this department.');
        }

        $this->assertCoordinatorOwns($internship, $request->user()->id);

        if ($internship->status !== 'pending_placement') {
            return response()->json(['message' => 'Student is already placed or cannot be placed at this time.'], 422);
        }

        $supervisor = User::findOrFail((int) $request->supervisor_id);
        if ($supervisor->role !== 'supervisor') {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['supervisor_id' => ['The selected user must be a company supervisor.']],
            ], 422);
        }

        $profile = $internship->student?->studentProfile;

        if ($profile && $request->filled('section')) {
            $profile->section = \App\Services\FacultySectionAssignmentService::normalizeSection($request->section);
            $profile->save();
        }

        $service = app(\App\Services\FacultySectionAssignmentService::class);
        $assignedFaculty = $service->resolveFacultyForProfile($profile);
        $facultyId = $assignedFaculty?->id;

        $program = $internship->program ?: ($profile?->program);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $internship, $program, $facultyId) {
                $company = Company::lockForUpdate()->findOrFail((int) $request->company_id);
                if (!$company->isEligibleForPlacement()) {
                    throw new \RuntimeException($company->ineligibilityReason());
                }

                $internship->update([
                    'company_id'     => $company->id,
                    'faculty_id'     => $facultyId,
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
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['company_id' => [$e->getMessage()]],
            ], 422);
        }


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

        $paginator = Internship::inDepartment()->where('status', 'completed')
            ->where('coordinator_id', $request->user()->id)
            ->with(['student.studentProfile.program', 'company', 'supervisor.supervisorProfile'])
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
        if (!Internship::inDepartment()->where('id', $id)->exists()) {
            abort(403, 'Access Denied: You do not have permission to access resources from this department.');
        }
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

        $owned = Internship::inDepartment()->where('coordinator_id', $coordId);

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
        $query = Internship::inDepartment()->with(['student.studentProfile.program', 'company'])
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
                'student_name'     => optional($i->student?->studentProfile)->last_name . ', ' . optional($i->student?->studentProfile)->first_name,
                'student_number'   => $i->student?->username,
                'program'          => $i->student?->studentProfile?->program?->name ?? $i->program ?? '—',
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
        $query = Internship::inDepartment()->with(['student.studentProfile.program', 'documents', 'company']);
        $this->applyReportFilters($query, $request);
        $internships = $query->get();

        $requiredTypes = \App\Support\RequiredDocuments::types();
        $requiredCount = \App\Support\RequiredDocuments::count();

        $rows = $internships->map(fn($i) => [
            'student_name'    => trim(optional($i->student?->studentProfile)->last_name . ', ' . optional($i->student?->studentProfile)->first_name),
            'program'         => $i->student?->studentProfile?->program?->name ?? '—',
            'industry'        => $i->company?->industry ?? '—',
            'approved_docs'   => $i->documents->where('status', 'approved')->count(),
            'required_docs'   => $requiredCount,
            'compliance_pct'  => $requiredCount > 0 ? min(100, round($i->documents->where('status', 'approved')->count() / $requiredCount * 100)) : 0,
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
        $query = Internship::inDepartment()->with(['student.studentProfile.program', 'company']);
        $this->applyReportFilters($query, $request);

        $byProgram = $query->get()
            ->groupBy(function (Internship $i) {
                $p = $i->student?->studentProfile;
                return $p?->program?->name ?? $i->program ?? 'Unknown';
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
            $programId = $request->program;
            $query->whereHas('student.studentProfile', function ($p) use ($programId) {
                $p->where('program_id', $programId);
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
        $deptId = auth()->user()?->facultyProfile?->department_id;
        $programsQuery = \App\Models\Program::where('is_active', true);
        if ($deptId) {
            $programsQuery->where('department_id', $deptId);
        }
        $programs = $programsQuery->orderBy('name')->get()->map(fn($p) => ['id' => $p->id, 'name' => $p->name]);

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
        $formTypes = ['FO-24', 'FO-03', 'FO-22', 'FO-23'];

        $query = Internship::inDepartment()
            ->with([
                'student.studentProfile.program',
                'company',
                'supervisor.supervisorProfile',
                'faculty.facultyProfile',
                'evaluations' => function ($q) use ($formTypes) {
                    $q->whereIn('form_type', $formTypes);
                },
            ])
            ->whereHas('evaluations', function ($q) use ($formTypes) {
                $q->whereIn('form_type', $formTypes);
            });

        // Filter by program
        if ($request->filled('program_id')) {
            $query->whereHas('student.studentProfile', function ($q) use ($request) {
                $q->where('program_id', $request->input('program_id'));
            });
        }

        // Filter by section
        if ($request->filled('section')) {
            $query->whereHas('student.studentProfile', function ($q) use ($request) {
                $q->where('section', $request->input('section'));
            });
        }

        // Filter by faculty
        if ($request->filled('faculty_id')) {
            $query->where('faculty_id', $request->input('faculty_id'));
        }

        // Filter by search (student name)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('student.studentProfile', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $internships = $query->orderByDesc('created_at')->paginate(40);

        // Faculty options for filter dropdown
        $facultyOptions = \App\Models\User::inDepartment()
            ->where('role', 'faculty')
            ->with('facultyProfile')
            ->get()
            ->map(fn($f) => [
                'id'   => $f->id,
                'name' => trim(($f->facultyProfile?->last_name ?? '') . ', ' . ($f->facultyProfile?->first_name ?? '')) ?: $f->username,
            ]);

        return response()->json([
            'internships'    => $internships,
            'faculty_options' => $facultyOptions,
        ]);
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

    public function updateStudentSection(Request $request, $userId)
    {
        $request->validate([
            'section' => 'nullable|string',
        ]);
        
        $user = User::where('role', 'student')->findOrFail($userId);
        $profile = $user->studentProfile;
        
        if (!$profile) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }
        
        $section = \App\Services\FacultySectionAssignmentService::normalizeSection($request->section);
        $profile->section = $section;
        $profile->save();
        
        $service = app(\App\Services\FacultySectionAssignmentService::class);
        $assignedFaculty = $service->resolveFacultyForProfile($profile);
        
        $internships = Internship::where('student_id', $userId)->get();
        foreach ($internships as $internship) {
            $internship->faculty_id = $assignedFaculty?->id;
            $internship->save();
        }
        
        return response()->json([
            'message' => 'Section updated successfully.',
            'section' => $section,
            'resolved_faculty' => $service->formatFaculty($assignedFaculty),
        ]);
    }

    public function bulkUpdateStudentSection(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:users,id',
            'section' => 'nullable|string',
        ]);
        
        $section = \App\Services\FacultySectionAssignmentService::normalizeSection($request->section);
        $service = app(\App\Services\FacultySectionAssignmentService::class);
        
        $assignedFaculty = $service->suggestFacultyForSection($section);
        $facultyId = $assignedFaculty?->id;
        
        \Illuminate\Support\Facades\DB::transaction(function() use ($request, $section, $facultyId) {
            $studentIds = $request->student_ids;
            
            \App\Models\StudentProfile::whereIn('user_id', $studentIds)->update(['section' => $section]);
            \App\Models\Internship::whereIn('student_id', $studentIds)->update(['faculty_id' => $facultyId]);
        });
        
        return response()->json([
            'message' => 'Sections updated successfully.',
            'section' => $section,
            'resolved_faculty' => $service->formatFaculty($assignedFaculty),
        ]);
    }

    public function applications()
    {
        // Legacy endpoint: return internships pending placement
        $applications = \App\Models\Internship::inDepartment()
            ->where('status', 'pending_placement')
            ->with(['student.studentProfile.program', 'company'])
            ->get();
        return response()->json(['applications' => $applications]);
    }

    public function updateApplicationStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required']);
        return response()->json(['message' => 'Status updated. Please use the Placements tab for full workflow.']);
    }

    public function hteRequests()
    {
        $requests = \App\Models\HteRequest::with('student.studentProfile.program')->get();
        return response()->json(['requests' => $requests]);
    }

    public function updateHteRequestStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required']);
        $req = \App\Models\HteRequest::findOrFail($id);
        $req->update(['status' => $request->status]);
        return response()->json(['message' => 'Status updated', 'request' => $req]);
    }
}
