<?php

namespace App\Support;

use App\Models\Program;

/**
 * Canonical program codes for University of Cabuyao offerings.
 * Never derive a code from the first 10 letters of the full name (that produced "BACHELORO").
 */
final class ProgramCatalog
{
    public const CODES_BY_NAME = [
        'Bachelor of Science in Information Technology' => 'BSIT',
        'Bachelor of Science in Computer Science' => 'BSCS',
        'Bachelor of Secondary Education' => 'BSED',
        'Bachelor of Elementary Education' => 'BEED',
        'Bachelor of Science in Civil Engineering' => 'BSCE',
        'Bachelor of Science in Computer Engineering' => 'BSCPE',
        'Bachelor of Science in Nursing' => 'BSN',
        'Bachelor of Science in Psychology' => 'BSPSY',
        'Bachelor of Science in Business Administration major in Marketing Management' => 'BSBAMM',
        'Bachelor of Science in Business Administration major in Financial Management' => 'BSBAFM',
        'Bachelor of Science in Accountancy' => 'BSA',
    ];

    public static function codeForName(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        if (isset(self::CODES_BY_NAME[$name])) {
            return self::CODES_BY_NAME[$name];
        }

        foreach (self::CODES_BY_NAME as $full => $code) {
            if (strcasecmp($full, $name) === 0) {
                return $code;
            }
        }

        return null;
    }

    public static function nameForCode(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }

        $flip = array_flip(self::CODES_BY_NAME);

        return $flip[$code] ?? null;
    }

    public static function looksTruncated(?string $code): bool
    {
        $code = strtoupper(trim((string) $code));

        return $code !== '' && str_starts_with($code, 'BACHELOR');
    }

    public static function displayName(?string $stored, ?string $fallback = null): string
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return $fallback ?: '';
        }

        if (self::looksTruncated($stored)) {
            return self::nameForCode(self::codeForName($fallback)) ?: ($fallback ?: $stored);
        }

        $fromCode = self::nameForCode($stored);

        return $fromCode ?: $stored;
    }

    /**
     * Repair truncated/generated codes on existing program rows. Never deletes rows.
     */
    public static function repairStoredCodes(): int
    {
        $fixed = 0;

        Program::query()->orderBy('id')->each(function (Program $program) use (&$fixed) {
            $correct = self::codeForName($program->name);
            if (! $correct) {
                return;
            }

            if ($program->code !== $correct) {
                $program->code = $correct;
                $program->save();
                $fixed++;
            }
        });

        return $fixed;
    }
}
