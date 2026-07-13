<?php

namespace App\Http\Controllers;

use App\Models\LogbookEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogbookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        return response()->json([
            'entries' => $student->logbookEntries()->orderByDesc('entry_date')->get(),
            'stats' => [
                'submitted' => $student->logbookEntries()->count(),
                'reviewed' => $student->logbookEntries()->where('status', 'reviewed')->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'hours_rendered' => ['required', 'numeric', 'min:0', 'max:24'],
            'tasks_completed' => ['required', 'string'],
            'learning_reflection' => ['nullable', 'string'],
        ]);

        $entry = $request->user()->logbookEntries()->create($data);

        return response()->json(['entry' => $entry], 201);
    }

    public function update(Request $request, LogbookEntry $logbookEntry): JsonResponse
    {
        $this->authorizeOwnership($request, $logbookEntry);

        abort_if($logbookEntry->status === 'reviewed', 422, 'Reviewed entries can no longer be edited.');

        $data = $request->validate([
            'entry_date' => ['sometimes', 'date'],
            'hours_rendered' => ['sometimes', 'numeric', 'min:0', 'max:24'],
            'tasks_completed' => ['sometimes', 'string'],
            'learning_reflection' => ['nullable', 'string'],
        ]);

        $logbookEntry->update($data);

        return response()->json(['entry' => $logbookEntry]);
    }

    public function destroy(Request $request, LogbookEntry $logbookEntry): JsonResponse
    {
        $this->authorizeOwnership($request, $logbookEntry);

        abort_if($logbookEntry->status === 'reviewed', 422, 'Reviewed entries can no longer be deleted.');

        $logbookEntry->delete();

        return response()->json(['message' => 'Logbook entry deleted.']);
    }

    private function authorizeOwnership(Request $request, LogbookEntry $entry): void
    {
        abort_if($entry->student_id !== $request->user()->id, 403, 'Not your record.');
    }
}
