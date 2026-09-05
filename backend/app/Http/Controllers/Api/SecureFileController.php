<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Internship;
use App\Support\InternshipAccess;
use App\Support\RequirementAudience;
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
        if (in_array($user->role, ['director', 'admin'], true)) {
            return;
        }

        $internshipId = InternshipAccess::internshipIdFromPath($path);

        if ($internshipId) {
            $internship = Internship::find($internshipId);
            if (!$internship) {
                abort(403, 'You do not have access to this file.');
            }
            InternshipAccess::abortUnlessCanView($user, $internship);

            return;
        }

        // signatures/document-reviews/{documentId}/...
        if (preg_match('#^signatures/document-reviews/(\d+)/#', $path, $m)) {
            $doc = Document::with('internship')->find((int) $m[1]);
            if (!$doc?->internship) {
                abort(403, 'You do not have access to this file.');
            }
            InternshipAccess::abortUnlessCanView($user, $doc->internship);

            return;
        }

        if (str_starts_with($path, 'requirement_templates/')) {
            if ($this->studentCanAccessRequirementTemplateFile($user, $path)) {
                return;
            }

            if ($this->staffCanAccessRequirementTemplateFile($user, $path)) {
                return;
            }

            abort(403, 'You do not have access to this file.');
        }

        abort(403, 'You do not have access to this file.');
    }

    private function studentCanAccessRequirementTemplateFile($user, string $path): bool
    {
        if ($user->role !== 'student') {
            return false;
        }

        $attachment = \App\Models\RequirementTemplateAttachment::with('requirementTemplate.targets')
            ->where('file_path', $path)
            ->first();

        $template = $attachment?->requirementTemplate
            ?? \App\Models\OjtRequirementTemplate::with('targets')->where('template_file_path', $path)->first();

        if (!$template || !$template->is_active) {
            return false;
        }

        return RequirementAudience::studentCanAccessTemplate($user, $template);
    }

    private function staffCanAccessRequirementTemplateFile($user, string $path): bool
    {
        if ($user->role === 'director' || $user->role === 'admin') {
            return true;
        }

        if (! in_array($user->role, ['faculty', 'coordinator'], true)) {
            return false;
        }

        $attachment = \App\Models\RequirementTemplateAttachment::with('requirementTemplate')
            ->where('file_path', $path)
            ->first();

        $template = $attachment?->requirementTemplate
            ?? \App\Models\OjtRequirementTemplate::where('template_file_path', $path)->first();

        if (! $template) {
            return false;
        }

        return (int) $template->created_by === (int) $user->id;
    }
}
