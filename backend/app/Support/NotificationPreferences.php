<?php

namespace App\Support;

/**
 * Role Settings preference keys and mapping to Notification.type values.
 */
final class NotificationPreferences
{
    /** Default toggles by role (matches frontend Settings pages). */
    public const DEFAULTS = [
        'student' => [
            'emailReminders' => true,
            'attendanceAlerts' => true,
            'evaluationReminders' => false,
            'directMessages' => true,
            'meetingInvites' => true,
        ],
        'coordinator' => [
            // Coordinator-specific
            'pendingDocuments'    => true,
            'placementUpdates'    => true,
            'supervisorApprovals' => true,
            // Faculty-inherited (coordinator acts as faculty supervisor)
            'journalSubmissions'  => true,
            'evaluationReminders' => true,
            'adviseeAlerts'       => false,
            // Shared
            'directMessages'      => true,
            'meetingInvites'      => true,
        ],
        'supervisor' => [
            'attendancePending' => true,
            'journalReviews' => true,
            'evaluationDue' => true,
            'directMessages' => true,
            'meetingInvites' => true,
        ],
        'faculty' => [
            'journalSubmissions' => true,
            'evaluationReminders' => true,
            'adviseeAlerts' => false,
            'directMessages' => true,
            'meetingInvites' => true,
        ],
        'director' => [
            'moaExpiry' => true,
            'companyUpdates' => true,
            'programReports' => false,
            'absorptionUpdates' => true,
            'meetingInvites' => true,
        ],
        'admin' => [
            'syncFailures' => true,
            'staffChanges' => true,
            'mappingGaps' => true,
        ],
    ];

    /**
     * Notification.type → preference key.
     * Unmapped types are always delivered.
     */
    public const TYPE_TO_PREF = [
        'placement_assigned' => 'placementUpdates',
        'document_pending_faculty' => 'pendingDocuments',
        'document_coordinator_approved' => 'pendingDocuments',
        'supervisor_registration' => 'supervisorApprovals',
        'supervisor_approved' => 'supervisorApprovals',
        'supervisor_rejected' => 'supervisorApprovals',
        'journal_reviewed' => 'journalReviews',
        'supervisor_feedback' => 'journalReviews',
        'supervisor_evaluation_submitted' => 'evaluationDue',
        'document_approved' => null,
        'document_rejected' => null,
        'absorption_pending' => 'absorptionUpdates',
        'absorption_recorded' => 'placementUpdates',
        'student_declared_hired' => 'absorptionUpdates',
        'new_message' => 'directMessages',
        'meeting_invite' => 'meetingInvites',
        'meeting_updated' => 'meetingInvites',
    ];

    public static function defaultsForRole(?string $role): array
    {
        return self::DEFAULTS[$role] ?? [];
    }

    public static function allowedKeysForRole(?string $role): array
    {
        return array_keys(self::defaultsForRole($role));
    }

    public static function mergeForUser(?string $role, ?array $stored): array
    {
        return array_merge(self::defaultsForRole($role), $stored ?? []);
    }

    public static function prefKeyForType(string $type): ?string
    {
        return self::TYPE_TO_PREF[$type] ?? null;
    }
}
