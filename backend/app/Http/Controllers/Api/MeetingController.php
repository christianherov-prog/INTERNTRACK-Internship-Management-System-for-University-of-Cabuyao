<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeetingController extends Controller
{
    /** GET /api/v1/meetings */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Meeting::with(['attendees.user', 'creator', 'internship.student.studentProfile'])
            ->where('status', '!=', 'cancelled')
            ->orderBy('starts_at');

        if (in_array($user->role, ['coordinator', 'faculty', 'director'], true)) {
            // Coordinators/faculty see meetings they created or attend; directors see all upcoming.
            if ($user->role !== 'director') {
                $query->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                        ->orWhereHas('attendees', fn ($a) => $a->where('user_id', $user->id));
                });
                $query->where(function ($q) {
                    $q->whereNull('internship_id')
                        ->orWhereHas('internship', fn ($i) => $i->inDepartment());
                });
            }
        } else {
            $query->whereHas('attendees', fn ($a) => $a->where('user_id', $user->id));
        }

        $meetings = $query->limit(100)->get()->map(fn (Meeting $m) => $this->payload($m, $user->id));

        return response()->json(['meetings' => $meetings]);
    }

    /** POST /api/v1/meetings — coordinator, faculty, or director */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role, ['coordinator', 'faculty', 'director'], true)) {
            abort(403, 'Only coordinators, faculty, and directors may create meetings.');
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'type' => ['required', Rule::in(Meeting::TYPES)],
            'description' => 'nullable|string|max:2000',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'location' => 'nullable|string|max:255',
            'meeting_url' => 'nullable|url|max:500',
            'internship_id' => 'nullable|integer|exists:internships,id',
            'attendee_ids' => 'nullable|array',
            'attendee_ids.*' => 'integer|exists:users,id',
        ]);

        $meeting = Meeting::create([
            ...collect($data)->except('attendee_ids')->all(),
            'created_by' => $user->id,
            'status' => 'scheduled',
        ]);

        $attendeeIds = collect($data['attendee_ids'] ?? []);

        if (!empty($data['internship_id'])) {
            $internship = Internship::find($data['internship_id']);
            if ($internship) {
                if (in_array($user->role, ['faculty', 'coordinator'], true)) {
                    \App\Support\DepartmentScope::abortUnlessInternshipInDepartment($user, $internship);
                }
                if ($user->role === 'faculty' && (int) $internship->faculty_id !== (int) $user->id) {
                    abort(403, 'You may only schedule meetings for your assigned interns.');
                }
                if ($user->role === 'coordinator' && (int) $internship->coordinator_id !== (int) $user->id) {
                    abort(403, 'You may only schedule meetings for internships you coordinate.');
                }
                // Directors may schedule for any internship (oversight).
                $allowed = collect([
                    $internship->student_id,
                    $internship->supervisor_id,
                    $internship->faculty_id,
                    $internship->coordinator_id,
                    $user->id,
                ])->filter()->map(fn ($id) => (int) $id)->unique();

                // Directors may be invited for oversight (Obj 1.5).
                $directorIds = User::where('role', 'director')->where('is_active', true)->pluck('id')
                    ->map(fn ($id) => (int) $id);
                $allowed = $allowed->merge($directorIds)->unique();

                $extra = $attendeeIds->map(fn ($id) => (int) $id)->diff($allowed);
                if ($extra->isNotEmpty()) {
                    abort(422, 'Meeting attendees must be parties to this internship (or a director).');
                }

                $attendeeIds = $attendeeIds->merge([
                    $internship->student_id,
                    $internship->supervisor_id,
                    $internship->faculty_id,
                    $internship->coordinator_id,
                ]);
            }
        } else {
            // No internship: only allow inviting users the creator may oversee.
            $allowedRoles = ['student', 'supervisor', 'faculty', 'coordinator', 'director'];
            $requested = User::whereIn('id', $attendeeIds)->get();
            foreach ($requested as $invitee) {
                if (!in_array($invitee->role, $allowedRoles, true)) {
                    abort(422, 'Meetings may only invite internship-related roles.');
                }
                if ($user->role === 'faculty') {
                    $ok = Internship::inDepartment()->where('faculty_id', $user->id)
                        ->where(function ($q) use ($invitee) {
                            $q->where('student_id', $invitee->id)
                                ->orWhere('supervisor_id', $invitee->id)
                                ->orWhere('coordinator_id', $invitee->id)
                                ->orWhere('faculty_id', $invitee->id);
                        })
                        ->exists()
                        || (int) $invitee->id === (int) $user->id;
                    if (!$ok) {
                        abort(403, 'You may only invite users related to your assigned internships.');
                    }
                }
                if ($user->role === 'coordinator') {
                    $ok = Internship::inDepartment()->where('coordinator_id', $user->id)
                        ->where(function ($q) use ($invitee) {
                            $q->where('student_id', $invitee->id)
                                ->orWhere('supervisor_id', $invitee->id)
                                ->orWhere('faculty_id', $invitee->id)
                                ->orWhere('coordinator_id', $invitee->id);
                        })
                        ->exists()
                        || (int) $invitee->id === (int) $user->id
                        || $invitee->role === 'director';
                    if (!$ok) {
                        abort(403, 'You may only invite users related to internships you coordinate.');
                    }
                }
            }
            $attendeeIds = $attendeeIds->merge([$user->id]);
        }

        $attendeeIds = $attendeeIds->filter()->unique()->values();
        foreach ($attendeeIds as $uid) {
            MeetingAttendee::create([
                'meeting_id' => $meeting->id,
                'user_id' => $uid,
                'rsvp' => (int) $uid === (int) $user->id ? 'accepted' : 'pending',
            ]);
            if ((int) $uid !== (int) $user->id) {
                $recipient = User::find($uid);
                $link = $this->meetingsPathForRole($recipient?->role);
                Notification::notify(
                    (int) $uid,
                    'meeting_invite',
                    'Meeting invitation',
                    $meeting->title.' — '.optional($meeting->starts_at)->toDayDateTimeString(),
                    $link,
                    ['meeting_id' => $meeting->id]
                );
            }
        }

        return response()->json([
            'message' => 'Meeting scheduled.',
            'meeting' => $this->payload($meeting->fresh(['attendees.user', 'creator', 'internship.student.studentProfile']), $user->id),
        ], 201);
    }

    /** PATCH /api/v1/meetings/{id} */
    public function update(Request $request, int $id)
    {
        $meeting = Meeting::findOrFail($id);
        $user = $request->user();

        if ((int) $meeting->created_by !== (int) $user->id && $user->role !== 'director') {
            abort(403, 'Only the creator may update this meeting.');
        }

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'type' => ['sometimes', Rule::in(Meeting::TYPES)],
            'description' => 'nullable|string|max:2000',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'nullable|date',
            'location' => 'nullable|string|max:255',
            'meeting_url' => 'nullable|url|max:500',
            'status' => ['sometimes', Rule::in(Meeting::STATUSES)],
        ]);

        $meeting->update($data);

        foreach ($meeting->attendees as $attendee) {
            if ((int) $attendee->user_id === (int) $user->id) {
                continue;
            }
            Notification::notify(
                (int) $attendee->user_id,
                'meeting_updated',
                'Meeting updated',
                $meeting->title.' was updated.',
                $this->meetingsPathForRole(User::find($attendee->user_id)?->role),
                ['meeting_id' => $meeting->id]
            );
        }

        return response()->json([
            'message' => 'Meeting updated.',
            'meeting' => $this->payload($meeting->fresh(['attendees.user', 'creator', 'internship.student.studentProfile']), $user->id),
        ]);
    }

    /** PATCH /api/v1/meetings/{id}/rsvp */
    public function rsvp(Request $request, int $id)
    {
        $data = $request->validate([
            'rsvp' => ['required', Rule::in(MeetingAttendee::RSVPS)],
        ]);

        $meeting = Meeting::findOrFail($id);
        $attendee = MeetingAttendee::where('meeting_id', $meeting->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $attendee->update(['rsvp' => $data['rsvp']]);

        return response()->json([
            'message' => 'RSVP saved.',
            'meeting' => $this->payload($meeting->fresh(['attendees.user', 'creator', 'internship.student.studentProfile']), $request->user()->id),
        ]);
    }

    private function meetingsPathForRole(?string $role): string
    {
        return match ($role) {
            'student' => '/student/meetings',
            'supervisor' => '/supervisor/meetings',
            'faculty' => '/faculty/meetings',
            'coordinator' => '/coordinator/meetings',
            'director' => '/director/meetings',
            default => '/meetings',
        };
    }

    private function payload(Meeting $meeting, int $viewerId): array
    {
        $mine = $meeting->attendees->firstWhere('user_id', $viewerId);

        return [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'type' => $meeting->type,
            'description' => $meeting->description,
            'starts_at' => optional($meeting->starts_at)?->toIso8601String(),
            'ends_at' => optional($meeting->ends_at)?->toIso8601String(),
            'location' => $meeting->location,
            'meeting_url' => $meeting->meeting_url,
            'status' => $meeting->status,
            'internship_id' => $meeting->internship_id,
            'created_by' => $meeting->created_by,
            'creator_username' => $meeting->creator?->username,
            'my_rsvp' => $mine?->rsvp,
            'attendees' => $meeting->attendees->map(fn ($a) => [
                'user_id' => $a->user_id,
                'username' => $a->user?->username,
                'role' => $a->user?->role,
                'rsvp' => $a->rsvp,
            ])->values(),
        ];
    }
}
