<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Internship;
use App\Support\InternshipAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SecureFileController extends Controller
{
    /**
     * GET /api/v1/files/download?path=
     * Streams a private (or legacy public) upload after ownership checks.
     */
    public function download(Request $request)
    {
        $data = $request->validate([
            'path' => 'required|string|max:500',
        ]);

        $path = ltrim(str_replace('\\', '/', $data['path']), '/');
        $cleanPath = preg_replace('#^(?:storage/|public/|app/public/)#i', '', $path);

        if (str_contains($cleanPath, '..') || str_starts_with($cleanPath, 'avatars/')) {
            abort(403, 'Invalid file path.');
        }

        $user = $request->user();
        $this->authorizePath($user, $cleanPath);

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($cleanPath)) {
                $mime = Storage::disk($disk)->mimeType($cleanPath) ?: 'application/octet-stream';
                $name = basename($cleanPath);

                return Storage::disk($disk)->response($cleanPath, $name, [
                    'Content-Type' => $mime,
                    'Content-Disposition' => 'inline; filename="'.$name.'"',
                ]);
            }
        }

        // Fallback: check physical storage and public directories directly
        $fallbacks = [
            storage_path('app/public/' . $cleanPath),
            storage_path('app/' . $cleanPath),
            public_path('storage/' . $cleanPath),
            public_path($cleanPath),
            storage_path($cleanPath),
        ];

        foreach ($fallbacks as $fp) {
            if (file_exists($fp) && is_file($fp)) {
                $name = basename($fp);
                $mime = @mime_content_type($fp) ?: 'application/octet-stream';
                return response()->file($fp, [
                    'Content-Type' => $mime,
                    'Content-Disposition' => 'inline; filename="'.$name.'"',
                ]);
            }
        }

        abort(404, 'File not found.');
    }

    private function authorizePath($user, string $path): void
    {
        if (in_array($user->role, ['director', 'admin', 'faculty', 'coordinator'], true)) {
            return;
        }

        $internshipId = InternshipAccess::internshipIdFromPath($path);

        if ($internshipId) {
            $internship = Internship::find($internshipId);
            if (!$internship || !InternshipAccess::canView($user, $internship)) {
                abort(403, 'You do not have access to this file.');
            }

            return;
        }

        // signatures/document-reviews/{documentId}/...
        if (preg_match('#^signatures/document-reviews/(\d+)/#', $path, $m)) {
            $doc = Document::with('internship')->find((int) $m[1]);
            if (!$doc?->internship || !InternshipAccess::canView($user, $doc->internship)) {
                abort(403, 'You do not have access to this file.');
            }

            return;
        }

        abort(403, 'You do not have access to this file.');
    }
}
