<?php

namespace App\Models;

use App\Events\NotificationCreated;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'message', 'link', 'data', 'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Map a notification type to the Settings preference key for a given role.
     * Returns null when the type is not preference-gated (always send).
     */
    public static function preferenceKeyFor(string $type, string $role): ?string
    {
        $type = strtolower($type);

        return match ($role) {
            'student' => match (true) {
                str_contains($type, 'attendance') => 'attendanceAlerts',
                str_contains($type, 'evaluation') => 'evaluationReminders',
                default => 'emailReminders',
            },
            'supervisor' => match (true) {
                str_contains($type, 'attendance') => 'attendancePending',
                str_contains($type, 'journal') => 'journalReviews',
                str_contains($type, 'evaluation') || str_contains($type, 'absorption') => 'evaluationDue',
                default => 'attendancePending',
            },
            'faculty' => match (true) {
                str_contains($type, 'journal') => 'journalSubmissions',
                str_contains($type, 'evaluation') => 'evaluationReminders',
                default => 'adviseeAlerts',
            },
            'coordinator' => match (true) {
                str_contains($type, 'document') => 'pendingDocuments',
                str_contains($type, 'placement') || str_contains($type, 'status') => 'placementUpdates',
                str_contains($type, 'supervisor') => 'supervisorApprovals',
                default => 'placementUpdates',
            },
            // Director prefs are informational in the UI; types stay ungated unless we add keys later.
            'director' => null,
            default => null,
        };
    }

    /** Whether the user has opted in (or never opted out) for this notification type. */
    public static function userAllows(User $user, string $type): bool
    {
        $prefs = $user->notification_preferences;
        if (!is_array($prefs) || $prefs === []) {
            return true;
        }

        $key = static::preferenceKeyFor($type, (string) $user->role);
        if ($key === null) {
            return true;
        }

        if (array_key_exists($key, $prefs) && $prefs[$key] === false) {
            return false;
        }

        return true;
    }

    /**
     * Create a notification for a user, respecting their stored preferences.
     * Returns null when the user has opted out of this notification type.
     */
    public static function notify(int $userId, string $type, string $title, string $message, ?string $link = null, ?array $data = null): ?static
    {
        $user = User::query()->find($userId);
        if (!$user) {
            return null;
        }

        if (!static::userAllows($user, $type)) {
            return null;
        }

        $notification = static::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'link'    => $link,
            'data'    => $data,
        ]);

        try {
            broadcast(new NotificationCreated($notification))->toOthers();
        } catch (\Throwable) {
            // Broadcasting is optional (e.g. no Reverb / queue in tests).
        }

        return $notification;
    }
}
