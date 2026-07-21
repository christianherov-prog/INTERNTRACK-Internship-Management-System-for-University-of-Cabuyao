<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\InternshipStatuses;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    /** Statuses that stay in the active inbox (not archived). */
    private const ACTIVE_INBOX_STATUSES = [
        'pending_placement',
        'placed',
        'ongoing',
        'active',
        'for_evaluation',
        'suspended',
        'deferred',
    ];

    private const ARCHIVED_INBOX_STATUSES = [
        'completed',
        'terminated',
        'failed',
        'expelled',
    ];

    private function internshipsFor(User $user)
    {
        return Internship::query()
            ->where(function ($q) use ($user) {
                $q->where('student_id', $user->id)
                    ->orWhere('supervisor_id', $user->id)
                    ->orWhere('faculty_id', $user->id)
                    ->orWhere('coordinator_id', $user->id);
            });
    }

    private function assertParticipant(Internship $internship, int $userId): void
    {
        if (!$internship->isParticipant($userId)) {
            abort(403, 'Forbidden. You are not a participant on this internship.');
        }
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing(['studentProfile', 'facultyProfile', 'supervisorProfile']);

        return [
            'id'       => $user->id,
            'username' => $user->username,
            'role'     => $user->role,
            'name'     => $user->profile_name,
        ];
    }

    /** Messages belonging to a role-pair thread on an internship (either direction). */
    private function rolePairQuery(int $internshipId, string $roleA, string $roleB)
    {
        return Message::query()
            ->where('internship_id', $internshipId)
            ->where(function ($q) use ($roleA, $roleB) {
                $q->where(function ($q2) use ($roleA, $roleB) {
                    $q2->where('sender_role', $roleA)->where('recipient_role', $roleB);
                })->orWhere(function ($q2) use ($roleA, $roleB) {
                    $q2->where('sender_role', $roleB)->where('recipient_role', $roleA);
                });
            });
    }

    /**
     * GET /api/v1/messages/conversations
     * Query: archived=0|1, page, per_page
     */
    public function conversations(Request $request)
    {
        $user = $request->user();
        $archived = filter_var($request->query('archived', false), FILTER_VALIDATE_BOOLEAN);
        $statuses = $archived ? self::ARCHIVED_INBOX_STATUSES : self::ACTIVE_INBOX_STATUSES;

        $internships = $this->internshipsFor($user)
            ->whereIn('status', $statuses)
            ->with([
                'student.studentProfile',
                'supervisor.supervisorProfile',
                'faculty.facultyProfile',
                'coordinator.facultyProfile',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $internshipIds = $internships->pluck('id')->all();

        // Single load of messages for these internships (avoids per-peer N+1).
        $allMessages = $internshipIds === []
            ? collect()
            : Message::whereIn('internship_id', $internshipIds)
                ->orderByDesc('created_at')
                ->get();

        $byInternship = $allMessages->groupBy('internship_id');

        $threads = [];

        foreach ($internships as $internship) {
            $messages = $byInternship->get($internship->id, collect());

            foreach ($internship->participantUserIds() as $peerId) {
                if ($peerId === (int) $user->id) {
                    continue;
                }

                $peer = collect([
                    $internship->student,
                    $internship->supervisor,
                    $internship->faculty,
                    $internship->coordinator,
                ])->first(fn ($u) => $u && (int) $u->id === $peerId);

                if (!$peer) {
                    $peer = User::with(['studentProfile', 'facultyProfile', 'supervisorProfile'])->find($peerId);
                }
                if (!$peer) {
                    continue;
                }

                $myRole = $user->role;
                $peerRole = $peer->role;

                $pairMessages = $messages->filter(function (Message $m) use ($myRole, $peerRole) {
                    $roles = [(string) $m->sender_role, (string) $m->recipient_role];
                    return in_array($myRole, $roles, true) && in_array($peerRole, $roles, true);
                })->values();

                // Fallback for legacy rows missing roles: user-pair only
                if ($pairMessages->isEmpty()) {
                    $pairMessages = $messages->filter(function (Message $m) use ($user, $peerId) {
                        return ((int) $m->sender_id === (int) $user->id && (int) $m->recipient_id === $peerId)
                            || ((int) $m->sender_id === $peerId && (int) $m->recipient_id === (int) $user->id);
                    })->values();
                }

                $last = $pairMessages->sortByDesc('created_at')->first();

                $unread = $pairMessages->filter(function (Message $m) use ($user, $myRole) {
                    if ($m->read_at !== null) {
                        return false;
                    }
                    // Current holder of the role sees unread for the role slot
                    return (int) $m->recipient_id === (int) $user->id
                        || (string) $m->recipient_role === (string) $myRole;
                })->count();

                $studentName = optional($internship->student)->profile_name
                    ?? optional($internship->student)->username
                    ?? 'Student';

                $threads[] = [
                    'internship_id'     => $internship->id,
                    'internship_term'   => $internship->term,
                    'internship_status' => InternshipStatuses::normalize($internship->status),
                    'archived'          => $archived,
                    'student_name'      => $studentName,
                    'peer'              => $this->userPayload($peer),
                    'last_message'      => $last ? [
                        'id'         => $last->id,
                        'body'       => $last->body,
                        'sender_id'  => $last->sender_id,
                        'created_at' => $last->created_at,
                        'read_at'    => $last->read_at,
                    ] : null,
                    'unread_count'      => $unread,
                ];
            }
        }

        usort($threads, function ($a, $b) {
            $aTime = $a['last_message']['created_at'] ?? null;
            $bTime = $b['last_message']['created_at'] ?? null;
            if ($aTime == $bTime) {
                return 0;
            }
            if ($aTime === null) {
                return 1;
            }
            if ($bTime === null) {
                return -1;
            }

            return $aTime < $bTime ? 1 : -1;
        });

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(5, (int) $request->query('per_page', 20)));
        $total = count($threads);
        $slice = array_slice($threads, ($page - 1) * $perPage, $perPage);

        $paginator = new LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return ApiResponse::list($paginator);
    }

    /**
     * GET /api/v1/messages/conversations/{internshipId}/{peerId}
     * Query: page, per_page (oldest-first within page; page 1 = newest chunk)
     */
    public function thread(Request $request, int $internshipId, int $peerId)
    {
        $user = $request->user();
        $internship = Internship::findOrFail($internshipId);

        $this->assertParticipant($internship, (int) $user->id);
        $this->assertParticipant($internship, $peerId);

        if ($peerId === (int) $user->id) {
            return response()->json(['message' => 'Cannot open a conversation with yourself.'], 422);
        }

        $peer = User::with(['studentProfile', 'facultyProfile', 'supervisorProfile'])->findOrFail($peerId);
        $myRole = $user->role;
        $peerRole = $peer->role;

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(10, (int) $request->query('per_page', 50)));

        $base = $this->rolePairQuery($internship->id, $myRole, $peerRole);

        // Legacy fallback if no role-tagged messages yet
        if (!(clone $base)->exists()) {
            $base = Message::where('internship_id', $internship->id)
                ->where(function ($q) use ($user, $peerId) {
                    $q->where(function ($q2) use ($user, $peerId) {
                        $q2->where('sender_id', $user->id)->where('recipient_id', $peerId);
                    })->orWhere(function ($q2) use ($user, $peerId) {
                        $q2->where('sender_id', $peerId)->where('recipient_id', $user->id);
                    });
                });
        }

        $paginator = (clone $base)->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        // Mark role-slot unread as read for the current viewer
        (clone $base)
            ->whereNull('read_at')
            ->where(function ($q) use ($user, $myRole) {
                $q->where('recipient_id', $user->id)
                    ->orWhere('recipient_role', $myRole);
            })
            ->update(['read_at' => now()]);

        // Return chronological (oldest → newest) for the page
        $messages = collect($paginator->items())->sortBy('created_at')->values();

        return response()->json([
            'internship' => [
                'id'     => $internship->id,
                'term'   => $internship->term,
                'status' => InternshipStatuses::normalize($internship->status),
            ],
            'peer'     => $this->userPayload($peer),
            'messages' => $messages,
            'meta'     => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /** POST /api/v1/messages */
    public function send(Request $request)
    {
        $request->validate([
            'internship_id' => 'required|integer|exists:internships,id',
            'recipient_id'  => 'required|integer|exists:users,id',
            'body'          => 'required|string|min:1|max:5000',
        ]);

        $user = $request->user();
        $internship = Internship::findOrFail((int) $request->internship_id);
        $recipientId = (int) $request->recipient_id;

        $this->assertParticipant($internship, (int) $user->id);
        $this->assertParticipant($internship, $recipientId);

        if ($recipientId === (int) $user->id) {
            return response()->json(['message' => 'Cannot send a message to yourself.'], 422);
        }

        $recipient = User::findOrFail($recipientId);

        $message = Message::create([
            'internship_id'  => $internship->id,
            'sender_id'      => $user->id,
            'sender_role'    => $user->role,
            'recipient_id'   => $recipientId,
            'recipient_role' => $recipient->role,
            'body'           => trim($request->body),
        ]);

        $senderName = $user->profile_name ?: $user->username;
        $messagesPath = $this->messagesPathForRole($recipient->role);
        $deepLink = $messagesPath.'?internship_id='.$internship->id.'&peer_id='.$user->id;

        Notification::notify(
            $recipientId,
            'new_message',
            'New message from '.$senderName,
            Str::limit(trim($request->body), 120),
            $deepLink,
            [
                'internship_id' => $internship->id,
                'peer_id'       => $user->id,
                'message_id'    => $message->id,
            ]
        );

        audit_log($user->id, 'send_message', [
            'message_id'    => $message->id,
            'internship_id' => $internship->id,
            'recipient_id'  => $recipientId,
        ]);

        return response()->json([
            'message' => 'Message sent.',
            'data'    => $message->fresh(),
        ], 201);
    }

    /** POST /api/v1/messages/mark-read */
    public function markRead(Request $request)
    {
        $request->validate([
            'internship_id' => 'required|integer|exists:internships,id',
            'peer_id'       => 'required|integer|exists:users,id',
        ]);

        $user = $request->user();
        $internship = Internship::findOrFail((int) $request->internship_id);
        $peerId = (int) $request->peer_id;

        $this->assertParticipant($internship, (int) $user->id);
        $this->assertParticipant($internship, $peerId);

        $peer = User::findOrFail($peerId);

        $updated = $this->rolePairQuery($internship->id, $user->role, $peer->role)
            ->whereNull('read_at')
            ->where(function ($q) use ($user) {
                $q->where('recipient_id', $user->id)
                    ->orWhere('recipient_role', $user->role);
            })
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Messages marked as read.',
            'updated' => $updated,
        ]);
    }

    private function messagesPathForRole(?string $role): string
    {
        return match ($role) {
            'student'     => '/student/messages',
            'supervisor'  => '/supervisor/messages',
            'faculty'     => '/faculty/messages',
            'coordinator' => '/coordinator/messages',
            default       => '/',
        };
    }
}
