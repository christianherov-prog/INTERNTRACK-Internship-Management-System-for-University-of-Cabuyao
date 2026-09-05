<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves public avatar files without relying on the public/storage symlink.
 * OneDrive / Windows junctions for public/storage often break; this keeps
 * navbar + settings avatars reachable even when the symlink is missing.
 */
class PublicAvatarController extends Controller
{
    /** GET /api/v1/media/avatars/{filename} */
    public function show(string $filename): StreamedResponse
    {
        $filename = basename($filename);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            abort(404);
        }

        if (!preg_match('/^[A-Za-z0-9._-]+\.(jpe?g|png|webp)$/i', $filename)) {
            abort(404);
        }

        $path = 'avatars/'.$filename;
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return Storage::disk('public')->response($path, $filename, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
