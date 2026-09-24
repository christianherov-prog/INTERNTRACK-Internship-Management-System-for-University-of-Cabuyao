<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AttendanceLog;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\FacultySectionAssignment;
use App\Models\Internship;
use App\Models\JournalEntry;
use App\Models\Notification;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\DtrWorkflowService;
use App\Services\FacultySectionAssignmentService;
use App\Services\InternshipProgressService;
use App\Services\OfficialFormDataService;
use App\Services\PortfolioDataService;
use App\Services\ProgramRequirementService;
use App\Services\SupervisorDirectoryService;
use App\Services\SupervisorFeedbackService;
use App\Support\ApiResponse;
use App\Support\DepartmentScope;
use App\Support\EvaluationPeriod;
use App\Support\InternshipAccess;
use App\Support\InternshipStatuses;
use App\Support\NameParts;
use App\Support\RequiredDocuments;
use App\Support\SignatureCapture;
use App\Support\UniqueWrite;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FacultyController extends Controller
{
    public function dashboard(Request $request)
    {
        $facultyId = $request->user()->id;
        $assignedStudentsCount = FacultySectionAssignmentService::assignedStudentsQuery($request->user())->count();

        $internships = Internship::inDepartment()->where('faculty_id', $facultyId)
            ->whereIn('status', ['ongoing', 'active', 'for_evaluation'])
            ->with('student.studentProfile')
            ->get();

        $internshipIds = $internships->pluck('id');

        $pendingJournals = $internshipIds->isEmpty()
            ? 0
            : JournalEntry::whereIn('internship_id', $internshipIds)
                ->pendingFacultyReview()
                ->count();

        $pendingEvals = $internships->whereNotIn('id',
            Evaluation::where('evaluator_type', 'faculty')->pluck('internship_id')->toArray()
        )->count();

        // Recent activity — last 5 journals or feedback the faculty has acted on
        $recentJournals = JournalEntry::whereIn('internship_id', $internshipIds)
            ->whereIn('status', ['approved', 'needs_revision'])
            ->whereNotNull('faculty_reviewed_at')
            ->with(['internship.student.studentProfile'])
            ->latest('faculty_reviewed_at')
            ->limit(5)
            ->get()
            ->map(fn ($j) => [
                'type' => 'journal',
                'action' => $j->status,
                'student' => $j->internship->student->studentProfile ? trim("{$j->internship->student->studentProfile->last_name}, {$j->internship->student->studentProfile->first_name}") : $j->internship->student->username,
                'week' => $j->week_number ?? $j->entry_number,
                'action_at' => $j->faculty_reviewed_at,
            ]);

        $recentActivity = $recentJournals->sortByDesc('action_at')->take(5)->values();

        $announcements = Announcement::where(function ($q) {
            $q->where('target_role', 'all')->orWhere('target_role', 'faculty');
        })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('is_pinned')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Announcement $a) => $a->toClientArray())
            ->values();

        return response()->json([
            'stats' => [
                'assigned_students' => $assignedStudentsCount,
                'pending_journals' => $pendingJournals,
                'pending_evaluations' => $pendingEvals,
            ],
            'recent_activity' => $recentActivity,
            'announcements' => $announcements,
        ]);
    }

    public function assignedStudents(Request $request)
    {
        $facultyId = $request->user()->id;
        $query = FacultySectionAssignmentService::assignedStudentsQuery(
            $request->user(),
            ! $request->boolean('archived')
        )->with(['studentProfile.program', 'activeInternship.company', 'activeInternship.supervisor.supervisorProfile', 'activeInternship.attendance']);

        if ($request->boolean('archived')) {
            $query->where('is_active', false);
        }

        $paginator = $query->paginate(20);

        $transformed = $paginator->through(function ($student) use ($facultyId) {
            $internship = $student->activeInternship;
            $profile = $student->studentProfile;
            $profile?->loadMissing('program');
            $programName = is_string($internship?->program) && $internship->program !== ''
                ? $internship->program
                : ($profile?->getRelation('program')?->name ?? '—');

            $progress = $internship ? InternshipProgressService::snapshot($internship) : null;
            $supervisorProfile = $internship?->supervisor?->supervisorProfile;
            $supervisorName = $supervisorProfile?->full_name ?: NameParts::fromProfile($supervisorProfile);
            $fo30 = $internship ? app(OfficialFormDataService::class)->fo30($internship) : null;

            return [
                'id' => $internship?->id ?? 0,
                'user_id' => $student->id,
                'student_id' => $student->id,
                'status' => $internship?->status ?? 'unplaced',
                // Portfolio preview is limited to internships this faculty personally handles.
                'can_preview_portfolio' => $internship !== null
                    && (int) $internship->faculty_id === (int) $facultyId,
                // Same authoritative check used for journal deadlines.
                'handled_by_faculty' => $internship !== null
                    && (int) $internship->faculty_id === (int) $facultyId,
                'program' => $programName,
                'section' => $profile?->section ?? '—',
                'company' => $internship?->company ? [
                    'id' => $internship->company->id,
                    'company_name' => $internship->company->company_name,
                    'company_logo_path' => $fo30['company_logo_path'] ?? null,
                ] : null,
                'supervisor' => $internship?->supervisor ? [
                    'id' => $internship->supervisor->id,
                    'supervisor_profile' => [
                        'first_name' => $supervisorProfile?->first_name,
                        'last_name' => $supervisorProfile?->last_name,
                        'full_name' => $supervisorName !== '' ? $supervisorName : null,
                    ],
                ] : null,
                'target_hours' => $progress['target_hours'] ?? 0,
                'total_hours_rendered' => $progress['hours_rendered'] ?? 0,
                'student' => [
                    'id' => $student->id,
                    'username' => $student->username,
                    'email' => $student->email,
                    'student_number' => $student->student_number ?? $profile?->student_number,
                    'sex' => collect([$student->sex, $profile?->sex])->first(fn ($s) => ! empty($s)) ?? '—',
                    'is_active' => $student->is_active,
                    'student_profile' => $profile,
                ],
                'student_signature_path' => $fo30['student_signature_path'] ?? null,
                'supervisor_signature_path' => $fo30['supervisor_signature_path'] ?? null,
                'attendance_logs' => $fo30['logs'] ?? [],
            ];
        });

        return ApiResponse::list($transformed);
    }

    /**
     * GET /api/v1/faculty/students/{userId}/portfolio
     *
     * Read-only preview of an assigned student's internship portfolio. Access is
     * granted only through the internship record whose faculty_id is the
     * requesting faculty user. The payload comes from PortfolioDataService, the
     * same builder the student Portfolio uses, so journals, attendance (FO-30)
     * and evaluations stay consistent. This GET performs no writes.
     */
    public function studentPortfolio(Request $request, int $userId, PortfolioDataService $portfolio)
    {
        $viewer = $request->user();
        $internship = $this->assignedInternshipForStudent($viewer, $userId);

        if (! $internship || ! InternshipAccess::canView($viewer, $internship)) {
            abort(403, 'Student is not assigned to you.');
        }

        $payload = $portfolio->payload($internship, $viewer);
        $payload['read_only'] = true;

        return response()->json($payload);
    }

    /**
     * The student's internship handled by this faculty user, preferring the
     * current internship over earlier ones. Null when no such assignment exists.
     */
    private function assignedInternshipForStudent(User $faculty, int $studentId): ?Internship
    {
        $current = InternshipStatuses::currentRelation();
        $placeholders = implode(',', array_fill(0, count($current), '?'));

        return Internship::inDepartment()
            ->where('student_id', $studentId)
            ->where('faculty_id', $faculty->id)
            ->whereHas('student', fn ($q) => $q->where('role', 'student'))
            ->orderByRaw("CASE WHEN status IN ({$placeholders}) THEN 0 ELSE 1 END", $current)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * PATCH /api/v1/faculty/students/{userId}/archive
     * Body: { archived: true|false } — soft-archive via users.is_active.
     */
    public function setStudentArchived(Request $request, int $userId)
    {
        $request->validate(['archived' => 'required|boolean']);

        $facultyId = $request->user()->id;
        $student = User::where('role', 'student')->findOrFail($userId);
        if (! User::inDepartment()->where('id', $userId)->exists()) {
            DepartmentScope::abortDifferentDepartment();
        }

        $sections = FacultySectionAssignment::where('faculty_user_id', $facultyId)->pluck('section');
        $assigned = $sections->contains($student->studentProfile?->section)
            || Internship::inDepartment()->where('faculty_id', $facultyId)
                ->where('student_id', $userId)
                ->exists();

        if (! $assigned) {
            return response()->json(['message' => 'You can only archive students assigned to you.'], 403);
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

    /**
     * GET /api/v1/faculty/students/{userId}/progress
     * Returns aggregated progress data for a single assigned student.
     */
    public function studentProgress(Request $request, int $userId)
    {
        $student = User::where('role', 'student')->with('studentProfile.program')->findOrFail($userId);
        if (! User::inDepartment()->where('id', $userId)->exists()) {
            DepartmentScope::abortDifferentDepartment();
        }

        $facultyId = $request->user()->id;
        $sections = FacultySectionAssignment::where('faculty_user_id', $facultyId)->pluck('section');
        $isAssigned = $sections->contains($student->studentProfile?->section)
            || Internship::inDepartment()->where('faculty_id', $facultyId)->where('student_id', $userId)->exists();

        if (! $isAssigned) {
            abort(403, 'Student is not assigned to you.');
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
                    'total' => 0,
                    'compliance_pct' => 0,
                    'complete' => false,
                    'label' => 'No Requirements',
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

        $journals = $internship->journals->filter(fn ($j) => ! $j->isSupervisorNote())->values();
        $documents = $internship->documents;
        $internFeedback = app(SupervisorFeedbackService::class)->serialize(
            $internship->journals->first(fn ($j) => $j->isSupervisorNote() && filled($j->supervisor_feedback))
        );

        $compliance = app(\App\Services\DocumentComplianceService::class)
            ->summaryForStudent($internship->student ?? $student, $internship);
        $docsSubmitted = $compliance['approved'] + $compliance['pending'];
        $docsApproved = $compliance['approved'];
        $docsTotal = $compliance['total'];

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
                'compliance_pct' => $compliance['pct'],
                'complete' => $compliance['complete'],
                'label' => $compliance['label'],
                'items' => collect($compliance['details'])->map(fn ($d) => [
                    'id' => $d['template_id'] ?? null,
                    'name' => $d['name'],
                    'status' => $d['status'],
                    'status_label' => $d['status_label'] ?? $d['status'],
                ])->values(),
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
            'supervisor_feedback' => $internFeedback,
            'attendance_logs' => $officialForm['fo30']['logs'] ?? [],
            'official_form' => $officialForm,
        ]);
    }

    /**
     * GET /api/v1/faculty/attendance
     * Read-only attendance monitoring for assigned students (not validation).
     * Optional: status, internship_id query filters.
     */
    public function attendance(Request $request)
    {
        $internshipIds = Internship::inDepartment()->where('faculty_id', $request->user()->id)->pluck('id');

        $query = AttendanceLog::whereIn('internship_id', $internshipIds)
            ->with(['internship.student.studentProfile', 'internship.company'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('internship_id')) {
            $internshipId = (int) $request->internship_id;
            if (! $internshipIds->contains($internshipId)) {
                return response()->json(['message' => 'Internship not assigned to you.'], 403);
            }
            $query->where('internship_id', $internshipId);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $page = $query->paginate(25);
        app(DtrWorkflowService::class)->decorateLogs(collect($page->items()));

        return ApiResponse::list($page);
    }

    public function journals(Request $request)
    {
        $internshipIds = Internship::inDepartment()->where('faculty_id', $request->user()->id)->pluck('id');
        $journals = JournalEntry::whereIn('internship_id', $internshipIds)
            ->academic()
            ->whereIn('status', ['submitted', 'approved', 'needs_revision'])
            ->with(['internship.student.studentProfile.program', 'internship.company'])
            ->orderByDesc('date')
            ->paginate(25);

        $journals->getCollection()->transform(function ($journal) {
            $student = $journal->internship?->student;
            $profile = $student?->studentProfile;
            $last = trim((string) ($profile?->last_name ?? ''));
            $first = trim((string) ($profile?->first_name ?? ''));
            $display = trim($last.($last !== '' && $first !== '' ? ', ' : '').$first);

            $journal->setAttribute('awaiting_supervisor', false);
            $journal->setAttribute('supervisor_validated', false);
            $journal->setAttribute('faculty_can_review', $journal->facultyCanReview());
            $journal->setAttribute('student_display_name', $display !== '' ? $display : NameParts::fromProfile($profile));
            $journal->setAttribute('program_name', $profile?->program?->name);
            $journal->setAttribute('student_number', $profile?->student_number ?: $student?->student_number);
            $journal->setAttribute('student_signature_path', $student ? SignatureCapture::profilePath($student) : null);

            $student = $journal->internship?->student;
            $profile = $student?->studentProfile;
            $last = trim((string) ($profile?->last_name ?? ''));
            $first = trim((string) ($profile?->first_name ?? ''));
            $studentName = ($last !== '' || $first !== '')
                ? trim($last.($last !== '' && $first !== '' ? ', ' : '').$first)
                : ($student?->student_number ?: $student?->email);

            $journal->setAttribute('student_name', $studentName ?: null);
            // One row per journal week (journal id); deadline + timing from the
            // same week the student submitted against.
            $timing = \App\Services\JournalDeadlineService::presentJournal($journal);
            $journal->setAttribute('range_display', $timing['range_display']);
            $journal->setAttribute('submitted_at_display', $timing['submitted_at_display']);
            $journal->setAttribute('deadline_display', $journal->deadline_at
                ? $journal->deadline_at->copy()->timezone(\App\Support\ManilaTime::TZ)->format('F j, Y, g:i A')
                : null);

            return $journal;
        });

        return ApiResponse::list($journals);
    }

    /** GET /api/v1/faculty/supervisor-feedback */
    public function supervisorFeedback(Request $request)
    {
        $internshipIds = Internship::inDepartment()->where('faculty_id', $request->user()->id)->pluck('id');
        $notes = JournalEntry::whereIn('internship_id', $internshipIds)
            ->where('status', SupervisorFeedbackService::NOTE_STATUS)
            ->whereNotNull('supervisor_feedback')
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

    public function reviewJournal(Request $request, int $id)
    {
        $request->validate(['action' => 'required|in:approved,needs_revision', 'feedback' => 'nullable|string|max:1000', 'score' => 'nullable|numeric|min:0|max:100']);
        $journal = JournalEntry::whereHas(
            'internship',
            fn ($q) => $q->inDepartment()->where('faculty_id', $request->user()->id)
        )->with('internship')->findOrFail($id);

        if ($journal->isSupervisorNote() || ! $journal->facultyCanReview()) {
            return response()->json(['message' => 'This journal is not available for faculty review.'], 422);
        }

        try {
            $journal = UniqueWrite::retry(fn () => DB::transaction(function () use ($request, $id) {
                $locked = JournalEntry::whereHas(
                    'internship',
                    fn ($q) => $q->inDepartment()->where('faculty_id', $request->user()->id)
                )->with('internship')->lockForUpdate()->findOrFail($id);

                if ($locked->isSupervisorNote() || ! $locked->facultyCanReview()) {
                    throw new \RuntimeException('This journal is not available for faculty review.');
                }

                $updateData = ['status' => $request->action, 'faculty_feedback' => $request->feedback, 'faculty_reviewed_by' => $request->user()->id, 'faculty_reviewed_at' => now()];
                if ($request->has('score') && $request->action === 'approved') {
                    $updateData['score'] = $request->score;
                } elseif ($request->action !== 'approved') {
                    $updateData['score'] = null;
                }

                $locked->update($updateData);

                return $locked->fresh('internship.student.studentProfile');
            }));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Notify student
        $studentId = $journal->internship?->student_id;
        if ($studentId) {
            $weekLabel = 'Week '.($journal->week_number ?? $journal->entry_number ?? '—');
            Notification::notify(
                $studentId,
                'journal_reviewed',
                $request->action === 'approved' ? 'Journal Approved by Faculty ✅' : 'Journal Needs Revision 🔄',
                $request->action === 'approved'
                    ? "Your {$weekLabel} journal was approved by your faculty supervisor."
                    : "Your {$weekLabel} journal needs revision: ".($request->feedback ?? 'Please check your entry.'),
                '/student/logbook',
                ['journal_id' => $journal->id, 'week_number' => $journal->week_number, 'action' => $request->action, 'feedback' => $request->feedback]
            );
        }

        audit_log($request->user()->id, 'faculty_review_journal', ['journal_id' => $id, 'action' => $request->action]);

        return response()->json(['message' => 'Journal '.$request->action.'.', 'journal' => $journal]);
    }

    public function studentJournalHistory(Request $request, int $studentId)
    {
        DepartmentScope::abortUnlessStudentInDepartment($request->user(), $studentId);

        $journals = JournalEntry::whereHas('internship', function ($q) use ($request, $studentId) {
            $q->inDepartment()->where('faculty_id', $request->user()->id)->where('student_id', $studentId);
        })
            ->academic()
            ->with(['internship.student.studentProfile.program', 'internship.company'])
            ->orderBy('week_number', 'asc')
            ->orderBy('entry_number', 'asc')
            ->get();

        $journals->transform(function ($journal) {
            $student = $journal->internship?->student;
            $journal->setAttribute(
                'student_signature_path',
                $student ? SignatureCapture::profilePath($student) : null
            );

            return $journal;
        });

        return response()->json($journals);
    }

    public function evaluations(Request $request)
    {
        $facultyId = $request->user()->id;
        $internshipIds = Internship::inDepartment()->where('faculty_id', $facultyId)->pluck('id');

        // Get unique available sections from assigned students for the frontend filter
        $availableSections = StudentProfile::whereHas('user.internshipsAsStudent', function ($q) use ($internshipIds) {
            $q->whereIn('id', $internshipIds);
        })->whereNotNull('section')->distinct()->pluck('section');

        // Faculty sees FO-24 (industry) plus their own faculty_eval records.
        // One row per student: the student's CURRENT internship as resolved by
        // EvaluationPeriod::currentInternshipFor() (latest open row, else latest
        // current row). Superseded/historical duplicates never repeat a student,
        // and the row the faculty approves is the row the student reads.
        $current = InternshipStatuses::currentRelation();
        $open = InternshipStatuses::openCurrent();
        $inCurrent = implode(',', array_fill(0, count($current), '?'));
        $inOpen = implode(',', array_fill(0, count($open), '?'));
        $query = Internship::inDepartment()
            ->where('faculty_id', $facultyId)
            ->whereIn('status', $current)
            ->whereRaw(
                "internships.id = (SELECT i2.id FROM internships i2 WHERE i2.student_id = internships.student_id AND i2.deleted_at IS NULL AND i2.status IN ({$inCurrent}) ORDER BY (i2.status IN ({$inOpen})) DESC, i2.id DESC LIMIT 1)",
                array_merge($current, $open)
            )
            ->with([
                'student.studentProfile.program',
                'company',
                'supervisor.supervisorProfile',
                'faculty.facultyProfile',
                'evaluations' => function ($q) {
                    $q->whereIn('form_type', ['FO-24', 'faculty_eval']);
                },
            ]);

        // Apply filters
        if ($request->filled('section')) {
            $query->whereHas('student.studentProfile', function ($q) use ($request) {
                $q->where('section', $request->input('section'));
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('student.studentProfile', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $internships = $query->get()->each(
            fn (Internship $i) => $i->setAttribute('evaluation_period', EvaluationPeriod::state($i))
        );

        return response()->json([
            'internships' => $internships,
            'available_sections' => $availableSections,
        ]);
    }

    /** POST /api/v1/faculty/evaluations/{internshipId}/approve-period */
    public function approveEvaluationPeriod(Request $request, int $internshipId)
    {
        $internship = Internship::inDepartment()
            ->where('faculty_id', $request->user()->id)
            ->find($internshipId);

        if (! $internship) {
            abort(403, 'Internship not assigned to you.');
        }

        // Approve only the internship the student actually reads; approving a
        // superseded/historical row would leave the student's forms locked.
        if (! EvaluationPeriod::isCurrentFor($internship)) {
            return response()->json([
                'message' => 'This is not the student\'s current internship. Refresh the list and approve the current one.',
                'current_internship_id' => EvaluationPeriod::currentInternshipFor((int) $internship->student_id)?->id,
            ], 409);
        }

        $internship = EvaluationPeriod::setApproved($internship, true, $request->user());

        audit_log($request->user()->id, 'approve_evaluation_period', [
            'internship_id' => $internship->id,
        ]);

        Notification::notify(
            (int) $internship->student_id,
            'evaluation_period_approved',
            'Evaluation period approved',
            'Your Faculty Supervisor approved the evaluation period. Your evaluation forms are now unlocked.',
            '/student/evaluations',
            ['internship_id' => $internship->id]
        );

        $state = EvaluationPeriod::state($internship);

        return response()->json([
            'message' => 'Evaluation period approved. Student and supervisor forms are now unlocked.',
            'internship_id' => $internship->id,
            'evaluation_period' => $state,
            'evaluation_period_status' => $internship->evaluation_period_status,
            'evaluation_period_approved' => true,
            'evaluation_period_approved_at' => $internship->evaluation_period_approved_at,
        ]);
    }

    /** Internships this faculty handles (authoritative assignment), keyed by id. */
    private function handledInternships(Request $request, ?array $ids = null)
    {
        return Internship::inDepartment()
            ->where('faculty_id', $request->user()->id)
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->with('student.studentProfile')
            ->get()
            ->keyBy('id');
    }

    /**
     * GET /api/v1/faculty/journal-weeks?internship_ids[]=
     * Authoritative journal weeks (same rule as Student journals / FO-31 / review
     * queue) for each requested internship this faculty handles, with the
     * week's journal and deadline. Unassigned internships are refused (403).
     */
    public function journalWeeks(Request $request, \App\Services\JournalDeadlineService $deadlines)
    {
        $data = $request->validate([
            'internship_ids' => 'nullable|array|max:200',
            'internship_ids.*' => 'integer',
        ]);

        $ids = isset($data['internship_ids'])
            ? collect($data['internship_ids'])->map(fn ($id) => (int) $id)->unique()->values()->all()
            : null;
        $internships = $this->handledInternships($request, $ids);

        if ($ids !== null && $internships->count() !== count($ids)) {
            abort(403, 'You can only view journal weeks for students assigned to you.');
        }

        return response()->json([
            'data' => $internships->values()->map(function (Internship $internship) use ($deadlines) {
                $profile = $internship->student?->studentProfile;

                return [
                    'internship_id' => $internship->id,
                    'student_id' => $internship->student_id,
                    'student_name' => NameParts::fromProfile($profile) ?: ($internship->student?->username ?? ''),
                    'student_number' => $profile?->student_number ?? $internship->student?->student_number,
                    'start_date' => $internship->start_date?->toDateString(),
                    'end_date' => $internship->end_date?->toDateString(),
                    'weeks' => $deadlines->weeksFor($internship),
                ];
            }),
            'timezone' => \App\Support\ManilaTime::TZ,
        ]);
    }

    /**
     * GET /api/v1/faculty/journal-deadlines?internship_id=
     * Weekly Journal deadlines for internships this faculty handles.
     */
    public function journalDeadlines(Request $request, \App\Services\JournalPeriodValidator $periods)
    {
        $request->validate(['internship_id' => 'nullable|integer']);

        $internships = $this->handledInternships($request);
        $query = \App\Models\JournalDeadline::query()
            ->whereIn('internship_id', $internships->keys())
            ->orderBy('internship_id')
            ->orderBy('week_number');
        if ($request->filled('internship_id')) {
            $query->where('internship_id', (int) $request->input('internship_id'));
        }

        return response()->json([
            'data' => $query->get()->map(fn ($d) => \App\Services\JournalDeadlineService::present(
                $d,
                $periods->weekWindow($internships->get($d->internship_id), (int) $d->week_number)
            ))->values(),
            'timezone' => \App\Support\ManilaTime::TZ,
        ]);
    }

    /**
     * POST /api/v1/faculty/journal-deadlines
     * Body: { deadlines: [{ internship_id, week_number, due_at: "YYYY-MM-DDTHH:MM" (Asia/Manila) }] }
     *   (legacy: { internship_ids: [], week_number, due_at } is expanded per internship)
     * Every row must target an internship assigned to this faculty (else 403) and a
     * week that exists for that student's internship (else 422). All or nothing.
     */
    public function setJournalDeadline(Request $request, \App\Services\JournalDeadlineService $deadlines)
    {
        $maxWeeks = \App\Services\JournalPeriodValidator::MAX_WEEKS;
        if (! $request->has('deadlines') && $request->has('internship_ids')) {
            $request->validate([
                'internship_ids' => 'required|array|min:1|max:200',
                'internship_ids.*' => 'required|integer|distinct',
                'week_number' => "required|integer|min:1|max:{$maxWeeks}",
                'due_at' => 'required|date|after:2000-01-01|before:2100-01-01',
            ]);
            $request->merge(['deadlines' => collect($request->input('internship_ids'))->map(fn ($id) => [
                'internship_id' => $id,
                'week_number' => $request->input('week_number'),
                'due_at' => $request->input('due_at'),
            ])->all()]);
        }

        $data = $request->validate([
            'deadlines' => 'required|array|min:1|max:200',
            'deadlines.*.internship_id' => 'required|integer',
            'deadlines.*.week_number' => "required|integer|min:1|max:{$maxWeeks}",
            'deadlines.*.due_at' => 'required|date|after:2000-01-01|before:2100-01-01',
        ]);

        $rows = collect($data['deadlines'])->map(fn ($row) => [
            'internship_id' => (int) $row['internship_id'],
            'week_number' => (int) $row['week_number'],
            'due_at' => \App\Services\JournalDeadlineService::parseManila((string) $row['due_at']),
        ]);
        $pairs = $rows->map(fn ($r) => $r['internship_id'].':'.$r['week_number']);
        if ($pairs->unique()->count() !== $pairs->count()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'deadlines' => ['Each student week can only appear once per save.'],
            ]);
        }

        $ids = $rows->pluck('internship_id')->unique()->values()->all();
        $internships = $this->handledInternships($request, $ids);
        if ($internships->count() !== count($ids)) {
            abort(403, 'You can only set journal deadlines for students assigned to you.');
        }

        $saved = $deadlines->saveRows($internships, $rows->all(), $request->user());

        audit_log($request->user()->id, 'set_journal_deadline', [
            'rows' => $rows->map(fn ($r) => ['internship_id' => $r['internship_id'], 'week_number' => $r['week_number']])->all(),
        ]);

        foreach ($saved as $deadline) {
            $internship = $internships->get($deadline['internship_id']);
            $range = $deadline['week_range_display'] ? " ({$deadline['week_range_display']})" : '';
            Notification::notify(
                (int) $internship->student_id,
                'journal_deadline_set',
                'Weekly journal deadline',
                "Week {$deadline['week_number']} journal{$range} is due {$deadline['due_at_display']} (Asia/Manila).",
                '/student/logbook',
                ['internship_id' => $internship->id, 'week_number' => $deadline['week_number']]
            );
        }

        return response()->json([
            'message' => 'Journal deadline saved.',
            'data' => $saved,
        ]);
    }

    /** DELETE /api/v1/faculty/journal-deadlines/{id} */
    public function deleteJournalDeadline(Request $request, int $id, \App\Services\JournalDeadlineService $deadlines)
    {
        $deadline = \App\Models\JournalDeadline::with('internship')->findOrFail($id);
        $internship = $deadline->internship;

        if (! $internship
            || (int) $internship->faculty_id !== (int) $request->user()->id
            || ! Internship::inDepartment()->whereKey($internship->id)->exists()) {
            abort(403, 'You can only manage journal deadlines for students assigned to you.');
        }

        $deadlines->remove($deadline);
        audit_log($request->user()->id, 'delete_journal_deadline', ['deadline_id' => $id]);

        return response()->json(['message' => 'Journal deadline removed.']);
    }

    /**
     * POST /api/v1/faculty/evaluations/{internshipId}/release-performance
     * Body: { released: bool } (default true)
     *
     * The internship's assigned Faculty authorizes (or withdraws) Student
     * visibility of the FO-24 Performance Evaluation details.
     */
    public function releasePerformanceEvaluation(Request $request, int $internshipId)
    {
        $data = $request->validate(['released' => 'sometimes|boolean']);
        $release = $data['released'] ?? true;

        $internship = Internship::inDepartment()
            ->where('faculty_id', $request->user()->id)
            ->find($internshipId);

        if (! $internship) {
            abort(403, 'Internship not assigned to you.');
        }

        $evaluations = Evaluation::where('internship_id', $internship->id)
            ->where('form_type', 'FO-24')
            ->whereNotNull('submitted_at')
            ->get();

        if ($evaluations->isEmpty()) {
            return response()->json([
                'message' => 'The industry supervisor has not submitted the Performance Evaluation yet.',
            ], 422);
        }

        foreach ($evaluations as $evaluation) {
            $evaluation->forceFill([
                'released_to_student_at' => $release ? ($evaluation->released_to_student_at ?? now()) : null,
                'released_to_student_by' => $release ? ($evaluation->released_to_student_by ?? $request->user()->id) : null,
            ])->save();
        }

        audit_log($request->user()->id, $release ? 'release_performance_evaluation' : 'withdraw_performance_evaluation', [
            'internship_id' => $internship->id,
            'evaluation_ids' => $evaluations->pluck('id')->all(),
        ]);

        if ($release) {
            Notification::notify(
                (int) $internship->student_id,
                'performance_evaluation_released',
                'Performance evaluation released',
                'Your Faculty Supervisor released your Student Internship Performance Evaluation (FO-24). You can now view the details.',
                '/student/evaluations',
                ['internship_id' => $internship->id]
            );
        }

        return response()->json([
            'message' => $release
                ? 'Performance Evaluation released. The student can now view the details.'
                : 'Performance Evaluation hidden from the student.',
            'internship_id' => $internship->id,
            'released' => $release,
            'evaluations' => $evaluations->map(fn ($e) => $e->fresh())->values(),
        ]);
    }

    /** POST /api/v1/faculty/evaluations/{internshipId} */
    public function submitEvaluation(Request $request, int $internshipId)
    {
        $request->validate([
            'evaluation_period' => 'required|in:midterm,final',
            'overall_score' => 'required|numeric|min:0|max:100',
            'general_comments' => 'nullable|string|max:2000',
        ]);

        $internship = Internship::inDepartment()
            ->where('faculty_id', $request->user()->id)
            ->with(['student.studentProfile'])
            ->find($internshipId);

        if (! $internship) {
            abort(403, 'Internship not assigned to you.');
        }

        if (in_array($internship->status, ['terminated', 'withdrawn', 'cancelled'], true)) {
            return response()->json(['message' => 'This internship is not eligible for faculty evaluation.'], 422);
        }

        $period = $request->input('evaluation_period');
        $score = round((float) $request->input('overall_score'), 2);
        $formType = 'faculty_eval';
        $rating = match (true) {
            $score >= 96 => 'Excellent',
            $score >= 90 => 'Very Good',
            $score >= 85 => 'Good',
            $score >= 80 => 'Fair',
            $score >= 75 => 'Passed',
            default => 'Failed',
        };

        $eval = null;
        $created = false;
        $attempt = 0;
        while ($attempt < 4) {
            try {
                [$eval, $created] = DB::transaction(function () use ($request, $internship, $period, $formType, $score, $rating) {
                    Internship::whereKey($internship->id)->lockForUpdate()->firstOrFail();

                    $eval = Evaluation::withTrashed()
                        ->where('internship_id', $internship->id)
                        ->where('evaluator_type', 'faculty')
                        ->where('evaluation_period', $period)
                        ->where('form_type', $formType)
                        ->first();

                    $created = false;
                    if ($eval) {
                        if ($eval->trashed()) {
                            $eval->restore();
                        }
                        $eval->fill([
                            'responses' => ['overall' => $score],
                            'total_score' => $score,
                            'average_score' => $score,
                            'rating' => $rating,
                            'general_comments' => $request->input('general_comments'),
                            'evaluated_by' => $request->user()->id,
                            'submitted_at' => now(),
                        ]);
                    } else {
                        $eval = new Evaluation([
                            'internship_id' => $internship->id,
                            'evaluator_type' => 'faculty',
                            'evaluation_period' => $period,
                            'form_type' => $formType,
                            'responses' => ['overall' => $score],
                            'total_score' => $score,
                            'average_score' => $score,
                            'rating' => $rating,
                            'general_comments' => $request->input('general_comments'),
                            'evaluated_by' => $request->user()->id,
                            'submitted_at' => now(),
                        ]);
                        $created = true;
                    }

                    $eval->save();

                    $sig = SignatureCapture::profilePath($request->user());
                    if ($sig && ! $eval->signature_path) {
                        $eval->signature_path = $sig;
                        $eval->signer_name = $eval->signer_name ?: NameParts::fromProfile($request->user()->facultyProfile);
                        $eval->save();
                    }

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
                    ->where('evaluator_type', 'faculty')
                    ->where('evaluation_period', $period)
                    ->where('form_type', $formType)
                    ->firstOrFail();
                $created = false;
                break;
            }
        }

        if ($created && $internship->student_id) {
            Notification::notify(
                (int) $internship->student_id,
                'evaluation_submitted',
                'Faculty evaluation submitted',
                'Your faculty supervisor submitted a '.$period.' evaluation.',
                '/student/evaluations',
                ['evaluation_id' => $eval->id, 'internship_id' => $internshipId, 'period' => $period]
            );
        }

        audit_log($request->user()->id, 'submit_faculty_evaluation', [
            'internship_id' => $internshipId,
            'period' => $period,
            'evaluation_id' => $eval->id,
        ]);

        return response()->json(['message' => 'Faculty evaluation submitted successfully.', 'evaluation' => $eval], 201);
    }

    public function feedback(Request $request)
    {
        $internshipIds = Internship::inDepartment()->where('faculty_id', $request->user()->id)->pluck('id');
        $journals = JournalEntry::whereIn('internship_id', $internshipIds)->whereNotNull('faculty_feedback')->with('internship.student.studentProfile')->orderByDesc('faculty_reviewed_at')->paginate(20);

        return ApiResponse::list($journals);
    }

    public function submitFeedback(Request $request, int $internshipId)
    {
        $request->validate(['feedback' => 'required|string|min:5|max:1000']);
        $internship = Internship::findOrFail($internshipId);
        if (! Internship::inDepartment()->where('id', $internshipId)->exists()) {
            DepartmentScope::abortDifferentDepartment();
        }
        if ((int) $internship->faculty_id !== (int) $request->user()->id) {
            abort(403, 'Internship not assigned to you.');
        }
        $journal = $internship->journals()->latest('date')->first();
        if ($journal) {
            $journal->update(['faculty_feedback' => $request->feedback, 'faculty_reviewed_by' => $request->user()->id, 'faculty_reviewed_at' => now()]);
        }

        return response()->json(['message' => 'Feedback submitted.']);
    }

    /** GET /api/v1/faculty/documents */
    public function documents(Request $request)
    {
        $facultyId = $request->user()->id;

        $internshipIds = Internship::inDepartment()->where('faculty_id', $facultyId)->pluck('id');

        $docs = Document::whereIn('internship_id', $internshipIds)
            ->with(['internship.student.studentProfile', 'attachments'])
            ->whereIn('status', ['pending', 'pending_review', 'under_review', 'pending_faculty', 'resubmitted'])
            ->orderByDesc('submitted_at')
            ->paginate(25);

        return ApiResponse::list($docs);
    }

    /** GET /api/v1/faculty/reports/student-summary — assigned students only */
    public function reportStudentSummary(Request $request)
    {
        $facultyId = $request->user()->id;
        $sections = FacultySectionAssignment::where('faculty_user_id', $facultyId)->pluck('section');

        $users = User::inDepartment()->where('role', 'student')
            ->where(function ($q) use ($facultyId, $sections) {
                $q->whereHas('studentProfile', function ($p) use ($sections) {
                    $p->whereIn('section', $sections);
                })
                    ->orWhereHas('internshipsAsStudent', function ($i) use ($facultyId) {
                        $i->where('faculty_id', $facultyId);
                    });
            })
            ->with([
                'studentProfile.program',
                'activeInternship.company',
                'activeInternship' => function ($q) {
                    $q->withCount([
                        'attendance as validated_days' => fn ($a) => $a->where('status', 'validated'),
                        'journals as approved_journals' => fn ($j) => $j->where('status', 'approved'),
                        'documents as approved_docs' => fn ($d) => $d->where('status', 'approved'),
                    ]);
                },
            ])
            ->get();

        $students = $users->map(function ($u) {
            $i = $u->activeInternship;
            $p = $u->studentProfile;
            $progress = $i
                ? InternshipProgressService::snapshot($i)
                : [
                    'hours_rendered' => 0.0,
                    'target_hours' => ProgramRequirementService::targetHoursForProfile($p),
                    'progress_pct' => 0.0,
                    'company_name' => null,
                ];
            $compliance = $i
                ? app(\App\Services\DocumentComplianceService::class)->summaryForStudent($u, $i)
                : ['approved' => 0, 'total' => 0, 'pct' => 0];

            return [
                'student_name' => NameParts::fromProfile($p) ?: trim(($p->last_name ?? '').', '.($p->first_name ?? '')),
                'student_number' => $u->username,
                'program' => $p->program?->name ?? $i?->program ?? '—',
                'company' => $progress['company_name'] ?? $i?->company?->company_name ?? '—',
                'status' => $i?->status ?? 'unplaced',
                'hours_rendered' => $progress['hours_rendered'],
                'target_hours' => $progress['target_hours'],
                'progress_pct' => $progress['progress_pct'],
                'validated_days' => $i?->validated_days ?? 0,
                'approved_journals' => $i?->approved_journals ?? 0,
                'approved_docs' => $compliance['approved'],
                'required_docs' => $compliance['total'],
                'compliance_pct' => $compliance['pct'],
                'start_date' => $i?->start_date?->toDateString(),
                'end_date' => $i?->end_date?->toDateString(),
                'final_grade' => $i?->final_grade,
            ];
        });

        return response()->json([
            'students' => $students->sortBy('status')->values(),
            'docs_total' => null,
            'generated_at' => now()->toDateTimeString(),
        ]);
    }

    /** GET /api/v1/faculty/reports/compliance */
    public function reportCompliance(Request $request)
    {
        $facultyId = $request->user()->id;
        $sections = FacultySectionAssignment::where('faculty_user_id', $facultyId)->pluck('section');

        $users = User::inDepartment()->where('role', 'student')
            ->where(function ($q) use ($facultyId, $sections) {
                $q->whereHas('studentProfile', function ($p) use ($sections) {
                    $p->whereIn('section', $sections);
                })
                    ->orWhereHas('internshipsAsStudent', function ($i) use ($facultyId) {
                        $i->where('faculty_id', $facultyId);
                    });
            })
            ->with(['studentProfile.program', 'activeInternship'])
            ->get();

        $report = app(\App\Services\DocumentComplianceService::class)->reportForStudents($users);

        return response()->json($report);
    }

    /** GET /api/v1/faculty/reports/performance */
    public function reportPerformance(Request $request)
    {
        $facultyId = $request->user()->id;
        $sections = FacultySectionAssignment::where('faculty_user_id', $facultyId)->pluck('section');

        $users = User::inDepartment()->where('role', 'student')
            ->where(function ($q) use ($facultyId, $sections) {
                $q->whereHas('studentProfile', function ($p) use ($sections) {
                    $p->whereIn('section', $sections);
                })
                    ->orWhereHas('internshipsAsStudent', function ($i) use ($facultyId) {
                        $i->where('faculty_id', $facultyId);
                    });
            })
            ->with(['studentProfile.program', 'activeInternship'])
            ->get();

        $byProgram = $users
            ->groupBy(function ($u) {
                foreach ([
                    $u->studentProfile?->program?->name,
                    $u->activeInternship?->program,
                ] as $value) {
                    $value = trim((string) $value);
                    if ($value !== '') {
                        return $value;
                    }
                }

                return 'Unknown';
            })
            ->map(function ($rows, $program) {
                $completedCount = $rows->filter(fn ($u) => $u->activeInternship?->status === 'completed')->count();
                $avgHours = $rows->avg(fn ($u) => $u->activeInternship?->computeTotalHours() ?? 0);
                $avgGrade = $rows->avg(fn ($u) => $u->activeInternship?->final_grade);

                return [
                    'program' => $program,
                    'total' => $rows->count(),
                    'completed' => $completedCount,
                    'avg_hours' => round((float) $avgHours, 2),
                    'avg_grade' => round((float) $avgGrade, 2),
                ];
            })
            ->sortBy('program')
            ->values();

        $internshipIds = Internship::inDepartment()->where('faculty_id', $request->user()->id)->pluck('id');
        $evalAvg = Evaluation::whereIn('internship_id', $internshipIds)
            ->selectRaw('
                evaluator_type,
                AVG(average_score) as avg_overall
            ')
            ->groupBy('evaluator_type')
            ->get();

        return response()->json([
            'by_program' => $byProgram,
            'eval_averages' => $evalAvg,
            'generated_at' => now()->toDateTimeString(),
        ]);
    }

    /** GET /api/v1/faculty/supervisors */
    public function supervisors(Request $request, SupervisorDirectoryService $directory)
    {
        return $directory->listFor($request->user(), SupervisorDirectoryService::SCOPE_FACULTY);
    }

    /** GET /api/v1/faculty/supervisors/{id} */
    public function showSupervisor(Request $request, int $id, SupervisorDirectoryService $directory)
    {
        return $directory->showFor($request->user(), SupervisorDirectoryService::SCOPE_FACULTY, $id);
    }
}
