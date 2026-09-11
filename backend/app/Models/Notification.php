<?php

namespace App\Models;

use App\Events\NotificationCreated;
use App\Mail\InternTrackNotificationMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
                in_array($type, ['document_approved', 'document_rejected', 'supervisor_approved', 'supervisor_rejected'], true) => null,
                str_contains($type, 'attendance') => 'attendanceAlerts',
                str_contains($type, 'evaluation') => 'evaluationReminders',
                default => 'emailReminders',
            },
            'supervisor' => match (true) {
                in_array($type, ['supervisor_approved', 'supervisor_rejected', 'account_activated'], true) => null,
                str_contains($type, 'attendance') => 'attendancePending',
                str_contains($type, 'journal') => 'journalReviews',
                str_contains($type, 'evaluation') || str_contains($type, 'absorption') => 'evaluationDue',
                default => 'attendancePending',
            },
            'faculty' => match (true) {
                str_contains($type, 'document') => null,
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
     *
     * @param  bool  $sendEmail  When false, only the in-app notification is created.
     */
    public static function notify(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $link = null,
        ?array $data = null,
        bool $sendEmail = true
    ): ?static {
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
            broadcast(new NotificationCreated($notification));
        } catch (\Throwable) {
            // Broadcasting is optional (e.g. no Reverb / queue in tests).
        }

        $emailTypes = [
            'document_rejected',
            'document_approved',
            'supervisor_approved',
            'supervisor_rejected',
            'account_activated',
            'placement_assigned',
            'journal_needs_revision',
            'evaluation_submitted',
        ];

        $shouldEmail = $sendEmail && in_array(strtolower($type), $emailTypes, true);
        if (! $shouldEmail) {
            return $notification;
        }

        $email = trim((string) $user->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Supervisor email unavailable.', [
                'user_id' => $userId,
                'type' => $type,
                'notification_id' => $notification->id,
            ]);

            return $notification;
        }

        $absoluteLink = $link;
        if ($link && ! preg_match('#^https?://#i', $link)) {
            $absoluteLink = rtrim((string) config('app.frontend_url', config('app.url')), '/').'/'.ltrim($link, '/');
        }

        $emailTitle = is_string($data['email_subject'] ?? null) ? $data['email_subject'] : $title;
        $emailBody = is_string($data['email_body'] ?? null) ? $data['email_body'] : $message;

        try {
            Mail::to($email)
                ->send(new InternTrackNotificationMail($emailTitle, $emailBody, $absoluteLink));
        } catch (\Throwable $e) {
            Log::warning('Notification email delivery failed.', [
                'user_id' => $userId,
                'type' => $type,
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $notification;
    }
}
