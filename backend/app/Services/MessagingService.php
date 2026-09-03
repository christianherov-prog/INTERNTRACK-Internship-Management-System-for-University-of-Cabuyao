<?php

namespace App\Services;

use App\Models\Internship;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Legacy conversation helpers — live API is flat DM via MessageController.
 * Do not use for new sends.
 */
class MessagingService
{
    /** @return Collection<int, int> */
    public function partyUserIds(Internship $internship): Collection
    {
        return collect([
            $internship->student_id,
            $internship->supervisor_id,
            $internship->faculty_id,
            $internship->coordinator_id,
        ])->filter()->unique()->values();
    }

    public function assertCanAccessInternship(User $user, Internship $internship): void
    {
        // PALD Director / MISD Admin may open any internship thread for oversight.
        if (in_array($user->role, ['director', 'admin'], true)) {
            return;
        }

        $allowed = $this->partyUserIds($internship)->contains((int) $user->id);
        if (!$allowed) {
            abort(403, 'You are not a participant on this internship conversation.');
        }
    }

    /** Notification / UI path for a role's Messages page. */
    public function messagesPathForRole(?string $role): string
    {
        return match ($role) {
            'student' => '/student/messages',
            'supervisor' => '/supervisor/messages',
            'faculty' => '/faculty/messages',
            'coordinator' => '/coordinator/messages',
            'director' => '/director/messages',
            'admin' => '/admin/dashboard',
            default => '/messages',
        };
    }
}
