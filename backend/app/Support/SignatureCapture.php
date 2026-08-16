<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class SignatureCapture
{
    /**
     * Validate and store a drawn/uploaded signature PNG/JPG, or fallback to user's saved profile signature.
     * Returns [signer_name, path, signed_at].
     *
     * @return array{signer_name: ?string, signature_path: ?string, signed_at: ?\Illuminate\Support\Carbon}
     */
    public static function fromRequest(Request $request, string $directory): array
    {
        if (!$request->hasFile('signature')) {
            $user = $request->user();
            if ($user) {
                $savedPath = "signatures/{$user->id}_processed.png";
                if (Storage::exists($savedPath)) {
                    $signerName = $request->input('signer_name');
                    if (!$signerName) {
                        $profile = $user->studentProfile ?? $user->facultyProfile ?? $user->coordinatorProfile ?? $user->supervisorProfile ?? $user->directorProfile ?? null;
                        if ($profile && isset($profile->first_name, $profile->last_name)) {
                            $signerName = trim("{$profile->first_name} {$profile->last_name}");
                        } else {
                            $signerName = $user->name ?? $user->username ?? 'Authorized Signer';
                        }
                    }
                    return [
                        'signer_name' => trim($signerName),
                        'signature_path' => $savedPath,
                        'signed_at' => now(),
                    ];
                }
            }
            $request->validate([
                'signature' => 'required|file|mimes:png,jpg,jpeg|max:2048',
            ], [
                'signature.required' => 'A signature is required. Please draw or upload your signature, or save one in your profile settings.',
            ]);
        }

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
