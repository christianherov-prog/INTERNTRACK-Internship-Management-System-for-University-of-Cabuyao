<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class SignatureCapture
{
    /**
     * Validate and store a drawn signature PNG/JPG. Returns [signer_name, path, signed_at].
     *
     * @return array{signer_name: string, signature_path: string, signed_at: \Illuminate\Support\Carbon}
     */
    public static function fromRequest(Request $request, string $directory): array
    {
        $data = $request->validate([
            'signer_name' => 'required|string|min:2|max:255',
            'signature' => 'required|file|mimes:png,jpg,jpeg|max:2048',
        ]);

        /** @var UploadedFile $file */
        $file = $data['signature'];
        $path = $file->store($directory, 'local');

        return [
            'signer_name' => trim($data['signer_name']),
            'signature_path' => $path,
            'signed_at' => now(),
        ];
    }

    public static function url(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        // Private disk — clients must use GET /api/v1/files/download?path=
        return null;
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
            Storage::disk('public')->delete($path); // legacy uploads
        }
    }
}
