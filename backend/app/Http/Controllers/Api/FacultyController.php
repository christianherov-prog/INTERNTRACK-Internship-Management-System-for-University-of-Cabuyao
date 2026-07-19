<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Models\Notification;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class FacultyController extends Controller
{
    public function dashboard(Request $request)
    {
        $facultyId  = $request->user()->id;
        $internships = Internship::where('faculty_id', $facultyId)
            ->whereIn('status', ['ongoing', 'active', 'for_evaluation'])
            ->with('student.studentProfile')
            ->get();

        $pendingJournals = $internships->sum(fn($i) => $i->journals()->where('status', 'submitted')->count());
        $pendingEvals     = $internships->whereNotIn('id',
            \App\Models\Evaluation::where('evaluator_type', 'faculty')->pluck('internship_id')->toArray()
        )->count();

        // Recent activity — last 5 journals or feedback the faculty has acted on
        $internshipIds = $internships->pluck('id');
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

        return response()->json([
            'stats' => [
                'assigned_students'   => $internships->count(),
                'pending_journals'    => $pendingJournals,
                'pending_evaluations' => $pendingEvals,
            ],
            'recent_activity' => $recentActivity,
        ]);
    }

    public function assignedStudents(Request $request)
    {
        $internships = Internship::where('faculty_id', $request->user()->id)
            ->with(['student.studentProfile', 'company'])
            ->paginate(20);
        return ApiResponse::list($internships);
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
                '/student/logbook'
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
        $request->validate(['evaluation_period' => 'required|in:midterm,final', 'technical_skills' => 'required|numeric|min:1|max:5', 'communication_skills' => 'required|numeric|min:1|max:5', 'teamwork' => 'required|numeric|min:1|max:5', 'initiative' => 'required|numeric|min:1|max:5', 'work_ethics' => 'required|numeric|min:1|max:5', 'attendance_punctuality' => 'required|numeric|min:1|max:5', 'adaptability' => 'required|numeric|min:1|max:5', 'problem_solving' => 'required|numeric|min:1|max:5']);
        $internship = Internship::where('faculty_id', $request->user()->id)->findOrFail($internshipId);
        $eval = new \App\Models\Evaluation($request->only(['evaluation_period', 'technical_skills', 'communication_skills', 'teamwork', 'initiative', 'work_ethics', 'attendance_punctuality', 'adaptability', 'problem_solving', 'general_comments']));
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
                '/student/documents'
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
                '/student/documents'
            );
        }

        audit_log($request->user()->id, 'reject_document_faculty', ['document_id' => $id]);

        return response()->json(['message' => 'Document rejected.', 'document' => $doc]);
    }
}
