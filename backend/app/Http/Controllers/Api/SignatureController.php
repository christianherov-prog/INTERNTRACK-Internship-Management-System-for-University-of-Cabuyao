<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SignatureCapture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SignatureController extends Controller
{
    /**
     * POST /v1/auth/signature
     * Upload a signature image. The system automatically removes the background
     * and saves a transparent PNG, ready to be stamped on PDF forms.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'signature' => 'required|image|mimes:png,jpg,jpeg|max:5120',
        ]);

        $user = auth()->user();
        $binary = (string) file_get_contents($request->file('signature')->getRealPath());

        try {
            SignatureCapture::storeProcessedProfile($user, $binary);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Signature uploaded and processed successfully.',
            'has_signature' => true,
        ]);
    }

    /**
     * DELETE /v1/auth/signature — remove saved signature
     */
    public function destroy()
    {
        $user = auth()->user();
        $path = "signatures/{$user->id}_processed.png";

        if (Storage::exists($path)) {
            Storage::delete($path);
        }

        return response()->json(['message' => 'Signature removed.']);
    }

    /**
     * GET /v1/auth/signature/status — check if user has a signature on file
     */
    public function status()
    {
        $user = auth()->user();
        $path = SignatureCapture::profilePath($user);

        return response()->json([
            'has_signature' => (bool) $path,
            'signature_path' => $path,
        ]);
    }

    /**
     * GET /v1/auth/signature/view — return the storage path for preview
     * The frontend fetches the actual file via /files/download?path=...
     */
    public function view()
    {
        $user = auth()->user();
        $path = SignatureCapture::profilePath($user);

        if (! $path) {
            return response()->json(['error' => 'No signature on file.'], 404);
        }

        return response()->json([
            'signature_path' => $path,
        ]);
    }
}
