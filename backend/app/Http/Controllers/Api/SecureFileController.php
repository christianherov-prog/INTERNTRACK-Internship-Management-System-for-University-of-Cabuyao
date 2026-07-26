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

        if (str_contains($path, '..') || str_starts_with($path, 'avatars/')) {
            abort(403, 'Invalid file path.');
        }

        $user = $request->user();
        $this->authorizePath($user, $path);

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                $mime = Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';
                $name = basename($path);

                return Storage::disk($disk)->response($path, $name, [
                    'Content-Type' => $mime,
                    'Content-Disposition' => 'inline; filename="'.$name.'"',
                ]);
            }
        }

        abort(404, 'File not found.');
    }

    private function authorizePath($user, string $path): void
    {
        if (in_array($user->role, ['director', 'admin'], true)) {
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

        if ($path === "signatures/{$user->id}_processed.png") {
            return;
        }

        abort(403, 'You do not have access to this file.');
    }
}
