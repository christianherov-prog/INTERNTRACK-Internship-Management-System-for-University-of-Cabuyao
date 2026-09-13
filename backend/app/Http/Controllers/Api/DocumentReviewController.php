<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\OjtRequirementTemplate;
use App\Models\Notification;
use App\Support\DocumentZip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentReviewController extends Controller
{
    /**
     * POST /api/v1/{faculty|coordinator}/documents/bulk-download
     */
    public function bulkDownload(Request $request)
    {
        $data = $request->validate([
            'document_ids' => 'required|array|min:1|max:'.DocumentZip::MAX_DOCUMENTS,
            'document_ids.*' => 'integer',
        ]);

        return DocumentZip::download($request->user(), $data['document_ids']);
    }

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

        // Authorization: owner-created custom template, system template in dept scope,
        // or internship assigned to the reviewer.
        $template = OjtRequirementTemplate::where('name', $document->document_type)
            ->where(function ($q) use ($request) {
                $q->where('created_by', $request->user()->id)
                    ->orWhere('is_system', true);
            })
            ->first();

        if (! $template) {
            // Alias match for renamed / legacy document_type labels against system codes
            foreach (\App\Services\DocumentComplianceService::systemCodeCatalog() as $row) {
                $aliases = \App\Services\DocumentComplianceService::aliasesForCode($row['code'], $row['name']);
                if (in_array($document->document_type, $aliases, true)) {
                    $template = OjtRequirementTemplate::query()
                        ->where('is_system', true)
                        ->where(function ($q) use ($row) {
                            $q->where('system_code', $row['code'])
                                ->orWhere('name', $row['name']);
                        })
                        ->first();
                    break;
                }
            }
        }

        if (!$template || (! $template->is_system && (int) $template->created_by !== (int) $request->user()->id)) {
            $isAssigned = $internship &&
                ($internship->faculty_id === $request->user()->id ||
                 $internship->coordinator_id === $request->user()->id);

            if (!$isAssigned && ! ($template && $template->is_system)) {
                return response()->json(['message' => 'Unauthorized to review this document.'], 403);
            }
        }

        $newStatus = $request->action === 'approve' ? 'approved' : 'rejected';
        $reviewer = $request->user()->loadMissing('facultyProfile');
        $reviewerName = trim((string) ($reviewer->facultyProfile?->full_name ?: $reviewer->username ?: 'Faculty'));

        $document = DB::transaction(function () use ($request, $id, $newStatus) {
            $document = Document::with('internship.student.studentProfile')->lockForUpdate()->findOrFail($id);
            $oldStatus = $document->status;

            if ($oldStatus === $newStatus && (int) $document->reviewed_by === (int) $request->user()->id) {
                return $document;
            }

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

            return $document->fresh('internship.student.studentProfile');
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

        $student = $document->internship?->student;
        audit_log($request->user()->id, $newStatus === 'approved' ? 'document_approved' : 'document_rejected', [
            'document_id' => $document->id,
            'document_type' => $document->document_type,
            'student_id' => $student?->id,
            'student_number' => $student?->student_number,
            'internship_id' => $document->internship_id,
            'remarks' => $request->remarks,
        ]);

        return response()->json([
            'message'  => 'Document ' . $newStatus . ' successfully.',
            'document' => $document,
        ]);
    }
}
