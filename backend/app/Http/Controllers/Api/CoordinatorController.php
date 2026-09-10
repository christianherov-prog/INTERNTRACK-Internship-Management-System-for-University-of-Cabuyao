<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\FacultySectionAssignment;
use App\Models\HteRequest;
use App\Models\Internship;
use App\Models\InternshipApplication;
use App\Models\InternshipStatusHistory;
use App\Models\JournalEntry;
use App\Models\Notification;
use App\Models\OjtRequirementTemplate;
use App\Models\Program;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\AbsorptionService;
use App\Services\FacultySectionAssignmentService;
use App\Services\InternshipProgressService;
use App\Services\OfficialFormDataService;
use App\Services\ProgramRequirementService;
use App\Services\SupervisorFeedbackService;
use App\Support\ApiResponse;
use App\Support\DepartmentScope;
use App\Support\InternshipStatuses;
use App\Support\NameParts;
use App\Support\RequiredDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoordinatorController extends Controller
{
    /**
     * Light ownership check using internship.coordinator_id.
     * Null coordinator_id = unclaimed (any coordinator may act / claim).
     */
    private function assertCoordinatorOwns(?Internship $internship, int $actorId): void
    {
        if (! $internship) {
            abort(404, 'Internship not found for this record.');
        }
        $actor = auth()->user();
        if ($actor && ! DepartmentScope::internshipBelongsToActor($actor, $internship)) {
            DepartmentScope::abortDifferentDepartment();
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
        if (! User::inDepartment()->where('id', $userId)->exists()) {
            DepartmentScope::abortDifferentDepartment();
        }

        $internship = Internship::inDepartment()->where('student_id', $userId)
            ->with(['company', 'supervisor.supervisorProfile', 'documents', 'journals', 'student.studentProfile.program'])
            ->latest()
            ->first();

        if (! $internship) {
            return response()->json([
                'student' => [
                    'id' => $student->id,
                    'name' => NameParts::fromProfile($student->studentProfile) ?: ($student->studentProfile?->full_name ?? $student->username),
                    'student_number' => $student->studentProfile?->student_number,
                    'program' => $student->studentProfile?->program?->name,
                    'section' => $student->studentProfile?->section,
                ],
                'internship' => null,
                'progress' => [
                    'hours_rendered' => 0,
                    'target_hours' => ProgramRequirementService::targetHoursForProfile($student->studentProfile),
                    'progress_pct' => 0,
                ],
                'documents' => [
                    'submitted' => 0,
                    'approved' => 0,
                    'total' => RequiredDocuments::count(),
                    'items' => [],
                ],
                'journals' => [
                    'count' => 0,
                    'last_date' => null,
                    'last_status' => null,
                    'items' => [],
                ],
                'attendance_logs' => [],
            ]);
        }

        $progressSnap = InternshipProgressService::snapshot($internship);
        $totalHours = $progressSnap['hours_rendered'];
        $targetHours = $progressSnap['target_hours'];
        $progressPct = $progressSnap['progress_pct'];

        $journals = $internship->journals;
        $documents = $internship->documents;

        $docsSubmitted = $documents->whereNotNull('file_path')->count();
        $docsApproved = $documents->where('status', 'approved')->count();
        $docsTotal = $documents->count();

        $journalCount = $journals->count();
        $lastJournal = $journals->sortByDesc('created_at')->first();
        $officialForm = app(OfficialFormDataService::class)->bundle($internship);

        return response()->json([
            'student' => [
                'id' => $internship->student->id,
                'name' => NameParts::fromProfile($internship->student->studentProfile) ?: ($internship->student->studentProfile?->full_name ?? $internship->student->username),
                'student_number' => $internship->student->studentProfile?->student_number,
                'program' => $internship->student->studentProfile?->program?->name,
                'section' => $internship->student->studentProfile?->section,
            ],
            'internship' => [
                'id' => $internship->id,
                'status' => $internship->status,
                'company' => $progressSnap['company_name'],
                'supervisor' => $internship->supervisor?->supervisorProfile?->full_name,
                'start_date' => $internship->start_date,
                'end_date' => $internship->end_date,
            ],
            'progress' => [
                'hours_rendered' => (float) $totalHours,
                'target_hours' => (float) $targetHours,
                'progress_pct' => $progressPct,
            ],
            'documents' => [
                'submitted' => $docsSubmitted,
                'approved' => $docsApproved,
                'total' => $docsTotal,
                'items' => $documents->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->document_type,
                    'status' => $d->status,
                ]),
            ],
            'journals' => [
                'count' => $journalCount,
                'last_date' => $lastJournal?->date,
                'last_status' => $lastJournal?->status,
                'items' => $journals->sortByDesc('week_number')->take(10)->map(fn ($j) => [
                    'id' => $j->id,
                    'week' => $j->week_number,
                    'date' => $j->date,
                    'end_date' => $j->end_date,
                    'status' => $j->status,
                    'accomplishment' => $j->activities_summary,
                    'difficulties' => $j->challenges,
                    'insights' => $j->learnings,
                ])->values(),
            ],
            'attendance_logs' => $officialForm['fo30']['logs'] ?? [],
            'official_form' => $officialForm,
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

        $activeInterns = Internship::inDepartment()->whereIn('status', $live)->count();
        $pendingPlacement = User::inDepartment()->where('role', 'student')->where('is_active', true)
            ->where(function ($q) {
                $q->whereDoesntHave('activeInternship')
                    ->orWhereHas('activeInternship', fn ($i) => $i->where('status', 'pending_placement'));
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
                'activeInternship.journals' => fn ($q) => $q->latest('date')->limit(1),
                'activeInternship.documents',
            ]);

        $students = $query->paginate(25);

        $deptId = DepartmentScope::departmentIdFor($request->user());
        $sections = $students->pluck('studentProfile.section')->filter()->unique();
        $facultyAssignments = FacultySectionAssignment::whereIn('section', $sections)
            ->when($deptId, fn ($q) => $q->whereHas('faculty.facultyProfile', fn ($fp) => $fp->where('department_id', $deptId)))
            ->with('faculty.facultyProfile')
            ->get()
            ->keyBy('section');

        $rows = $students->through(function ($student) use ($facultyAssignments) {
            $profile = $student->studentProfile;
            $i = $student->activeInternship;
            $supProfile = $i?->supervisor?->supervisorProfile;
            $lastJournal = $i?->journals->first();
            $docsApproved = $i?->documents->where('status', 'approved')->count() ?? 0;
            $docsTotal = RequiredDocuments::count();

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
                'user_id' => $student->id,
                'internship_id' => $i?->id,
                'student_name' => $profile ? trim("{$profile->last_name}, {$profile->first_name}") : $student->username,
                'student_number' => $profile?->student_number ?? '—',
                'program' => $profile?->program?->name ?? '-',
                'section' => $profile?->section ?? '-',
                'sex' => $student->sex ?? $profile?->sex ?? '-',
                'faculty_name' => $facultyName,
                'status' => $i?->status ?? 'unplaced',
                'supervisor_name' => $supProfile ? trim("{$supProfile->last_name}, {$supProfile->first_name}") : 'Not Assigned',
                'company' => $i?->company?->company_name ?? 'Not Assigned',
                'last_journal_date' => $lastJournal?->date?->toDateString(),
                'journal_status' => $lastJournal?->status ?? 'none',
                'docs_approved' => $docsApproved,
                'docs_total' => $docsTotal,
                'docs_label' => $docsTotal > 0 && $docsApproved < $docsTotal ? ($docsTotal - $docsApproved).' Missing' : 'Complete',
                'docs_status' => $docsTotal > 0 && $docsApproved >= $docsTotal ? 'complete' : 'missing',
                'progress_percent' => (float) ($i && $i->target_hours > 0 ? round($i->total_hours_rendered / $i->target_hours * 100, 1) : 0),
                'hours_rendered' => (float) ($i?->total_hours_rendered ?? 0),
                'target_hours' => $i?->target_hours ?? 0,
            ];
        });

        return response()->json([
            'stats' => [
                'active_interns' => $activeInterns,
                'pending_placement' => $pendingPlacement,
                'avg_hours_completion' => round($avgHoursCompletion, 1),
                'at_risk_students' => $atRisk,
                'fully_completed' => $fullyCompleted,
            ],
        ] + ApiResponse::list($rows)->getData(true));
    }

    /** GET /api/v1/coordinator/documents */
    public function documents(Request $request)
    {
        $coordId = $request->user()->id;

        // Only show documents for requirements created by this coordinator
        $coordinatorReqs = OjtRequirementTemplate::where('created_by', $coordId)->pluck('name');

        $docs = Document::whereIn('document_type', $coordinatorReqs)
            ->whereHas('internship', fn ($q) => $q->inDepartment())
            ->with(['internship.student.studentProfile', 'attachments'])
            ->whereIn('status', ['pending', 'pending_review', 'under_review', 'pending_faculty', 'resubmitted'])
            ->orderByDesc('submitted_at')
            ->paginate(25);

        return ApiResponse::list($docs);
    }

    // Document verification methods removed (approveDocument, rejectDocument, bulkApprove, bulkReject)

    /** GET /api/v1/coordinator/logbook */
    public function logbook(Request $request)
    {
        $coordId = $request->user()->id;
        $journals = JournalEntry::academic()
            ->where('status', 'submitted')
            ->whereHas('internship', fn ($q) => $q->inDepartment()->where('coordinator_id', $coordId))
            ->with(['internship.student.studentProfile'])
            ->orderByDesc('date')
            ->paginate(25);

        return ApiResponse::list($journals);
    }

    /** PATCH /api/v1/coordinator/logbook/{id}/review */
    public function reviewLogbook(Request $request, int $id)
    {
        $request->validate([
            'action' => 'required|in:approved,needs_revision',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $journal = JournalEntry::whereHas(
            'internship',
            fn ($q) => $q->inDepartment()->where('coordinator_id', $request->user()->id)
        )->findOrFail($id);

        $journal->update([
            'status' => $request->action,
            'faculty_feedback' => $request->feedback,
            'faculty_reviewed_by' => $request->user()->id,
            'faculty_reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Journal '.$request->action.'.', 'journal' => $journal]);
    }

    /** GET /api/v1/coordinator/records */
    public function records(Request $request)
    {
        $query = User::inDepartment()->where('role', 'student')
            ->with([
                'studentProfile.program',
                'activeInternship.company',
                'internshipsAsStudent' => fn ($q) => $q
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
        if (! User::inDepartment()->where('id', $userId)->exists()) {
            DepartmentScope::abortDifferentDepartment();
        }
        $student->is_active = ! $request->boolean('archived');
        $student->save();

        audit_log($request->user()->id, $request->boolean('archived') ? 'archive_student' : 'unarchive_student', [
            'student_id' => $userId,
        ]);

        return response()->json([
            'message' => $request->boolean('archived') ? 'Student archived.' : 'Student restored to active.',
            'student' => [
                'id' => $student->id,
                'username' => $student->username,
                'is_active' => $student->is_active,
            ],
        ]);
    }

    /** GET /api/v1/coordinator/placement-options */
    public function placementOptions(Request $request)
    {
        $companies = Company::where('moa_status', 'active')->get();
        $faculty = User::whereIn('role', ['faculty', 'coordinator'])
            ->whereHas('facultyProfile', function ($q) use ($request) {
                $deptId = DepartmentScope::departmentIdFor($request->user());
                if ($deptId) {
                    $q->where('department_id', $deptId);
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->with('facultyProfile')
            ->get();
        $supervisors = User::where('role', 'supervisor')->with('supervisorProfile')->get();

        $deptId = DepartmentScope::departmentIdFor($request->user());
        $sections = StudentProfile::query()
            ->when($deptId, fn ($q) => DepartmentScope::constrainStudentProfiles($q, $deptId), fn ($q) => $q->whereRaw('1 = 0'))
            ->pluck('section')
            ->merge(
                FacultySectionAssignment::query()
                    ->where('is_active', true)
                    ->when($deptId, function ($q) use ($deptId) {
                        $q->whereHas('faculty.facultyProfile', fn ($fp) => $fp->where('department_id', $deptId));
                    }, fn ($q) => $q->whereRaw('1 = 0'))
                    ->pluck('section')
            )
            ->map(fn ($s) => FacultySectionAssignmentService::normalizeSection($s) ?: $s)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $map = [];
        $service = app(FacultySectionAssignmentService::class);
        foreach ($sections as $sec) {
            $fac = $service->suggestFacultyForSection(
                $sec,
                null,
                null,
                null,
                DepartmentScope::departmentIdFor($request->user())
            );
            if ($fac && ! DepartmentScope::facultyBelongsToActor($request->user(), $fac)) {
                $fac = null;
            }
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
            'faculty_id' => 'nullable|exists:users,id',
        ]);

        $internship = Internship::with('student.studentProfile')->findOrFail($id);
        if (! Internship::inDepartment()->where('id', $id)->exists()) {
            DepartmentScope::abortDifferentDepartment();
        }

        $this->assertCoordinatorOwns($internship, $request->user()->id);

        $supervisor = User::findOrFail((int) $request->supervisor_id);
        if ($supervisor->role !== 'supervisor') {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['supervisor_id' => ['The selected user must be a company supervisor.']],
            ], 422);
        }

        $profile = $internship->student?->studentProfile;

        if ($profile && $request->filled('section')) {
            $profile->section = FacultySectionAssignmentService::normalizeSection($request->section);
            $profile->save();
        }

        $service = app(FacultySectionAssignmentService::class);
        $assignedFaculty = null;
        if ($request->filled('faculty_id')) {
            $assignedFaculty = User::findOrFail((int) $request->faculty_id);
            if (! in_array($assignedFaculty->role, ['faculty', 'coordinator'], true)) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['faculty_id' => ['The selected user must be a faculty member.']],
                ], 422);
            }
            DepartmentScope::assertFacultySameDepartment($assignedFaculty, $internship->student ?? $profile);
        } else {
            $assignedFaculty = $service->suggestFacultyForSection(
                $profile?->section,
                is_object($profile?->program) ? $profile->program->name : $profile?->program,
                $profile?->school_year,
                $profile?->semester,
                DepartmentScope::studentDepartmentId($profile)
            );
            if ($assignedFaculty && $profile && ! DepartmentScope::facultyMatchesStudent($assignedFaculty, $profile)) {
                $assignedFaculty = null;
            }
        }
        $facultyId = $assignedFaculty?->id;

        $program = $internship->program ?: ($profile?->program?->name);

        try {
            DB::transaction(function () use ($request, $internship, $program, $facultyId) {
                $lockedInternship = Internship::whereKey($internship->id)->lockForUpdate()->firstOrFail();
                if ($lockedInternship->status !== 'pending_placement') {
                    throw new \RuntimeException('Student is already placed or cannot be placed at this time.');
                }

                $company = Company::lockForUpdate()->findOrFail((int) $request->company_id);
                if (! $company->isEligibleForPlacement()) {
                    throw new \RuntimeException($company->ineligibilityReason());
                }

                $lockedInternship->update([
                    'company_id' => $company->id,
                    'faculty_id' => $facultyId,
                    'supervisor_id' => $request->supervisor_id,
                    'coordinator_id' => $request->user()->id,
                    'program' => $program,
                    'status' => 'active',
                    'status_reason' => 'Authorized deployment / placement by coordinator.',
                    'start_date' => now(),
                ]);

                $company->consumeSlot();

                InternshipStatusHistory::create([
                    'internship_id' => $lockedInternship->id,
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

        InternshipProgressService::synchronize($internship->fresh());

        Notification::notify(
            $internship->student_id,
            'placement_assigned',
            'Internship Placement Assigned',
            'You have been officially deployed. You may now start logging your hours.',
            '/student/records',
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
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /** PATCH /api/v1/coordinator/internships/{id}/absorption */
    public function recordAbsorption(Request $request, int $id)
    {
        $request->validate([
            'absorption_status' => 'required|in:absorbed,not_hired',
            'absorbed_at' => 'nullable|date',
            'job_title' => 'nullable|string|max:255',
            'absorption_notes' => 'nullable|string|max:2000',
        ]);

        $internship = Internship::findOrFail($id);
        if (! Internship::inDepartment()->where('id', $id)->exists()) {
            DepartmentScope::abortDifferentDepartment();
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
            'status' => $request->absorption_status,
        ]);

        return response()->json(['message' => 'Absorption outcome saved.', 'internship' => $updated]);
    }

    /** GET /api/v1/coordinator/reports/overview — lightweight owned-internship stats */
    public function reportsOverview(Request $request)
    {
        $owned = Internship::inDepartment();

        $countsByStatus = (clone $owned)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $totalStudents = (clone $owned)->distinct()->count('student_id');

        $pendingDocs = Document::query()
            ->whereIn('status', ['pending_review', 'resubmitted', 'under_review'])
            ->whereHas('internship', fn ($q) => $q->inDepartment())
            ->count();

        return response()->json([
            'counts_by_status' => $countsByStatus,
            'total_students' => $totalStudents,
            'pending_docs' => $pendingDocs,
            'generated_at' => now()->toDateTimeString(),
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
            ->map(fn ($i) => [
                'student_name' => optional($i->student?->studentProfile)->last_name.', '.optional($i->student?->studentProfile)->first_name,
                'student_number' => $i->student?->username,
                'program' => $i->student?->studentProfile?->program?->name ?? $i->program ?? '—',
                'company' => $i->company?->company_name ?? '—',
                'industry' => $i->company?->industry ?? '—',
                'status' => $i->status,
                'hours_rendered' => (float) $i->total_hours_rendered,
                'target_hours' => $i->target_hours,
                'progress_pct' => $i->target_hours > 0 ? round($i->total_hours_rendered / $i->target_hours * 100, 1) : 0,
                'validated_days' => $i->validated_days,
                'approved_journals' => $i->approved_journals,
                'approved_docs' => $i->approved_docs,
                'start_date' => $i->start_date?->toDateString(),
                'end_date' => $i->end_date?->toDateString(),
                'final_grade' => $i->final_grade,
            ]);

        return response()->json([
            'students' => $students,
            'filters' => $this->reportFilterOptions(),
            'applied' => [
                'program' => $request->input('program'),
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

        $requiredTypes = RequiredDocuments::types();
        $requiredCount = RequiredDocuments::count();

        $rows = $internships->map(fn ($i) => [
            'student_name' => trim(optional($i->student?->studentProfile)->last_name.', '.optional($i->student?->studentProfile)->first_name),
            'program' => $i->student?->studentProfile?->program?->name ?? '—',
            'industry' => $i->company?->industry ?? '—',
            'approved_docs' => $i->documents->where('status', 'approved')->count(),
            'required_docs' => $requiredCount,
            'compliance_pct' => $requiredCount > 0 ? min(100, round($i->documents->where('status', 'approved')->count() / $requiredCount * 100)) : 0,
            'missing_docs' => collect($requiredTypes)->diff($i->documents->where('status', 'approved')->pluck('document_type'))->values(),
        ]);

        return response()->json([
            'rows' => $rows,
            'required_types' => $requiredTypes,
            'filters' => $this->reportFilterOptions(),
            'applied' => [
                'program' => $request->input('program'),
                'industry' => $request->input('industry'),
            ],
            'generated_at' => now()->toDateTimeString(),
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

        $evalAvg = Evaluation::selectRaw('
            evaluator_type,
            AVG(average_score) as avg_overall
        ')
            ->whereHas('internship', fn ($q) => $q->inDepartment())
            ->groupBy('evaluator_type')
            ->get();

        return response()->json([
            'by_program' => $byProgram,
            'eval_averages' => $evalAvg,
            'filters' => $this->reportFilterOptions(),
            'applied' => [
                'program' => $request->input('program'),
                'industry' => $request->input('industry'),
            ],
            'generated_at' => now()->toDateTimeString(),
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
        $deptId = DepartmentScope::departmentIdFor(auth()->user());
        $programsQuery = Program::where('is_active', true);
        if ($deptId) {
            $programsQuery->where('department_id', $deptId);
        } else {
            $programsQuery->whereRaw('1 = 0');
        }
        $programs = $programsQuery->orderBy('name')->get()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]);

        $industries = Company::query()
            ->whereNotNull('industry')
            ->where('industry', '<>', '')
            ->distinct()
            ->orderBy('industry')
            ->pluck('industry')
            ->values()
            ->all();

        return [
            'programs' => $programs,
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
        $facultyOptions = User::inStaffDepartment()
            ->whereIn('role', ['faculty', 'coordinator'])
            ->with('facultyProfile')
            ->get()
            ->map(function ($f) {
                $formatted = app(FacultySectionAssignmentService::class)->formatFaculty($f);

                return [
                    'id' => $f->id,
                    'name' => $formatted['name'] ?? $f->username,
                    'first_name' => $f->facultyProfile?->first_name,
                    'last_name' => $f->facultyProfile?->last_name,
                    'faculty_number' => $formatted['faculty_number'] ?? $f->username,
                ];
            });

        return response()->json([
            'internships' => $internships,
            'faculty_options' => $facultyOptions,
        ]);
    }

    /** GET /api/v1/coordinator/supervisor-feedback — narrative feedback from industry supervisors */
    public function supervisorFeedback(Request $request)
    {
        $notes = JournalEntry::whereNotNull('supervisor_feedback')
            ->where('status', SupervisorFeedbackService::NOTE_STATUS)
            ->whereHas('internship', fn ($q) => $q->inDepartment())
            ->with(['internship.student.studentProfile', 'internship.company', 'internship.supervisor.supervisorProfile'])
            ->orderByDesc('supervisor_reviewed_at')
            ->paginate(40);

        $service = app(SupervisorFeedbackService::class);
        $notes->getCollection()->transform(fn (JournalEntry $note) => $service->serialize($note) + [
            'id' => $note->id,
            'internship' => $note->internship,
        ]);

        return ApiResponse::list($notes);
    }

    public function updateStudentSection(Request $request, $userId)
    {
        $request->validate([
            'section' => 'nullable|string',
        ]);

        $user = User::where('role', 'student')->findOrFail($userId);
        DepartmentScope::abortUnlessStudentInDepartment($request->user(), (int) $userId);
        $profile = $user->studentProfile;

        if (! $profile) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        $section = FacultySectionAssignmentService::normalizeSection($request->section);
        $profile->section = $section;
        $profile->save();

        $service = app(FacultySectionAssignmentService::class);
        $assignedFaculty = $service->suggestFacultyForSection(
            $profile->section,
            is_object($profile->program) ? $profile->program->name : $profile->program,
            $profile->school_year,
            $profile->semester,
            DepartmentScope::studentDepartmentId($profile)
        );
        if ($assignedFaculty && ! DepartmentScope::facultyMatchesStudent($assignedFaculty, $profile)) {
            $assignedFaculty = null;
        }

        if ($assignedFaculty) {
            $internships = Internship::where('student_id', $userId)->get();
            foreach ($internships as $internship) {
                $internship->faculty_id = $assignedFaculty->id;
                $internship->save();
            }
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

        $section = FacultySectionAssignmentService::normalizeSection($request->section);
        $service = app(FacultySectionAssignmentService::class);

        $assignedFaculty = $service->suggestFacultyForSection(
            $section,
            null,
            null,
            null,
            DepartmentScope::departmentIdFor($request->user())
        );
        if ($assignedFaculty && ! DepartmentScope::facultyBelongsToActor($request->user(), $assignedFaculty)) {
            $assignedFaculty = null;
        }
        $facultyId = $assignedFaculty?->id;

        $studentIds = collect($request->student_ids)->map(fn ($id) => (int) $id)->unique()->values();
        $allowedIds = User::inDepartment()->where('role', 'student')->whereIn('id', $studentIds)->pluck('id');
        if ($allowedIds->count() !== $studentIds->count()) {
            DepartmentScope::abortDifferentDepartment();
        }

        if ($assignedFaculty) {
            User::whereIn('id', $allowedIds)->with('studentProfile')->get()->each(function (User $student) use ($assignedFaculty) {
                DepartmentScope::assertFacultySameDepartment($assignedFaculty, $student);
            });
        }

        DB::transaction(function () use ($allowedIds, $section, $facultyId) {
            StudentProfile::whereIn('user_id', $allowedIds)->update(['section' => $section]);
            Internship::whereIn('student_id', $allowedIds)->update(['faculty_id' => $facultyId]);
        });

        return response()->json([
            'message' => 'Sections updated successfully.',
            'section' => $section,
            'resolved_faculty' => $service->formatFaculty($assignedFaculty),
        ]);
    }

    public function applications()
    {
        $applications = InternshipApplication::query()
            ->with(['student.studentProfile.program', 'company'])
            ->whereHas('student', fn ($q) => $q->inDepartment())
            ->latest()
            ->get();

        return response()->json(['applications' => $applications]);
    }

    public function updateApplicationStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
            'coordinator_remarks' => 'nullable|string|max:2000',
        ]);

        if ($data['status'] === 'rejected' && blank($data['coordinator_remarks'] ?? null)) {
            return response()->json([
                'message' => 'A reason is required when rejecting an application.',
                'errors' => ['coordinator_remarks' => ['A reason is required when rejecting an application.']],
            ], 422);
        }

        $application = InternshipApplication::with(['student.studentProfile', 'company'])->findOrFail($id);
        DepartmentScope::abortUnlessStudentInDepartment($request->user(), (int) $application->student_id);

        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'This application has already been processed.',
                'application' => $application,
            ], 422);
        }

        DB::transaction(function () use ($application, $data) {
            $application->update([
                'status' => $data['status'],
                'coordinator_remarks' => $data['status'] === 'rejected'
                    ? $data['coordinator_remarks']
                    : $application->coordinator_remarks,
            ]);

            $internship = Internship::query()
                ->where('student_id', $application->student_id)
                ->whereIn('status', InternshipStatuses::openCurrent())
                ->lockForUpdate()
                ->first();

            if ($internship && $data['status'] === 'rejected' && (int) $internship->company_id === (int) $application->company_id) {
                $internship->update(['company_id' => null]);
            }
        });

        $application->refresh()->load(['student.studentProfile.program', 'company']);

        $companyName = $application->company?->company_name ?: 'the company';
        if ($data['status'] === 'approved') {
            Notification::notify(
                (int) $application->student_id,
                'placement_application_approved',
                'Application approved',
                'Your application to '.$companyName.' was approved. Await placement confirmation.',
                '/student/companies'
            );
        } else {
            Notification::notify(
                (int) $application->student_id,
                'placement_application_rejected',
                'Application rejected',
                'Your application to '.$companyName.' was rejected. '.$application->coordinator_remarks,
                '/student/companies'
            );
        }

        return response()->json([
            'message' => $data['status'] === 'approved' ? 'Application approved.' : 'Application rejected.',
            'application' => $application,
        ]);
    }

    public function hteRequests()
    {
        $requests = HteRequest::with('student.studentProfile.program')
            ->whereHas('student', fn ($q) => $q->inDepartment())
            ->latest()
            ->get();

        return response()->json(['requests' => $requests]);
    }

    public function updateHteRequestStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
            'remarks' => 'nullable|string|max:2000',
            'coordinator_remarks' => 'nullable|string|max:2000',
        ]);

        $reason = $data['coordinator_remarks'] ?? $data['remarks'] ?? null;
        if ($data['status'] === 'rejected' && blank($reason)) {
            return response()->json([
                'message' => 'A reason is required when rejecting an HTE request.',
                'errors' => ['coordinator_remarks' => ['A reason is required when rejecting an HTE request.']],
            ], 422);
        }

        $req = HteRequest::with('student.studentProfile')->findOrFail($id);
        DepartmentScope::abortUnlessStudentInDepartment($request->user(), (int) $req->student_id);

        if ($req->status !== 'pending') {
            return response()->json([
                'message' => 'This HTE request has already been processed.',
                'request' => $req,
            ], 422);
        }

        $company = null;
        DB::transaction(function () use ($req, $data, $reason, &$company) {
            $payload = ['status' => $data['status']];
            if ($data['status'] === 'rejected') {
                $payload['coordinator_remarks'] = $reason;
            }

            if ($data['status'] === 'approved') {
                $company = Company::query()
                    ->whereRaw('LOWER(company_name) = ?', [mb_strtolower($req->company_name)])
                    ->first();

                if (! $company) {
                    $company = Company::create([
                        'company_name' => $req->company_name,
                        'address' => $req->address,
                        'contact_person' => $req->contact_person,
                        'contact_email' => $req->contact_email,
                        'contact_number' => $req->contact_number,
                        'moa_status' => 'on-process',
                        'is_active' => true,
                        'slots_available' => 0,
                    ]);
                }

                if ($req->moa_path && ! $company->moa_file_path) {
                    $company->update(['moa_file_path' => $req->moa_path]);
                }
            }

            $req->update($payload);
        });

        $req->refresh()->load('student.studentProfile.program');

        if ($data['status'] === 'approved') {
            Notification::notify(
                (int) $req->student_id,
                'hte_request_approved',
                'HTE request approved',
                'Your HTE request for '.$req->company_name.' was approved.',
                '/student/companies'
            );
        } else {
            Notification::notify(
                (int) $req->student_id,
                'hte_request_rejected',
                'HTE request rejected',
                'Your HTE request for '.$req->company_name.' was rejected. '.$req->coordinator_remarks,
                '/student/companies'
            );
        }

        return response()->json([
            'message' => $data['status'] === 'approved' ? 'HTE request approved.' : 'HTE request rejected.',
            'request' => $req,
            'company' => $company,
        ]);
    }
}
