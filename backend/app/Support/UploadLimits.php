<?php

namespace App\Support;

/**
 * Single source of truth for InternTrack upload size limits.
 * MIME / extension allow-lists remain module-specific.
 */
class UploadLimits
{
    public static function maxMb(): int
    {
        return max(1, (int) config('interntrack.upload_max_mb', 10));
    }

    /** Laravel `max:` rule uses kilobytes. */
    public static function maxKb(): int
    {
        return self::maxMb() * 1024;
    }

    public static function maxRequestMb(): int
    {
        $configured = (int) config('interntrack.upload_max_request_mb', 30);

        return max(self::maxMb(), $configured);
    }

    public static function maxFiles(): int
    {
        return max(1, (int) config('interntrack.upload_max_files', 5));
    }

    public static function maxBytes(): int
    {
        return self::maxMb() * 1024 * 1024;
    }

    /**
     * Build a Laravel file rule fragment (without required/nullable).
     *
     * @param  list<string>|string  $mimes
     */
    public static function fileRule(array|string $mimes, ?int $maxKb = null): string
    {
        $mimeList = is_array($mimes) ? implode(',', $mimes) : $mimes;
        $kb = $maxKb ?? self::maxKb();

        return "file|mimes:{$mimeList}|max:{$kb}";
    }

    public static function oversizedMessage(?string $filename = null): string
    {
        $limit = self::maxMb();
        if ($filename) {
            return "\"{$filename}\" exceeds the {$limit} MB upload limit.";
        }

        return "The selected file exceeds the maximum allowed size of {$limit} MB.";
    }

    public static function requestTooLargeMessage(): string
    {
        return 'The uploaded files exceed the maximum allowed request size of '.self::maxRequestMb().' MB.';
    }

    /** Custom validation messages for a file field using the global limit. */
    public static function maxMessages(string $attribute = 'file'): array
    {
        $limit = self::maxMb();

        return [
            "{$attribute}.max" => "The {$attribute} must not exceed {$limit} MB.",
            "{$attribute}.*.max" => "Each file must not exceed {$limit} MB.",
        ];
    }
}
