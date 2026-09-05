<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\OjtRequirementTemplate;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentReviewController extends Controller
{
    /**
     * Approve or reject a document submission.
     * POST /api/v1/{faculty|coordinator}/documents/{id}/review
     */
    public function review(Request $request, $id)
    {
        $request->validate([
            'action'  => 'required|in:approve,reject',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $document = Document::with('internship.student.studentProfile')->findOrFail($id);

        $internship = $document->internship;
        if ($internship) {
            \App\Support\DepartmentScope::abortUnlessInternshipInDepartment($request->user(), $internship);
        }

        // Authorization: confirm the reviewer created the requirement template
        // Match by name AND created_by so we never cross role boundaries.
        $template = OjtRequirementTemplate::where('name', $document->document_type)
            ->where('created_by', $request->user()->id)
            ->first();

        if (!$template) {
            // Fallback: allow if the internship is directly assigned to the reviewer
            $internship = $document->internship;
            $isAssigned = $internship &&
                ($internship->faculty_id === $request->user()->id ||
                 $internship->coordinator_id === $request->user()->id);

            if (!$isAssigned) {
                return response()->json(['message' => 'Unauthorized to review this document.'], 403);
            }
        }

        $newStatus = $request->action === 'approve' ? 'approved' : 'rejected';
        $oldStatus = $document->status;
        $reviewer = $request->user()->loadMissing('facultyProfile');
        $reviewerName = trim((string) ($reviewer->facultyProfile?->full_name ?: $reviewer->username ?: 'Faculty'));

        DB::transaction(function () use ($request, $document, $oldStatus, $newStatus) {
            $document->update([
                'status'      => $newStatus,
                'remarks'     => $request->remarks,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            DocumentReview::create([
                'document_id'  => $document->id,
                'stage'        => 'creator_review',
                'action'       => $request->action,
                'from_status'  => $oldStatus,
                'to_status'    => $newStatus,
                'remarks'      => $request->remarks,
                'reviewed_by'  => $request->user()->id,
                'signed_at'    => now(),
            ]);
        });

        $internship = $document->internship;
        if ($internship?->student_id) {
            $outcome = $newStatus === 'approved' ? 'approved' : 'rejected';
            $title = $newStatus === 'approved' ? 'Document approved' : 'Document rejected';
            $message = "Your document \"{$document->document_type}\" was {$outcome} by {$reviewerName}.";
            if ($request->remarks) {
                $message .= " Remarks: {$request->remarks}";
            }

            Notification::notify(
                (int) $internship->student_id,
                $newStatus === 'approved' ? 'document_approved' : 'document_rejected',
                $title,
                $message,
                '/student/documents',
                [
                    'document_id' => $document->id,
                    'document_type' => $document->document_type,
                    'status' => $newStatus,
                    'reviewed_by' => $reviewerName,
                ]
            );
        }

        $document->refresh();

        return response()->json([
            'message'  => 'Document ' . $newStatus . ' successfully.',
            'document' => $document,
        ]);
    }
}
