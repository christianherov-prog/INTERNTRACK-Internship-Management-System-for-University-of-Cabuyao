<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Internship;
use App\Models\Notification;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\RequiredDocuments;
use App\Support\SignatureCapture;
use Illuminate\Http\Request;

class FacultyController extends Controller
{
    public function dashboard(Request $request)
    {
        $facultyId  = $request->user()->id;
        $sections   = \App\Models\FacultySectionAssignment::where('faculty_user_id', $facultyId)->pluck('section');
        $assignedStudentsCount = User::where('role', 'student')
            ->where(function ($q) use ($facultyId, $sections) {
                $q->whereHas('studentProfile', function ($p) use ($sections) {
                    $p->whereIn('section', $sections);
                })
                ->orWhereHas('internshipsAsStudent', function ($i) use ($facultyId) {
                    $i->where('faculty_id', $facultyId);
                });
            })
            ->where('is_active', true)
            ->count();

        $internships = Internship::where('faculty_id', $facultyId)
            ->whereIn('status', ['ongoing', 'active', 'for_evaluation'])
            ->with('student.studentProfile')
            ->get();

        $internshipIds = $internships->pluck('id');

        $pendingJournals = $internshipIds->isEmpty()
            ? 0
            : \App\Models\JournalEntry::whereIn('internship_id', $internshipIds)
                ->where('status', 'submitted')
                ->count();

        $pendingEvals = $internships->whereNotIn('id',
            \App\Models\Evaluation::where('evaluator_type', 'faculty')->pluck('internship_id')->toArray()
        )->count();

        // Recent activity — last 5 journals or feedback the faculty has acted on
        $recentJournals = \App\Models\JournalEntry::whereIn('internship_id', $internshipIds)
            ->whereIn('status', ['approved', 'needs_revision'])
            ->whereNotNull('faculty_reviewed_at')
            ->with(['internship.student.studentProfile'])
            ->latest('faculty_reviewed_at')
            ->limit(5)
            ->get()
            ->map(fn($j) => [
                'type'      => 'journal',
                'action'    => $j->status,
                'student'   => $j->internship->student->studentProfile ? trim("{$j->internship->student->studentProfile->first_name} {$j->internship->student->studentProfile->last_name}") : $j->internship->student->username,
                'week'      => $j->week_number ?? $j->entry_number,
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
                'assigned_students'   => $assignedStudentsCount,
                'pending_journals'    => $pendingJournals,
                'pending_evaluations' => $pendingEvals,
            ],
            'recent_activity' => $recentActivity,
            'announcements'   => $announcements,
        ]);
    }

    public function assignedStudents(Request $request)
    {
        $facultyId = $request->user()->id;
        $sections = \App\Models\FacultySectionAssignment::where('faculty_user_id', $facultyId)->pluck('section');

        $query = User::where('role', 'student')
            ->where(function ($q) use ($facultyId, $sections) {
                $q->whereHas('studentProfile', function ($p) use ($sections) {
                    $p->whereIn('section', $sections);
                })
                ->orWhereHas('internshipsAsStudent', function ($i) use ($facultyId) {
                    $i->where('faculty_id', $facultyId);
                });
            })
            ->with(['studentProfile', 'activeInternship.company', 'activeInternship.supervisor.supervisorProfile']);

        if ($request->boolean('archived')) {
            $query->where('is_active', false);
        } else {
            $query->where('is_active', true);
        }

        $paginator = $query->paginate(20);

        $transformed = $paginator->through(function ($student) {
            $internship = $student->activeInternship;
            $profile = $student->studentProfile;

            return [
                'id' => $internship?->id ?? 0,
                'user_id' => $student->id,
                'student_id' => $student->id,
                'status' => $internship?->status ?? 'unplaced',
                'program' => $internship?->program ?? $profile?->course_name ?? $profile?->program ?? '—',
                'section' => $profile?->section ?? '—',
                'company' => $internship?->company ?? null,
                'supervisor' => $internship?->supervisor ?? null,
                'student' => [
                    'id' => $student->id,
                    'username' => $student->username,
                    'email' => $student->email,
                    'sex' => collect([$student->sex, $profile?->sex])->first(fn($s) => !empty($s)) ?? '—',
                    'is_active' => $student->is_active,
                    'student_profile' => $profile,
                ],
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

        $assigned = Internship::where('faculty_id', $request->user()->id)
            ->where('student_id', $userId)
            ->exists();

        if (!$assigned) {
            return response()->json(['message' => 'You can only archive students assigned to you.'], 403);
        }

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

    /**
     * GET /api/v1/faculty/students/{userId}/progress
     * Returns aggregated progress data for a single assigned student.
     */
    public function studentProgress(Request $request, int $userId)
    {
        $student = User::where('role', 'student')->with('studentProfile')->findOrFail($userId);
        
        $facultyId = $request->user()->id;
        $sections = \App\Models\FacultySectionAssignment::where('faculty_user_id', $facultyId)->pluck('section');
        $isAssigned = $sections->contains($student->studentProfile?->section)
            || Internship::where('faculty_id', $facultyId)->where('student_id', $userId)->exists();

        if (!$isAssigned) {
            abort(403, 'Student is not assigned to you.');
        }

        $internship = Internship::where('student_id', $userId)
            ->with(['company', 'supervisor.supervisorProfile', 'documents', 'journals'])
            ->latest()
            ->first();

        if (!$internship) {
            return response()->json([
                'student'         => [
                    'id'            => $student->id,
                    'name'          => $student->studentProfile?->full_name ?? $student->username,
                    'student_number'=> $student->studentProfile?->student_number,
                    'program'       => $student->studentProfile?->program ?? $student->studentProfile?->course_name,
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
                    'total'      => RequiredDocuments::count(),
                    'items'      => [],
                ],
                'journals'        => [
                    'count'       => 0,
                    'last_date'   => null,
                    'last_status' => null,
                    'items'       => [],
                ],
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

        return response()->json([
            'student'         => [
                'id'            => $internship->student->id,
                'name'          => $internship->student->studentProfile?->full_name ?? $internship->student->username,
                'student_number'=> $internship->student->studentProfile?->student_number,
                'program'       => $internship->student->studentProfile?->program,
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
                    'status'       => $j->status,
                    'accomplishment' => $j->activities_summary,
                    'difficulties'   => $j->challenges,
                    'insights'       => $j->learnings,
                ])->values(),
            ],
        ]);
    }

    /**
     * GET /api/v1/faculty/attendance
     * Read-only attendance monitoring for assigned students (not validation).
     * Optional: status, internship_id query filters.
     */
    public function attendance(Request $request)
    {
        $internshipIds = Internship::where('faculty_id', $request->user()->id)->pluck('id');

        $query = \App\Models\AttendanceLog::whereIn('internship_id', $internshipIds)
            ->with(['internship.student.studentProfile', 'internship.company'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('internship_id')) {
            $internshipId = (int) $request->internship_id;
            if (!$internshipIds->contains($internshipId)) {
                return response()->json(['message' => 'Internship not assigned to you.'], 403);
            }
            $query->where('internship_id', $internshipId);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return ApiResponse::list($query->paginate(25));
    }

    public function journals(Request $request)
    {
        $internshipIds = Internship::where('faculty_id', $request->user()->id)->pluck('id');
        $journals = \App\Models\JournalEntry::whereIn('internship_id', $internshipIds)
            ->whereIn('status', ['submitted', 'approved', 'needs_revision'])
            ->with(['internship.student.studentProfile'])
            ->orderByDesc('date')
            ->paginate(25);
        return ApiResponse::list($journals);
    }

    public function reviewJournal(Request $request, int $id)
    {
        $request->validate(['action' => 'required|in:approved,needs_revision', 'feedback' => 'nullable|string|max:1000']);
        $journal = \App\Models\JournalEntry::whereHas('internship', fn($q) => $q->where('faculty_id', $request->user()->id))->findOrFail($id);
        $journal->update(['status' => $request->action, 'faculty_feedback' => $request->feedback, 'faculty_reviewed_by' => $request->user()->id, 'faculty_reviewed_at' => now()]);

        // Notify student
        $studentId = $journal->internship?->student_id;
        if ($studentId) {
            $weekLabel = 'Week ' . ($journal->week_number ?? $journal->entry_number ?? '—');
            Notification::notify(
                $studentId,
                'journal_reviewed',
                $request->action === 'approved' ? "Journal Approved by Faculty ✅" : "Journal Needs Revision 🔄",
                $request->action === 'approved'
                    ? "Your {$weekLabel} journal was approved by your faculty supervisor."
                    : "Your {$weekLabel} journal needs revision: " . ($request->feedback ?? 'Please check your entry.'),
                '/student/logbook',
                ['journal_id' => $journal->id, 'week_number' => $journal->week_number, 'action' => $request->action, 'feedback' => $request->feedback]
            );
        }

        audit_log($request->user()->id, 'faculty_review_journal', ['journal_id' => $id, 'action' => $request->action]);
        return response()->json(['message' => 'Journal ' . $request->action . '.', 'journal' => $journal]);
    }

    public function evaluations(Request $request)
    {
        $internshipIds = Internship::where('faculty_id', $request->user()->id)->pluck('id');
        $evaluations   = \App\Models\Evaluation::whereIn('internship_id', $internshipIds)->where('evaluator_type', 'faculty')->with('internship.student.studentProfile')->get();
        $pending       = Internship::whereIn('id', $internshipIds)->whereNotIn('id', $evaluations->pluck('internship_id'))->with('student.studentProfile')->get();
        return ApiResponse::groups(['completed' => $evaluations, 'pending' => $pending]);
    }

    public function submitEvaluation(Request $request, int $internshipId)
    {
        $request->validate([
            'evaluation_period' => 'required|in:midterm,final',
            'technical_skills' => 'required|numeric|min:1|max:5',
            'communication_skills' => 'required|numeric|min:1|max:5',
            'teamwork' => 'required|numeric|min:1|max:5',
            'initiative' => 'required|numeric|min:1|max:5',
            'work_ethics' => 'required|numeric|min:1|max:5',
            'attendance_punctuality' => 'required|numeric|min:1|max:5',
            'adaptability' => 'required|numeric|min:1|max:5',
            'problem_solving' => 'required|numeric|min:1|max:5',
            'general_comments' => 'nullable|string',
        ]);

        $sig = SignatureCapture::fromRequest($request, 'signatures/evaluations');

        $internship = Internship::where('faculty_id', $request->user()->id)->findOrFail($internshipId);
        $eval = new \App\Models\Evaluation([
            'evaluation_period' => $request->input('evaluation_period'),
            'technical_skills' => $request->input('technical_skills'),
            'communication_skills' => $request->input('communication_skills'),
            'teamwork' => $request->input('teamwork'),
            'initiative' => $request->input('initiative'),
            'work_ethics' => $request->input('work_ethics'),
            'attendance_punctuality' => $request->input('attendance_punctuality'),
            'adaptability' => $request->input('adaptability'),
            'problem_solving' => $request->input('problem_solving'),
            'general_comments' => $request->input('general_comments'),
            'signer_name' => $sig['signer_name'],
            'signature_path' => $sig['signature_path'],
            'signed_at' => $sig['signed_at'],
        ]);
        $eval->internship_id = $internship->id;
        $eval->evaluator_type = 'faculty';
        $eval->evaluated_by = $request->user()->id;
        $eval->submitted_at = now();
        $eval->computeScores();
        $eval->save();
        return response()->json(['message' => 'Evaluation submitted.', 'evaluation' => $eval], 201);
    }

    public function feedback(Request $request)
    {
        $internshipIds = Internship::where('faculty_id', $request->user()->id)->pluck('id');
        $journals = \App\Models\JournalEntry::whereIn('internship_id', $internshipIds)->whereNotNull('faculty_feedback')->with('internship.student.studentProfile')->orderByDesc('faculty_reviewed_at')->paginate(20);
        return ApiResponse::list($journals);
    }

    public function submitFeedback(Request $request, int $internshipId)
    {
        $request->validate(['feedback' => 'required|string|min:5']);
        $internship = Internship::where('faculty_id', $request->user()->id)->findOrFail($internshipId);
        $journal = $internship->journals()->latest('date')->first();
        if ($journal) {
            $journal->update(['faculty_feedback' => $request->feedback, 'faculty_reviewed_by' => $request->user()->id, 'faculty_reviewed_at' => now()]);
        }
        return response()->json(['message' => 'Feedback submitted.']);
    }

    /** GET /api/v1/faculty/documents — faculty-stage queue for assigned students only */
    public function documents(Request $request)
    {
        $internshipIds = Internship::where('faculty_id', $request->user()->id)->pluck('id');

        $docs = \App\Models\Document::whereIn('internship_id', $internshipIds)
            ->where('current_stage', 'faculty')
            ->where('status', 'pending_faculty')
            ->with(['internship.student.studentProfile', 'reviews'])
            ->orderBy('submitted_at')
            ->paginate(25);

        return ApiResponse::list($docs);
    }

    /** PATCH /api/v1/faculty/documents/{id}/verify — final approval */
    public function verifyDocument(Request $request, int $id)
    {
        $internshipIds = Internship::where('faculty_id', $request->user()->id)->pluck('id');
        $doc = \App\Models\Document::whereIn('internship_id', $internshipIds)
            ->where('current_stage', 'faculty')
            ->where('status', 'pending_faculty')
            ->findOrFail($id);

        $from = $doc->status;
        $doc->update([
            'status' => 'approved',
            'current_stage' => 'done',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'remarks' => $request->remarks,
        ]);

        \App\Models\DocumentReview::create([
            'document_id' => $doc->id,
            'stage' => 'faculty',
            'action' => 'approve',
            'from_status' => $from,
            'to_status' => 'approved',
            'remarks' => $request->remarks,
            'reviewed_by' => $request->user()->id,
        ]);

        if ($doc->internship?->student_id) {
            Notification::notify(
                $doc->internship->student_id,
                'document_approved',
                'Document Fully Approved',
                "Your {$doc->document_type} has been verified by faculty and is fully approved.",
                '/student/documents',
                ['document_id' => $doc->id, 'document_type' => $doc->document_type]
            );
        }

        audit_log($request->user()->id, 'verify_document_faculty', ['document_id' => $id]);

        return response()->json(['message' => 'Document fully approved.', 'document' => $doc]);
    }

    /** PATCH /api/v1/faculty/documents/{id}/reject */
    public function rejectDocument(Request $request, int $id)
    {
        $request->validate(['remarks' => 'required|string|max:500']);

        $internshipIds = Internship::where('faculty_id', $request->user()->id)->pluck('id');
        $doc = \App\Models\Document::whereIn('internship_id', $internshipIds)
            ->where('current_stage', 'faculty')
            ->where('status', 'pending_faculty')
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
            'stage' => 'faculty',
            'action' => 'reject',
            'from_status' => $from,
            'to_status' => 'rejected',
            'remarks' => $request->remarks,
            'reviewed_by' => $request->user()->id,
        ]);

        if ($doc->internship?->student_id) {
            Notification::notify(
                $doc->internship->student_id,
                'document_rejected',
                'Document Rejected by Faculty',
                "Your {$doc->document_type} was rejected: {$request->remarks}",
                '/student/documents',
                ['document_id' => $doc->id, 'document_type' => $doc->document_type, 'remarks' => $request->remarks]
            );
        }

        audit_log($request->user()->id, 'reject_document_faculty', ['document_id' => $id]);

        return response()->json(['message' => 'Document rejected.', 'document' => $doc]);
    }

    /** GET /api/v1/faculty/reports/student-summary — assigned students only */
    public function reportStudentSummary(Request $request)
    {
        $facultyId = $request->user()->id;
        $sections = \App\Models\FacultySectionAssignment::where('faculty_user_id', $facultyId)->pluck('section');

        $users = User::where('role', 'student')
            ->where(function ($q) use ($facultyId, $sections) {
                $q->whereHas('studentProfile', function ($p) use ($sections) {
                    $p->whereIn('section', $sections);
                })
                ->orWhereHas('internshipsAsStudent', function ($i) use ($facultyId) {
                    $i->where('faculty_id', $facultyId);
                });
            })
            ->with([
                'studentProfile', 
                'activeInternship.company',
                'activeInternship' => function($q) {
                    $q->withCount([
                        'attendance as validated_days' => fn($a) => $a->where('status', 'validated'),
                        'journals as approved_journals' => fn($j) => $j->where('status', 'approved'),
                        'documents as approved_docs' => fn($d) => $d->where('status', 'approved')
                    ]);
                }
            ])
            ->get();

        $students = $users->map(function ($u) {
            $i = $u->activeInternship;
            $p = $u->studentProfile;
            return [
                'student_name' => trim(($p->first_name ?? '').' '.($p->last_name ?? '')),
                'student_number' => $u->username,
                'program' => $p->course_name ?? $p->program ?? $i?->program ?? '—',
                'company' => $i->company?->company_name ?? '—',
                'status' => $i?->status ?? 'unplaced',
                'hours_rendered' => (float) ($i?->total_hours_rendered ?? 0),
                'target_hours' => $i?->target_hours ?? 500,
                'progress_pct' => ($i?->target_hours ?? 500) > 0 ? round((($i?->total_hours_rendered ?? 0) / ($i?->target_hours ?? 500)) * 100, 1) : 0,
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
        $sections = \App\Models\FacultySectionAssignment::where('faculty_user_id', $facultyId)->pluck('section');

        $users = User::where('role', 'student')
            ->where(function ($q) use ($facultyId, $sections) {
                $q->whereHas('studentProfile', function ($p) use ($sections) {
                    $p->whereIn('section', $sections);
                })
                ->orWhereHas('internshipsAsStudent', function ($i) use ($facultyId) {
                    $i->where('faculty_id', $facultyId);
                });
            })
            ->with(['studentProfile', 'activeInternship.documents'])
            ->get();

        $rows = $users->map(function ($u) use ($requiredCount, $requiredTypes) {
            $i = $u->activeInternship;
            $approvedDocsCount = $i ? $i->documents->where('status', 'approved')->count() : 0;
            $approvedDocTypes = $i ? $i->documents->where('status', 'approved')->pluck('document_type') : collect([]);
            
            return [
                'student_name' => trim((optional($u->studentProfile)->first_name ?? '').' '.(optional($u->studentProfile)->last_name ?? '')),
                'program' => $u->studentProfile?->course_name ?? '—',
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
        $sections = \App\Models\FacultySectionAssignment::where('faculty_user_id', $facultyId)->pluck('section');

        $users = User::where('role', 'student')
            ->where(function ($q) use ($facultyId, $sections) {
                $q->whereHas('studentProfile', function ($p) use ($sections) {
                    $p->whereIn('section', $sections);
                })
                ->orWhereHas('internshipsAsStudent', function ($i) use ($facultyId) {
                    $i->where('faculty_id', $facultyId);
                });
            })
            ->with(['studentProfile', 'activeInternship'])
            ->get();

        $byProgram = $users
            ->groupBy(function ($u) {
                foreach ([
                    $u->studentProfile?->course_name,
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
                $completedCount = $rows->filter(fn($u) => $u->activeInternship?->status === 'completed')->count();
                $avgHours = $rows->avg(fn($u) => $u->activeInternship?->total_hours_rendered ?? 0);
                $avgGrade = $rows->avg(fn($u) => $u->activeInternship?->final_grade);
                
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

        $internshipIds = Internship::where('faculty_id', $request->user()->id)->pluck('id');
        $evalAvg = \App\Models\Evaluation::whereIn('internship_id', $internshipIds)
            ->selectRaw('
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
            'by_program' => $byProgram,
            'eval_averages' => $evalAvg,
            'generated_at' => now()->toDateTimeString(),
        ]);
    }
}
