<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Announcement CRUD for coordinators and PALD directors.
 */
class AnnouncementController extends Controller
{
    /** GET /api/v1/{coordinator|director}/announcements */
    public function index(Request $request)
    {
        $items = Announcement::with('author')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate(15);

        $items->getCollection()->transform(fn (Announcement $a) => $a->toClientArray());

        return ApiResponse::list($items);
    }

    /** POST — JSON or multipart with optional attachment. */
    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'content'     => 'required|string',
            'target_role' => 'required|string|in:all,student,supervisor,faculty,coordinator,director',
            'category'    => 'nullable|string|in:'.implode(',', Announcement::CATEGORIES),
            'is_pinned'   => 'boolean',
            'expires_at'  => 'nullable|date|after:now',
            'attachment'  => Announcement::attachmentValidationRules(),
        ], Announcement::attachmentValidationMessages());

        $announcement = Announcement::create([
            'created_by'  => $request->user()->id,
            'title'       => $request->title,
            'content'     => $request->content,
            'target_role' => $request->target_role,
            'category'    => $request->input('category', 'general') ?: 'general',
            'is_pinned'   => $request->boolean('is_pinned', false),
            'expires_at'  => $request->expires_at,
        ]);

        if ($request->hasFile('attachment')) {
            $announcement->storeUploadedAttachment($request->file('attachment'));
            $announcement->save();
        }

        audit_log($request->user()->id, 'create_announcement', [
            'title'          => $request->title,
            'has_attachment' => $announcement->hasAttachment(),
        ]);

        return response()->json([
            'message'      => 'Announcement created.',
            'announcement' => $announcement->fresh('author')->toClientArray(),
        ], 201);
    }

    /**
     * PUT|POST /announcements/{id}
     * POST multipart when replacing/removing an attachment.
     */
    public function update(Request $request, int $id)
    {
        $request->validate([
            'title'             => 'required|string|max:255',
            'content'           => 'required|string',
            'target_role'       => 'required|in:all,student,supervisor,faculty,coordinator,director',
            'category'          => 'nullable|string|in:'.implode(',', Announcement::CATEGORIES),
            'is_pinned'         => 'boolean',
            'expires_at'        => 'nullable|date',
            'attachment'        => Announcement::attachmentValidationRules(),
            'remove_attachment' => 'sometimes|boolean',
        ], Announcement::attachmentValidationMessages());

        $announcement = Announcement::where('created_by', $request->user()->id)->findOrFail($id);
        $announcement->fill($request->only(['title', 'content', 'target_role', 'expires_at']));
        $announcement->category = $request->input('category', $announcement->category ?: 'general') ?: 'general';
        $announcement->is_pinned = $request->boolean('is_pinned', false);

        if ($request->boolean('remove_attachment') && !$request->hasFile('attachment')) {
            $announcement->deleteStoredAttachment();
            $announcement->clearAttachmentFields();
        }

        if ($request->hasFile('attachment')) {
            $announcement->storeUploadedAttachment($request->file('attachment'));
        }

        $announcement->save();

        return response()->json([
            'message'      => 'Announcement updated.',
            'announcement' => $announcement->fresh('author')->toClientArray(),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $announcement = Announcement::where('created_by', $request->user()->id)->findOrFail($id);
        $announcement->deleteStoredAttachment();
        $announcement->delete();
        audit_log($request->user()->id, 'delete_announcement', ['id' => $id]);

        return response()->json(['message' => 'Announcement deleted.']);
    }
}
