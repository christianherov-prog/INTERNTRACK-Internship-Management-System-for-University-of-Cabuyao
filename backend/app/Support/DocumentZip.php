<?php

namespace App\Support;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

final class DocumentZip
{
    public const MAX_DOCUMENTS = 25;

    public const MAX_BYTES = 52428800;

    /**
     * @param  list<int>  $documentIds
     */
    public static function download(User $user, array $documentIds): StreamedResponse
    {
        $ids = array_values(array_unique(array_map('intval', $documentIds)));
        if ($ids === []) {
            abort(422, 'Select at least one document.');
        }
        if (count($ids) > self::MAX_DOCUMENTS) {
            abort(422, 'You can download at most '.self::MAX_DOCUMENTS.' documents at a time.');
        }

        $documents = Document::with(['attachments', 'internship.student.studentProfile'])
            ->whereIn('id', $ids)
            ->get();

        if ($documents->count() !== count($ids)) {
            abort(403, 'One or more documents were not found or are not authorized.');
        }

        foreach ($documents as $document) {
            if (! $document->internship || ! InternshipAccess::canView($user, $document->internship)) {
                abort(403, 'You are not authorized to download one or more selected documents.');
            }
        }

        $entries = [];
        $usedNames = [];
        $totalBytes = 0;

        foreach ($documents as $document) {
            $files = [];
            if ($document->file_path) {
                $files[] = [
                    'path' => $document->file_path,
                    'name' => $document->file_name ?: basename((string) $document->file_path),
                ];
            }
            foreach ($document->attachments as $attachment) {
                if ($attachment->file_path) {
                    $files[] = [
                        'path' => $attachment->file_path,
                        'name' => $attachment->file_name ?: basename((string) $attachment->file_path),
                    ];
                }
            }

            foreach ($files as $file) {
                $resolved = self::resolveFile($file['path']);
                if (! $resolved) {
                    continue;
                }

                $size = filesize($resolved['absolute']);
                $totalBytes += $size;
                if ($totalBytes > self::MAX_BYTES) {
                    abort(422, 'Selected files exceed the 50 MB download limit.');
                }

                $student = $document->internship?->student?->studentProfile;
                $prefix = $student
                    ? trim(($student->last_name ?? '').'_'.($student->first_name ?? ''))
                    : 'document';
                $base = self::safeName($prefix.'_'.$document->document_type.'_'.$document->id.'_'.$file['name']);
                $name = $base;
                $i = 2;
                while (isset($usedNames[$name])) {
                    $name = self::safeName(pathinfo($base, PATHINFO_FILENAME).'_'.$i.'.'.(pathinfo($base, PATHINFO_EXTENSION) ?: 'bin'));
                    $i++;
                }
                $usedNames[$name] = true;
                $entries[] = ['absolute' => $resolved['absolute'], 'name' => $name];
            }
        }

        if ($entries === []) {
            abort(422, 'None of the selected documents have a downloadable file.');
        }

        if (! class_exists(ZipArchive::class)) {
            abort(500, 'ZIP downloads are not available on this server.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'itdocs');
        $zip = new ZipArchive();
        if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Unable to create the document archive.');
        }

        foreach ($entries as $entry) {
            $zip->addFile($entry['absolute'], $entry['name']);
        }
        $zip->close();

        $archiveName = 'interntrack-documents-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.zip';

        return response()->streamDownload(function () use ($tmp) {
            readfile($tmp);
            @unlink($tmp);
        }, $archiveName, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * @return array{absolute:string}|null
     */
    private static function resolveFile(string $path): ?array
    {
        $cleanPath = ltrim(str_replace('\\', '/', $path), '/');
        $cleanPath = preg_replace('#^(?:storage/|public/|app/public/)#i', '', $cleanPath);
        if (! is_string($cleanPath) || $cleanPath === '' || str_contains($cleanPath, '..')) {
            return null;
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($cleanPath)) {
                return ['absolute' => Storage::disk($disk)->path($cleanPath)];
            }
        }

        $fallbacks = [
            storage_path('app/public/'.$cleanPath),
            storage_path('app/'.$cleanPath),
            public_path('storage/'.$cleanPath),
            public_path($cleanPath),
        ];
        foreach ($fallbacks as $fp) {
            if (is_file($fp)) {
                return ['absolute' => $fp];
            }
        }

        return null;
    }

    private static function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'document';

        return trim($name, '._') ?: 'document';
    }
}
