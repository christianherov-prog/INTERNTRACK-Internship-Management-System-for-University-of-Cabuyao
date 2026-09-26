<?php

namespace App\Services;

use App\Models\Internship;
use App\Models\JournalEntry;
use App\Models\Notification;
use App\Models\User;
use App\Support\DepartmentScope;
use App\Support\ManilaTime;
use Illuminate\Support\Facades\DB;

/**
 * Industry-supervisor intern feedback stored on a dedicated journal note
 * (status=supervisor_note, week 0) so it is not mixed with FO-31 weekly journals
 * or Faculty journal review.
 */
class SupervisorFeedbackService
{
    public const NOTE_STATUS = 'supervisor_note';

    public function assertAssignedSupervisor(User $supervisor, Internship $internship): void
    {
        if ((int) $internship->supervisor_id !== (int) $supervisor->id) {
            abort(403, 'You may only provide feedback for your assigned interns.');
        }
    }

    public function noteForInternship(Internship $internship): ?JournalEntry
    {
        return $internship->journals()
            ->where('status', self::NOTE_STATUS)
            ->whereNotNull('supervisor_feedback')
            ->latest('supervisor_reviewed_at')
            ->latest('id')
            ->first();
    }

    public function upsert(Internship $internship, User $supervisor, string $feedback): JournalEntry
    {
        $this->assertAssignedSupervisor($supervisor, $internship);

        $note = DB::transaction(function () use ($internship, $supervisor, $feedback) {
            Internship::whereKey($internship->id)->lockForUpdate()->firstOrFail();
            $existing = JournalEntry::withTrashed()
                ->where('internship_id', $internship->id)
                ->where('status', self::NOTE_STATUS)
                ->lockForUpdate()
                ->first();

            $payload = [
                'entry_number' => 0,
                'week_number' => 0,
                'date' => now()->toDateString(),
                'activities_summary' => '',
                'status' => self::NOTE_STATUS,
                'supervisor_feedback' => $feedback,
                'supervisor_reviewed_by' => $supervisor->id,
                'supervisor_reviewed_at' => now(),
            ];

            if ($existing) {
                if (method_exists($existing, 'trashed') && $existing->trashed()) {
                    $existing->restore();
                }
                $existing->update($payload);

                return $existing->fresh(['internship.student.studentProfile', 'internship.company', 'internship.supervisor.supervisorProfile']);
            }

            return $internship->journals()->create($payload)
                ->fresh(['internship.student.studentProfile', 'internship.company', 'internship.supervisor.supervisorProfile']);
        });

        $this->notifyAudience($note, $internship, $feedback);

        return $note;
    }

    public function updateNote(JournalEntry $note, User $supervisor, string $feedback): JournalEntry
    {
        $internship = $note->internship ?? Internship::findOrFail($note->internship_id);
        $this->assertAssignedSupervisor($supervisor, $internship);

        if ($note->status !== self::NOTE_STATUS && (int) $note->supervisor_reviewed_by !== (int) $supervisor->id) {
            abort(403, 'You may only edit your own intern feedback.');
        }

        $note->update([
            'supervisor_feedback' => $feedback,
            'supervisor_reviewed_by' => $supervisor->id,
            'supervisor_reviewed_at' => now(),
        ]);

        $fresh = $note->fresh(['internship.student.studentProfile', 'internship.company', 'internship.supervisor.supervisorProfile']);
        $this->notifyAudience($fresh, $internship, $feedback, updated: true);

        return $fresh;
    }

    public function deleteNote(JournalEntry $note, User $supervisor): void
    {
        $internship = $note->internship ?? Internship::findOrFail($note->internship_id);
        $this->assertAssignedSupervisor($supervisor, $internship);

        if ($note->status === self::NOTE_STATUS) {
            $note->delete();

            return;
        }

        if ((int) $note->supervisor_reviewed_by !== (int) $supervisor->id) {
            abort(403, 'You may only remove your own intern feedback.');
        }

        $note->update([
            'supervisor_feedback' => null,
            'supervisor_reviewed_by' => null,
            'supervisor_reviewed_at' => null,
        ]);
    }

    public function serialize(?JournalEntry $note): ?array
    {
        if (! $note || ! filled($note->supervisor_feedback)) {
            return null;
        }

        $at = $note->supervisor_reviewed_at;

        return [
            'id' => $note->id,
            'internship_id' => $note->internship_id,
            'student_id' => $note->internship?->student_id,
            'supervisor_id' => $note->supervisor_reviewed_by,
            'feedback' => $note->supervisor_feedback,
            'supervisor_feedback' => $note->supervisor_feedback,
            'supervisor_reviewed_at' => optional($at)?->toIso8601String(),
            'supervisor_reviewed_at_manila' => ManilaTime::datetimeDisplay($at),
            'editable' => $note->status === self::NOTE_STATUS,
            'internship' => $note->relationLoaded('internship') ? $note->internship : null,
        ];
    }

    private function notifyAudience(JournalEntry $note, Internship $internship, string $feedback, bool $updated = false): void
    {
        $internship->loadMissing('student.studentProfile.program');
        $title = $updated ? 'Industry Supervisor feedback updated' : 'New feedback from Industry Supervisor';
        $body = $updated
            ? 'Your Industry Supervisor updated intern feedback for this week.'
            : 'Your Industry Supervisor submitted intern feedback for this week.';

        if ($internship->student_id) {
            Notification::notify(
                (int) $internship->student_id,
                'supervisor_feedback',
                $title,
                $body,
                '/student/records',
                ['journal_id' => $note->id, 'internship_id' => $internship->id]
            );
        }

        if ($internship->faculty_id) {
            Notification::notify(
                (int) $internship->faculty_id,
                'supervisor_feedback_submitted',
                $updated ? 'Supervisor feedback updated' : 'Supervisor feedback submitted',
                'Industry supervisor submitted feedback for an assigned intern.',
                '/faculty/assigned-students',
                ['journal_id' => $note->id, 'student_id' => $internship->student_id, 'internship_id' => $internship->id]
            );
        }

        foreach (DepartmentScope::coordinatorIdsForStudent($internship->student) as $coordId) {
            Notification::notify(
                $coordId,
                'supervisor_feedback_submitted',
                $updated ? 'Supervisor feedback updated' : 'Supervisor feedback submitted',
                'Industry supervisor submitted feedback for an assigned intern.',
                '/coordinator/evaluations',
                ['journal_id' => $note->id, 'student_id' => $internship->student_id, 'internship_id' => $internship->id]
            );
        }
    }
}
