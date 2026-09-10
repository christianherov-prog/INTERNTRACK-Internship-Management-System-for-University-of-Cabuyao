<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Resolves private storage images for official-form HTML/PDF without exposing raw paths.
 */
final class OfficialFormAsset
{
    public static function dataUri(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        try {
            foreach (['local', 'public'] as $disk) {
                if (! Storage::disk($disk)->exists($normalized)) {
                    continue;
                }

                $binary = Storage::disk($disk)->get($normalized);
                if (! is_string($binary) || $binary === '') {
                    continue;
                }

                return 'data:'.self::mime($normalized).';base64,'.base64_encode($binary);
            }
        } catch (Throwable $e) {
            Log::warning('Official form asset could not be read.', [
                'path' => $normalized,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        Log::info('Official form asset missing from storage.', ['path' => $normalized]);

        return null;
    }

    public static function universityLogoDataUri(): ?string
    {
        $candidates = [
            public_path('images/pnc-logo.png'),
            base_path('../frontend/public/images/pnc-logo.png'),
        ];

        foreach ($candidates as $file) {
            if (! is_string($file) || ! is_file($file) || ! is_readable($file)) {
                continue;
            }

            $binary = @file_get_contents($file);
            if (! is_string($binary) || $binary === '') {
                continue;
            }

            return 'data:image/png;base64,'.base64_encode($binary);
        }

        Log::info('University logo file was not found for official-form PDF rendering.');

        return null;
    }

    public static function clockLabel(?string $time): string
    {
        if (! filled($time)) {
            return '';
        }

        $raw = trim((string) $time);
        if (preg_match('/[ap]m/i', $raw)) {
            return $raw;
        }

        $parts = explode(':', $raw);
        $hour = (int) ($parts[0] ?? -1);
        if ($hour < 0) {
            return '';
        }

        $minute = substr(str_pad((string) ($parts[1] ?? '00'), 2, '0', STR_PAD_LEFT), 0, 2);
        $ampm = $hour >= 12 ? 'PM' : 'AM';
        $hour = $hour % 12 ?: 12;

        return $hour.':'.$minute.' '.$ampm;
    }

    private static function mime(string $path): string
    {
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
    }
}
