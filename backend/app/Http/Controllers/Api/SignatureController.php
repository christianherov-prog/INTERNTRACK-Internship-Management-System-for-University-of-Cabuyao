<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

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
            'signature' => 'required|image|mimes:png,jpg,jpeg|max:5120', // 5MB max
        ]);

        $user = auth()->user();

        // Process: remove white/light background → transparent PNG
        $manager = new ImageManager(new Driver());
        $image   = $manager->read($request->file('signature'));

        // Convert to PNG with background removed
        $processed = $this->removeBackground($image);

        $storagePath = "signatures/{$user->id}_processed.png";
        Storage::put($storagePath, $processed);

        return response()->json([
            'message'   => 'Signature uploaded and processed successfully.',
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
        $path = "signatures/{$user->id}_processed.png";

        return response()->json([
            'has_signature'  => Storage::exists($path),
            'signature_path' => Storage::exists($path) ? $path : null,
        ]);
    }

    /**
     * GET /v1/auth/signature/view — return the storage path for preview
     * The frontend fetches the actual file via /files/download?path=...
     */
    public function view()
    {
        $user = auth()->user();
        $path = "signatures/{$user->id}_processed.png";

        if (!Storage::exists($path)) {
            return response()->json(['error' => 'No signature on file.'], 404);
        }

        return response()->json([
            'signature_path' => $path,
        ]);
    }

    // ─── Background Removal ───────────────────────────────────────────────────

    /**
     * Removes the white/near-white background from a signature image,
     * making it transparent so it stamps cleanly on PDFs.
     */
    protected function removeBackground($image): string
    {
        // Get native GD resource
        $gd = $image->core()->native();

        $width  = imagesx($gd);
        $height = imagesy($gd);

        // Create a new true-color image with alpha channel
        $output = imagecreatetruecolor($width, $height);
        imagealphablending($output, false);
        imagesavealpha($output, true);

        // Fill with transparent background
        $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
        imagefill($output, 0, 0, $transparent);

        // Threshold: pixels with R,G,B all above 220 are treated as white/background
        $threshold = 220;

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb  = imagecolorat($gd, $x, $y);
                $r    = ($rgb >> 16) & 0xFF;
                $g    = ($rgb >> 8)  & 0xFF;
                $b    = $rgb         & 0xFF;

                if ($r >= $threshold && $g >= $threshold && $b >= $threshold) {
                    // This is a background pixel — keep transparent
                    continue;
                }

                // Copy foreground pixel (ink/signature)
                $color = imagecolorallocatealpha($output, $r, $g, $b, 0);
                imagesetpixel($output, $x, $y, $color);
            }
        }

        // Capture PNG output
        ob_start();
        imagepng($output);
        $pngData = ob_get_clean();

        imagedestroy($output);

        return $pngData;
    }
}
