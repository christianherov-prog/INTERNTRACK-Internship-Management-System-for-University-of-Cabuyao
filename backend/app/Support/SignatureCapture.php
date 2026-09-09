<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

final class SignatureCapture
{
    /**
     * Validate and store a drawn/uploaded signature PNG/JPG, or fallback to user's saved profile signature.
     * Returns [signer_name, path, signed_at].
     *
     * @return array{signer_name: ?string, signature_path: ?string, signed_at: ?Carbon}
     */
    public static function fromRequest(Request $request, string $directory): array
    {
        if (! $request->hasFile('signature')) {
            $user = $request->user();
            if ($user) {
                $savedPath = "signatures/{$user->id}_processed.png";
                if (Storage::exists($savedPath)) {
                    $signerName = $request->input('signer_name');
                    if (! $signerName) {
                        $profile = $user->studentProfile ?? $user->facultyProfile ?? $user->coordinatorProfile ?? $user->supervisorProfile ?? $user->directorProfile ?? null;
                        if ($profile && isset($profile->first_name, $profile->last_name)) {
                            $signerName = trim("{$profile->last_name}, {$profile->first_name}");
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
        if (! $path) {
            return null;
        }

        // Private disk — clients must use GET /api/v1/files/download?path=
        return null;
    }

    public static function profilePath(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $path = "signatures/{$user->id}_processed.png";

        return Storage::exists($path) ? $path : null;
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
            Storage::disk('public')->delete($path); // legacy uploads
        }
    }

    /**
     * Convert a PNG/JPEG signature into a PNG with a transparent background.
     * Removes a uniform light (paper) or dark (canvas) fill without inventing strokes.
     */
    public static function transparentPngFromBinary(string $binary): string
    {
        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            throw new \InvalidArgumentException('Could not read the signature image.');
        }

        imagealphablending($source, false);
        imagesavealpha($source, true);

        $png = self::stripUniformBackground($source);
        imagedestroy($source);

        return $png;
    }

    /**
     * Re-run background removal on stored *_processed.png files.
     * Does not invent strokes; only removes a uniform background.
     */
    public static function reprocessStored(): int
    {
        $count = 0;
        foreach (Storage::disk('local')->files('signatures') as $path) {
            if (! str_ends_with(strtolower($path), '.png')) {
                continue;
            }
            $raw = Storage::disk('local')->get($path);
            if (! is_string($raw) || $raw === '') {
                continue;
            }
            try {
                $processed = self::transparentPngFromBinary($raw);
                if (! self::pngHasInk($processed)) {
                    continue;
                }
                Storage::disk('local')->put($path, $processed);
                $count++;
            } catch (\Throwable) {
            }
        }

        return $count;
    }

    private static function stripUniformBackground(\GdImage $source): string
    {
        $width = imagesx($source);
        $height = imagesy($source);

        $output = imagecreatetruecolor($width, $height);
        imagealphablending($output, false);
        imagesavealpha($output, true);
        $clear = imagecolorallocatealpha($output, 0, 0, 0, 127);
        imagefill($output, 0, 0, $clear);

        $bg = self::sampleBackground($source, $width, $height);
        $darkCanvas = $bg['luma'] < 80;

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $px = self::pixel($source, $x, $y);
                if ($px['alpha'] >= 110) {
                    continue;
                }

                $dist = self::colorDistance($px, $bg);
                $luma = self::luma($px['r'], $px['g'], $px['b']);

                if ($darkCanvas) {
                    if ($luma <= $bg['luma'] + 18 || $dist < 28) {
                        continue;
                    }
                    $strength = max(0.0, min(1.0, ($luma - ($bg['luma'] + 18)) / 160));
                    $gdAlpha = (int) round(127 * (1 - $strength));
                    $color = imagecolorallocatealpha($output, 20, 20, 20, $gdAlpha);
                    imagesetpixel($output, $x, $y, $color);

                    continue;
                }

                if ($px['r'] >= 220 && $px['g'] >= 220 && $px['b'] >= 220) {
                    continue;
                }
                if ($dist < 18) {
                    continue;
                }

                $color = imagecolorallocatealpha($output, $px['r'], $px['g'], $px['b'], 0);
                imagesetpixel($output, $x, $y, $color);
            }
        }

        ob_start();
        imagepng($output);
        $png = (string) ob_get_clean();
        imagedestroy($output);

        return $png;
    }

    private static function pngHasInk(string $png): bool
    {
        $im = @imagecreatefromstring($png);
        if ($im === false) {
            return false;
        }
        $width = imagesx($im);
        $height = imagesy($im);
        $ink = 0;
        for ($x = 0; $x < $width; $x += 3) {
            for ($y = 0; $y < $height; $y += 3) {
                $rgba = imagecolorat($im, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                if ($alpha < 110) {
                    $ink++;
                }
            }
        }
        imagedestroy($im);

        return $ink > 8;
    }

    /**
     * @return array{r:int,g:int,b:int,alpha:int,luma:float}
     */
    private static function sampleBackground(\GdImage $source, int $width, int $height): array
    {
        $points = [
            [0, 0],
            [$width - 1, 0],
            [0, $height - 1],
            [$width - 1, $height - 1],
            [(int) floor($width / 2), 0],
            [(int) floor($width / 2), $height - 1],
        ];

        $r = $g = $b = $n = 0;
        foreach ($points as [$x, $y]) {
            $px = self::pixel($source, max(0, $x), max(0, $y));
            if ($px['alpha'] >= 110) {
                continue;
            }
            $r += $px['r'];
            $g += $px['g'];
            $b += $px['b'];
            $n++;
        }

        if ($n === 0) {
            return ['r' => 255, 'g' => 255, 'b' => 255, 'alpha' => 0, 'luma' => 255.0];
        }

        $r = (int) round($r / $n);
        $g = (int) round($g / $n);
        $b = (int) round($b / $n);

        return ['r' => $r, 'g' => $g, 'b' => $b, 'alpha' => 0, 'luma' => self::luma($r, $g, $b)];
    }

    /**
     * @return array{r:int,g:int,b:int,alpha:int}
     */
    private static function pixel(\GdImage $image, int $x, int $y): array
    {
        $rgba = imagecolorat($image, $x, $y);
        $a = ($rgba & 0x7F000000) >> 24;
        if (! imageistruecolor($image)) {
            $colors = imagecolorsforindex($image, $rgba);

            return [
                'r' => (int) $colors['red'],
                'g' => (int) $colors['green'],
                'b' => (int) $colors['blue'],
                'alpha' => (int) ($colors['alpha'] ?? 0),
            ];
        }

        return [
            'r' => ($rgba >> 16) & 0xFF,
            'g' => ($rgba >> 8) & 0xFF,
            'b' => $rgba & 0xFF,
            'alpha' => $a,
        ];
    }

    /**
     * @param  array{r:int,g:int,b:int}  $a
     * @param  array{r:int,g:int,b:int}  $b
     */
    private static function colorDistance(array $a, array $b): float
    {
        $dr = $a['r'] - $b['r'];
        $dg = $a['g'] - $b['g'];
        $db = $a['b'] - $b['b'];

        return sqrt(($dr * $dr) + ($dg * $dg) + ($db * $db));
    }

    private static function luma(int $r, int $g, int $b): float
    {
        return (0.299 * $r) + (0.587 * $g) + (0.114 * $b);
    }
}
