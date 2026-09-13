<?php

namespace App\Support;

use App\Models\HteRequest;
use App\Models\InternshipApplication;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Private MOA uploads for company applications and new-HTE requests.
 */
final class PlacementMoa
{
    public static function rule(): string
    {
        return 'nullable|'.UploadLimits::fileRule('pdf');
    }

    /** @deprecated Prefer rule() */
    public const RULE = 'nullable|file|mimes:pdf|max:10240';

    public static function store(UploadedFile $file, string $kind, int $ownerId): string
    {
        $kind = $kind === 'hte' ? 'hte-requests' : 'applications';
        $name = Str::uuid()->toString().'.pdf';

        return $file->storeAs('placement-moa/'.$kind.'/'.$ownerId, $name, 'local');
    }

    public static function delete(?string $path): void
    {
        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public static function authorize(User $user, string $path): bool
    {
        if (in_array($user->role, ['director', 'admin'], true)) {
            return true;
        }

        if (! str_starts_with($path, 'placement-moa/')) {
            return false;
        }

        $application = InternshipApplication::where('moa_path', $path)->first();
        if ($application) {
            return self::canAccessStudentRecord($user, (int) $application->student_id);
        }

        $request = HteRequest::where('moa_path', $path)->first();
        if ($request) {
            return self::canAccessStudentRecord($user, (int) $request->student_id);
        }

        return false;
    }

    public static function canAccessStudentRecord(User $user, int $studentId): bool
    {
        if (in_array($user->role, ['director', 'admin'], true)) {
            return true;
        }

        if ($user->role === 'student') {
            return (int) $user->id === $studentId;
        }

        if ($user->role === 'coordinator') {
            try {
                DepartmentScope::abortUnlessStudentInDepartment($user, $studentId);

                return true;
            } catch (HttpException) {
                return false;
            }
        }

        return false;
    }

    public static function notifyDepartmentCoordinators(User $student, string $type, string $title, string $message, string $link): void
    {
        $ids = DepartmentScope::coordinatorIdsForStudent($student);

        foreach ($ids as $coordinatorId) {
            Notification::notify((int) $coordinatorId, $type, $title, $message, $link);
        }
    }
}
