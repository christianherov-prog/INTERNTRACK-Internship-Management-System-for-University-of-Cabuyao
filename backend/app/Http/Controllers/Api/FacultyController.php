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
use App\Services\ProgramRequirementService;
use App\Services\SupervisorFeedbackService;
use App\Support\ApiResponse;
use App\Support\DepartmentScope;
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

        $transformed = $paginator->through(function ($student) {
            $internship = $student->activeInternship;
            $profile = $student->studentProfile;
            $profile?->loadMissing('program');
            $programName = is_string($internship?->program) && $internship->program !== ''
                ? $internship->program
                : ($profile?->getRelation('program')?->name ?? '—');

            return [
                'id' => $internship?->id ?? 0,
                'user_id' => $student->id,
                'student_id' => $student->id,
                'status' => $internship?->status ?? 'unplaced',
                'program' => $programName,
                'section' => $profile?->section ?? '—',
                'company' => $internship?->company?->company_name ?? null,
                'supervisor' => $internship?->supervisor?->supervisorProfile?->full_name ?? null,
                'student' => [
                    'id' => $student->id,
                    'username' => $student->username,
                    'email' => $student->email,
                    'sex' => collect([$student->sex, $profile?->sex])->first(fn ($s) => ! empty($s)) ?? '—',
                    'is_active' => $student->is_active,
                    'student_profile' => $profile,
                ],
                'attendance_logs' => $internship?->attendance?->sortBy('date')->values() ?? [],
            ];
        });

        return ApiResponse::list($transformed);
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

        $journals = $internship->journals->filter(fn ($j) => ! $j->isSupervisorNote())->values();
        $documents = $internship->documents;
        $internFeedback = app(SupervisorFeedbackService::class)->serialize(
            $internship->journals->first(fn ($j) => $j->isSupervisorNote() && filled($j->supervisor_feedback))
        );

        $docsSubmitted = $documents->whereNotNull('file_path')->count();
        $docsApproved = $documents->where('status', 'approved')->count();
        $docsTotal = $documents->count();

        $journalCount = $journals->count();
        $lastJournal = $journals->sortByDesc('created_at')->first();

        // Get all attendance logs for the DTR preview
        $attendanceLogs = AttendanceLog::where('internship_id', $internship->id)
            ->orderBy('date', 'asc')
            ->get();

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
            'supervisor_feedback' => $internFeedback,
            'attendance_logs' => $attendanceLogs,
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
            ->with(['internship.student.studentProfile', 'internship.company'])
            ->orderByDesc('date')
            ->paginate(25);

        $journals->getCollection()->transform(function ($journal) {
            $journal->setAttribute('awaiting_supervisor', false);
            $journal->setAttribute('supervisor_validated', false);
            $journal->setAttribute('faculty_can_review', $journal->facultyCanReview());

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
            ->orderBy('week_number', 'asc')
            ->orderBy('entry_number', 'asc')
            ->get();

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
        $query = Internship::inDepartment()
            ->where('faculty_id', $facultyId)
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

        $internships = $query->get();

        return response()->json([
            'internships' => $internships,
            'available_sections' => $availableSections,
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
        $request->validate(['feedback' => 'required|string|min:5']);
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
                'approved_docs' => $i?->approved_docs ?? 0,
                'required_docs' => RequiredDocuments::count(),
                'start_date' => $i?->start_date?->toDateString(),
                'end_date' => $i?->end_date?->toDateString(),
                'final_grade' => $i?->final_grade,
            ];
        });

        return response()->json([
            'students' => $students->sortBy('status')->values(),
            'docs_total' => RequiredDocuments::count(),
            'generated_at' => now()->toDateTimeString(),
        ]);
    }

    /** GET /api/v1/faculty/reports/compliance */
    public function reportCompliance(Request $request)
    {
        $requiredTypes = RequiredDocuments::types();
        $requiredCount = RequiredDocuments::count();
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
            ->with(['studentProfile.program', 'activeInternship.documents'])
            ->get();

        $rows = $users->map(function ($u) use ($requiredCount, $requiredTypes) {
            $i = $u->activeInternship;
            $approvedDocsCount = $i ? $i->documents->where('status', 'approved')->count() : 0;
            $approvedDocTypes = $i ? $i->documents->where('status', 'approved')->pluck('document_type') : collect([]);

            return [
                'student_name' => trim((optional($u->studentProfile)->last_name ?? '').', '.(optional($u->studentProfile)->first_name ?? '')),
                'program' => $u->studentProfile?->program?->name ?? '-',
                'approved_docs' => $approvedDocsCount,
                'required_docs' => $requiredCount,
                'compliance_pct' => $requiredCount > 0 ? round($approvedDocsCount / $requiredCount * 100) : 0,
                'missing_docs' => collect($requiredTypes)->diff($approvedDocTypes)->values(),
            ];
        });

        return response()->json([
            'rows' => $rows,
            'required_types' => $requiredTypes,
            'generated_at' => now()->toDateTimeString(),
        ]);
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
                $avgHours = $rows->avg(fn ($u) => $u->activeInternship?->total_hours_rendered ?? 0);
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
}
