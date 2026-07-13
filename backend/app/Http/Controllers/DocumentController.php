<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();
        $documents = $student->documents()->latest()->get();

        // Requirement checklist with the latest upload (if any) per type.
        $requirements = collect(Document::REQUIRED_TYPES)->map(function ($type) use ($documents) {
            $latest = $documents->firstWhere('document_type', $type);

            return [
                'document_type' => $type,
                'status' => $latest?->status ?? 'missing',
                'last_update' => $latest?->created_at,
                'document_id' => $latest?->id,
            ];
        });

        return response()->json([
            'documents' => $documents,
            'requirements' => $requirements,
            'required_types' => Document::REQUIRED_TYPES,
            'stats' => [
                'approved' => $requirements->where('status', 'approved')->count(),
                'pending' => $requirements->where('status', 'pending')->count(),
                'missing' => $requirements->where('status', 'missing')->count(),
                'submitted' => $requirements->whereNotIn('status', ['missing'])->count(),
                'required' => count(Document::REQUIRED_TYPES),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $path = $file->store('documents/'.$request->user()->id);

        $document = $request->user()->documents()->create([
            'document_type' => $data['document_type'],
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'remarks' => $data['remarks'] ?? null,
        ]);

        return response()->json(['document' => $document], 201);
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        abort_if($document->student_id !== $request->user()->id, 403, 'Not your record.');
        abort_if($document->status === 'approved', 422, 'Approved documents can no longer be deleted.');

        Storage::delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Document deleted.']);
    }
}
