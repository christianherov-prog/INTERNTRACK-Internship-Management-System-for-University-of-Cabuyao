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
        $assignedStudentsCount = User::inDepartment()->where('role', 'student')
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

        $internships = Internship::inDepartment()->where('faculty_id', $facultyId)
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
                'student'   => $j->internship->student->studentProfile ? trim("{$j->internship->student->studentProfile->last_name}, {$j->internship->student->studentProfile->first_name}") : $j->internship->student->username,
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

        $query = User::inDepartment()->where('role', 'student')
            ->where(function ($q) use ($facultyId, $sections) {
                $q->whereHas('studentProfile', function ($p) use ($sections) {
                    $p->whereIn('section', $sections);
                })
                ->orWhereHas('internshipsAsStudent', function ($i) use ($facultyId) {
                    $i->where('faculty_id', $facultyId);
                });
            })
            ->with(['studentProfile.program', 'activeInternship.company', 'activeInternship.supervisor.supervisorProfile', 'activeInternship.attendance']);

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
                'program' => is_object($internship?->program)
                    ? ($internship->program->name ?? $internship->program->code ?? ($profile?->program?->name ?? '—'))
                    : ($internship?->program ?: ($profile?->program?->name ?? '—')),
                'section' => $profile?->section ?? '—',
                'company' => $internship?->company?->company_name ?? null,
                'supervisor' => $internship?->supervisor?->supervisorProfile?->full_name ?? null,
                'student' => [
                    'id' => $student->id,
                    'username' => $student->username,
                    'email' => $student->email,
                    'sex' => collect([$student->sex, $profile?->sex])->first(fn($s) => !empty($s)) ?? '—',
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
        if (!User::inDepartment()->where('id', $userId)->exists()) {
            \App\Support\DepartmentScope::abortDifferentDepartment();
        }

        $sections = \App\Models\FacultySectionAssignment::where('faculty_user_id', $facultyId)->pluck('section');
        $assigned = $sections->contains($student->studentProfile?->section)
            || Internship::inDepartment()->where('faculty_id', $facultyId)
                ->where('student_id', $userId)
                ->exists();

        if (!$assigned) {
            return response()->json(['message' => 'You can only archive students assigned to you.'], 403);
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

    /**
     * GET /api/v1/faculty/students/{userId}/progress
     * Returns aggregated progress data for a single assigned student.
     */
    public function studentProgress(Request $request, int $userId)
    {
        $student = User::where('role', 'student')->with('studentProfile.program')->findOrFail($userId);
        if (!User::inDepartment()->where('id', $userId)->exists()) {
            \App\Support\DepartmentScope::abortDifferentDepartment();
        }
        
        $facultyId = $request->user()->id;
        $sections = \App\Models\FacultySectionAssignment::where('faculty_user_id', $facultyId)->pluck('section');
        $isAssigned = $sections->contains($student->studentProfile?->section)
            || Internship::inDepartment()->where('faculty_id', $facultyId)->where('student_id', $userId)->exists();

        if (!$isAssigned) {
            abort(403, 'Student is not assigned to you.');
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
                    'total'      => RequiredDocuments::count(),
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

    /**
     * GET /api/v1/faculty/attendance
     * Read-only attendance monitoring for assigned students (not validation).
     * Optional: status, internship_id query filters.
     */
    public function attendance(Request $request)
    {
        $internshipIds = Internship::inDepartment()->where('faculty_id', $request->user()->id)->pluck('id');

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

        $page = $query->paginate(25);
        app(\App\Services\DtrWorkflowService::class)->decorateLogs(collect($page->items()));

        return ApiResponse::list($page);
    }

    public function journals(Request $request)
    {
        $internshipIds = Internship::inDepartment()->where('faculty_id', $request->user()->id)->pluck('id');
        $journals = \App\Models\JournalEntry::whereIn('internship_id', $internshipIds)
            ->whereIn('status', ['submitted', 'approved', 'needs_revision'])
            ->with(['internship.student.studentProfile'])
            ->orderByDesc('date')
            ->paginate(25);
        return ApiResponse::list($journals);
    }

    public function reviewJournal(Request $request, int $id)
    {
        $request->validate(['action' => 'required|in:approved,needs_revision', 'feedback' => 'nullable|string|max:1000', 'score' => 'nullable|numeric|min:0|max:100']);
        $journal = \App\Models\JournalEntry::whereHas(
            'internship',
            fn ($q) => $q->inDepartment()->where('faculty_id', $request->user()->id)
        )->findOrFail($id);
        
        $updateData = ['status' => $request->action, 'faculty_feedback' => $request->feedback, 'faculty_reviewed_by' => $request->user()->id, 'faculty_reviewed_at' => now()];
        if ($request->has('score') && $request->action === 'approved') {
            $updateData['score'] = $request->score;
        } elseif ($request->action !== 'approved') {
            $updateData['score'] = null; // Clear score if not approved
        }
        
        $journal->update($updateData);

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

    public function studentJournalHistory(Request $request, int $studentId)
    {
        \App\Support\DepartmentScope::abortUnlessStudentInDepartment($request->user(), $studentId);

        $journals = \App\Models\JournalEntry::whereHas('internship', function($q) use ($request, $studentId) {
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
        $availableSections = \App\Models\StudentProfile::whereHas('user.internshipsAsStudent', function($q) use ($internshipIds) {
            $q->whereIn('id', $internshipIds);
        })->whereNotNull('section')->distinct()->pluck('section');

        // Faculty sees only FO-24 (Supervisor performance rating — official basis for grading)
        $query = Internship::inDepartment()
            ->where('faculty_id', $facultyId)
            ->with([
                'student.studentProfile.program',
                'company',
                'supervisor.supervisorProfile',
                'faculty.facultyProfile',
                'evaluations' => function ($q) {
                    $q->whereIn('form_type', ['FO-24']);
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
            'available_sections' => $availableSections
        ]);
    }

    public function feedback(Request $request)
    {
        $internshipIds = Internship::inDepartment()->where('faculty_id', $request->user()->id)->pluck('id');
        $journals = \App\Models\JournalEntry::whereIn('internship_id', $internshipIds)->whereNotNull('faculty_feedback')->with('internship.student.studentProfile')->orderByDesc('faculty_reviewed_at')->paginate(20);
        return ApiResponse::list($journals);
    }

    public function submitFeedback(Request $request, int $internshipId)
    {
        $request->validate(['feedback' => 'required|string|min:5']);
        $internship = Internship::findOrFail($internshipId);
        if (!Internship::inDepartment()->where('id', $internshipId)->exists()) {
            \App\Support\DepartmentScope::abortDifferentDepartment();
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

        $docs = \App\Models\Document::whereIn('internship_id', $internshipIds)
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
        $sections = \App\Models\FacultySectionAssignment::where('faculty_user_id', $facultyId)->pluck('section');

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
                'student_name' => trim(($p->last_name ?? '').', '.($p->first_name ?? '')),
                'student_number' => $u->username,
                'program' => $p->program?->name ?? $i?->program ?? '—',
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
        $sections = \App\Models\FacultySectionAssignment::where('faculty_user_id', $facultyId)->pluck('section');

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

        $internshipIds = Internship::inDepartment()->where('faculty_id', $request->user()->id)->pluck('id');
        $evalAvg = \App\Models\Evaluation::whereIn('internship_id', $internshipIds)
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
