<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Models\Message;
use App\Models\MessageThreadState;
use App\Models\Notification;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\InternshipStatuses;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        // Directors have no internship FK — MERGE-ONLY keeps director messaging as oversight access.
        if ($user->role === 'director') {
            return Internship::query();
        }

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
        if ($internship->isParticipant($userId)) {
            return;
        }

        $role = User::query()->whereKey($userId)->value('role');
        if ($role === 'director') {
            return;
        }

        abort(403, 'Forbidden. You are not a participant on this internship.');
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing(['studentProfile', 'facultyProfile', 'supervisorProfile']);

        return [
            'id'        => $user->id,
            'username'  => $user->username,
            'role'      => $user->role,
            'name'      => $user->profile_name,
            'avatar'    => $this->initialsFromName($user->profile_name),
            'avatarUrl' => $this->resolveAvatarUrl($user->avatar_path),
        ];
    }

    /** Host-aware URL construction pointing to PublicAvatarController endpoint. */
    private function resolveAvatarUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        $filename   = basename($normalized);

        if ($filename === '' || $filename === '.' || $filename === '..') {
            return null;
        }

        $base = rtrim(request()->getSchemeAndHttpHost(), '/');

        return $base . '/api/v1/media/avatars/' . $filename;
    }

    private function initialsFromName(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $first = $parts[0] ?? 'U';
        $last = end($parts) ?: '';

        return strtoupper(substr($first, 0, 1).substr($last, 0, 1));
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
     *
     * Active = live internship threads the user has NOT archived.
     * Archived = user-archived threads OR ended-internship threads.
     */
    public function conversations(Request $request)
    {
        $user = $request->user();
        $wantArchived = filter_var($request->query('archived', false), FILTER_VALIDATE_BOOLEAN);

        $allStatuses = array_values(array_unique(array_merge(
            self::ACTIVE_INBOX_STATUSES,
            self::ARCHIVED_INBOX_STATUSES
        )));

        $internships = $this->internshipsFor($user)
            ->whereIn('status', $allStatuses)
            ->with([
                'student.studentProfile',
                'supervisor.supervisorProfile',
                'faculty.facultyProfile',
                'coordinator.facultyProfile',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $internshipIds = $internships->pluck('id')->all();

        $allMessages = $internshipIds === []
            ? collect()
            : Message::whereIn('internship_id', $internshipIds)
                ->orderByDesc('created_at')
                ->get();

        $byInternship = $allMessages->groupBy('internship_id');

        $states = MessageThreadState::where('user_id', $user->id)
            ->whereIn('internship_id', $internshipIds ?: [0])
            ->get()
            ->keyBy(fn (MessageThreadState $s) => $s->internship_id.'-'.$s->peer_id);

        $threads = [];

        foreach ($internships as $internship) {
            $messages = $byInternship->get($internship->id, collect());
            $statusNorm = InternshipStatuses::normalize($internship->status);
            $isEnded = in_array($internship->status, self::ARCHIVED_INBOX_STATUSES, true)
                || in_array($statusNorm, self::ARCHIVED_INBOX_STATUSES, true);
            $isLive = in_array($internship->status, self::ACTIVE_INBOX_STATUSES, true)
                || in_array($statusNorm, self::ACTIVE_INBOX_STATUSES, true);

            $peerIds = $internship->participantUserIds();
            foreach ($messages as $m) {
                if ((int) $m->sender_id === (int) $user->id) {
                    $peerIds[] = (int) $m->recipient_id;
                } elseif ((int) $m->recipient_id === (int) $user->id) {
                    $peerIds[] = (int) $m->sender_id;
                }
            }
            $peerIds = array_values(array_unique(array_filter($peerIds)));

            foreach ($peerIds as $peerId) {
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

                $stateKey = $internship->id.'-'.$peerId;
                $state = $states->get($stateKey);
                $userArchived = $state && $state->archived_at !== null;

                $inArchivedTab = $userArchived || $isEnded;
                $inActiveTab = $isLive && !$userArchived;

                if ($wantArchived && !$inArchivedTab) {
                    continue;
                }
                if (!$wantArchived && !$inActiveTab) {
                    continue;
                }

                $myRole = $user->role;
                $peerRole = $peer->role;

                $pairMessages = $messages->filter(function (Message $m) use ($myRole, $peerRole) {
                    $roles = [(string) $m->sender_role, (string) $m->recipient_role];

                    return in_array($myRole, $roles, true) && in_array($peerRole, $roles, true);
                })->values();

                if ($pairMessages->isEmpty()) {
                    $pairMessages = $messages->filter(function (Message $m) use ($user, $peerId) {
                        return ((int) $m->sender_id === (int) $user->id && (int) $m->recipient_id === $peerId)
                            || ((int) $m->sender_id === $peerId && (int) $m->recipient_id === (int) $user->id);
                    })->values();
                }

                if ($state?->cleared_before_message_id) {
                    $clearedId = (int) $state->cleared_before_message_id;
                    $pairMessages = $pairMessages->filter(
                        fn (Message $m) => (int) $m->id > $clearedId
                    )->values();
                } elseif ($state?->cleared_before) {
                    $clearedBefore = $state->cleared_before;
                    $pairMessages = $pairMessages->filter(
                        fn (Message $m) => $m->created_at && $m->created_at->gt($clearedBefore)
                    )->values();
                }

                $last = $pairMessages->sortByDesc('created_at')->first();

                $unread = $pairMessages->filter(function (Message $m) use ($user, $myRole) {
                    if ($m->unsent_at !== null) {
                        return false;
                    }
                    if ($m->read_at !== null) {
                        return false;
                    }

                    return (int) $m->recipient_id === (int) $user->id
                        || (string) $m->recipient_role === (string) $myRole;
                })->count();

                $studentName = optional($internship->student)->profile_name
                    ?? optional($internship->student)->username
                    ?? 'Student';

                $threads[] = [
                    'internship_id'     => $internship->id,
                    'internship_term'   => $internship->term,
                    'internship_status' => $statusNorm,
                    'archived'          => $inArchivedTab,
                    'user_archived'     => (bool) $userArchived,
                    'student_name'      => $studentName,
                    'peer'              => $this->userPayload($peer),
                    'last_message'      => $last ? $this->lastMessagePreview($last) : null,
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

        $state = MessageThreadState::where('user_id', $user->id)
            ->where('internship_id', $internship->id)
            ->where('peer_id', $peerId)
            ->first();

        if ($state?->cleared_before_message_id) {
            $base = (clone $base)->where('id', '>', (int) $state->cleared_before_message_id);
        } elseif ($state?->cleared_before) {
            $base = (clone $base)->where('created_at', '>', $state->cleared_before);
        }

        $paginator = (clone $base)->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        // Mark role-slot unread as read for the current viewer (skip unsent)
        (clone $base)
            ->whereNull('read_at')
            ->whereNull('unsent_at')
            ->where(function ($q) use ($user, $myRole) {
                $q->where('recipient_id', $user->id)
                    ->orWhere('recipient_role', $myRole);
            })
            ->update(['read_at' => now()]);

        $messages = collect($paginator->items())
            ->sortBy('created_at')
            ->values()
            ->map(fn (Message $m) => $m->toClientArray());

        return response()->json([
            'internship' => [
                'id'     => $internship->id,
                'term'   => $internship->term,
                'status' => InternshipStatuses::normalize($internship->status),
            ],
            'peer'          => $this->userPayload($peer),
            'user_archived' => (bool) ($state?->archived_at),
            'cleared_before'=> $state?->cleared_before,
            'messages'      => $messages,
            'meta'          => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /** POST /api/v1/messages — JSON text and/or multipart attachment. */
    public function send(Request $request)
    {
        $request->validate([
            'internship_id' => 'required|integer|exists:internships,id',
            'recipient_id'  => 'required|integer|exists:users,id',
            'body'          => 'nullable|string|max:5000',
            'attachment'    => [
                'nullable',
                'file',
                'max:'.Message::ATTACHMENT_MAX_KB,
                'mimes:'.implode(',', Message::ATTACHMENT_MIMES),
            ],
        ], [
            'attachment.max'   => 'The attachment must not be larger than 10 MB.',
            'attachment.mimes' => 'The attachment must be an image (jpg, jpeg, png, gif, webp) or document (pdf, doc, docx, xls, xlsx).',
        ]);

        $user = $request->user();
        $internship = Internship::findOrFail((int) $request->internship_id);
        $recipientId = (int) $request->recipient_id;

        $this->assertParticipant($internship, (int) $user->id);
        $this->assertParticipant($internship, $recipientId);

        if ($recipientId === (int) $user->id) {
            return response()->json(['message' => 'Cannot send a message to yourself.'], 422);
        }

        $body = trim((string) $request->input('body', ''));
        $hasFile = $request->hasFile('attachment');

        if ($body === '' && !$hasFile) {
            throw ValidationException::withMessages([
                'body' => ['Enter a message or attach a file.'],
            ]);
        }

        $recipient = User::findOrFail($recipientId);

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentMime = null;
        $attachmentSize = null;

        if ($hasFile) {
            $file = $request->file('attachment');
            $originalName = $this->sanitizeAttachmentFilename($file->getClientOriginalName());
            $safeBase = pathinfo($originalName, PATHINFO_FILENAME);
            $safeBase = Str::slug($safeBase) ?: 'file';
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
            $storedName = $safeBase.'_'.Str::lower(Str::random(8)).'.'.$ext;
            $dir = 'messages/'.$internship->id;
            $attachmentPath = $file->storeAs($dir, $storedName, 'local');
            $attachmentName = $originalName;
            $attachmentMime = $file->getMimeType() ?: $file->getClientMimeType();
            $attachmentSize = (int) $file->getSize();
        }

        $message = Message::create([
            'internship_id'             => $internship->id,
            'sender_id'                 => $user->id,
            'sender_role'               => $user->role,
            'recipient_id'              => $recipientId,
            'recipient_role'            => $recipient->role,
            'body'                      => $body,
            'attachment_path'           => $attachmentPath,
            'attachment_original_name'  => $attachmentName,
            'attachment_mime'           => $attachmentMime,
            'attachment_size'           => $attachmentSize,
        ]);

        $senderName = $user->profile_name ?: $user->username;
        $messagesPath = $this->messagesPathForRole($recipient->role);
        $deepLink = $messagesPath.'?internship_id='.$internship->id.'&peer_id='.$user->id;

        $notifPreview = $body !== ''
            ? Str::limit($body, 120)
            : ('Sent an attachment'.($attachmentName ? ': '.$attachmentName : ''));

        Notification::notify(
            $recipientId,
            'new_message',
            'New message from '.$senderName,
            $notifPreview,
            $deepLink,
            [
                'internship_id' => $internship->id,
                'peer_id'       => $user->id,
                'message_id'    => $message->id,
                'has_attachment'=> (bool) $attachmentPath,
            ]
        );

        audit_log($user->id, 'send_message', [
            'message_id'     => $message->id,
            'internship_id'  => $internship->id,
            'recipient_id'   => $recipientId,
            'has_attachment' => (bool) $attachmentPath,
        ]);

        return response()->json([
            'message' => 'Message sent.',
            'data'    => $message->fresh()->toClientArray(),
        ], 201);
    }

    /** Conversation-list snippet including attachment hint. */
    private function lastMessagePreview(Message $last): array
    {
        $body = $last->display_body;
        $hasAttachment = !$last->is_unsent && $last->hasAttachment();

        if (!$last->is_unsent && $body === '' && $hasAttachment) {
            $name = $last->attachment_original_name;
            $body = $name ? ('📎 '.$name) : '📎 Attachment';
        } elseif (!$last->is_unsent && $hasAttachment && $body !== '') {
            // Keep text preview; client can still show paperclip via has_attachment.
        }

        return [
            'id'             => $last->id,
            'body'           => $body,
            'is_unsent'      => $last->is_unsent,
            'has_attachment' => $hasAttachment,
            'sender_id'      => $last->sender_id,
            'created_at'     => $last->created_at,
            'read_at'        => $last->read_at,
        ];
    }

    /** Strip path segments and unsafe characters from client filenames. */
    private function sanitizeAttachmentFilename(string $name): string
    {
        $name = str_replace(["\0", '\\'], '', $name);
        $name = basename(str_replace(['/', '\\'], '-', $name));
        $name = preg_replace('/[^\w.\- ()\[\]]+/u', '_', $name) ?: 'file';
        $name = trim($name, '._ ');

        return Str::limit($name !== '' ? $name : 'file', 180, '');
    }

    /**
     * POST /api/v1/messages/conversations/{internshipId}/{peerId}/archive
     * Body: { archived: true|false }
     */
    public function setArchived(Request $request, int $internshipId, int $peerId)
    {
        $request->validate([
            'archived' => 'required|boolean',
        ]);

        $user = $request->user();
        $internship = Internship::findOrFail($internshipId);
        $this->assertParticipant($internship, (int) $user->id);
        $this->assertParticipant($internship, $peerId);

        if ($peerId === (int) $user->id) {
            return response()->json(['message' => 'Invalid peer.'], 422);
        }

        $state = MessageThreadState::forThread((int) $user->id, $internshipId, $peerId);
        $state->archived_at = $request->boolean('archived') ? now() : null;
        $state->save();

        audit_log($user->id, $request->boolean('archived') ? 'archive_message_thread' : 'unarchive_message_thread', [
            'internship_id' => $internshipId,
            'peer_id'       => $peerId,
        ]);

        return response()->json([
            'message'       => $request->boolean('archived') ? 'Conversation archived.' : 'Conversation moved to Active.',
            'user_archived' => $state->archived_at !== null,
            'archived_at'   => $state->archived_at,
        ]);
    }

    /**
     * POST /api/v1/messages/conversations/{internshipId}/{peerId}/clear
     * Clears history for the current user only (cleared_before = now).
     */
    public function clearThread(Request $request, int $internshipId, int $peerId)
    {
        $user = $request->user();
        $internship = Internship::findOrFail($internshipId);
        $this->assertParticipant($internship, (int) $user->id);
        $this->assertParticipant($internship, $peerId);

        if ($peerId === (int) $user->id) {
            return response()->json(['message' => 'Invalid peer.'], 422);
        }

        $state = MessageThreadState::forThread((int) $user->id, $internshipId, $peerId);

        $pairQuery = Message::where('internship_id', $internshipId)
            ->where(function ($q) use ($user, $peerId) {
                $q->where(function ($q2) use ($user, $peerId) {
                    $q2->where('sender_id', $user->id)->where('recipient_id', $peerId);
                })->orWhere(function ($q2) use ($user, $peerId) {
                    $q2->where('sender_id', $peerId)->where('recipient_id', $user->id);
                });
            });

        $maxId = (int) ($pairQuery->max('id') ?? 0);

        $state->cleared_before = now();
        $state->cleared_before_message_id = $maxId;
        $state->save();

        audit_log($user->id, 'clear_message_thread', [
            'internship_id'              => $internshipId,
            'peer_id'                    => $peerId,
            'cleared_before'             => $state->cleared_before?->toIso8601String(),
            'cleared_before_message_id'  => $state->cleared_before_message_id,
        ]);

        return response()->json([
            'message'                    => 'Conversation cleared for your view.',
            'cleared_before'             => $state->cleared_before,
            'cleared_before_message_id'  => $state->cleared_before_message_id,
        ]);
    }

    /**
     * POST /api/v1/messages/{id}/unsend
     * Soft-unsend: keeps row, sets unsent_at; clients show placeholder for both users.
     */
    public function unsend(Request $request, int $id)
    {
        $user = $request->user();
        $message = Message::findOrFail($id);

        if ((int) $message->sender_id !== (int) $user->id) {
            return response()->json(['message' => 'You can only unsend messages you sent.'], 403);
        }

        if ($message->unsent_at) {
            return response()->json([
                'message' => 'Message already unsent.',
                'data'    => $message->toClientArray(),
            ]);
        }

        $internship = Internship::findOrFail($message->internship_id);
        $this->assertParticipant($internship, (int) $user->id);

        // Remove file from disk so unsent attachments are no longer viewable/downloadable.
        $message->deleteStoredAttachment();
        $message->unsent_at = now();
        $message->attachment_path = null;
        $message->attachment_original_name = null;
        $message->attachment_mime = null;
        $message->attachment_size = null;
        $message->save();

        audit_log($user->id, 'unsend_message', [
            'message_id'    => $message->id,
            'internship_id' => $message->internship_id,
        ]);

        return response()->json([
            'message' => 'Message unsent.',
            'data'    => $message->fresh()->toClientArray(),
        ]);
    }

    private function messagesPathForRole(?string $role): string
    {
        return match ($role) {
            'student'     => '/student/messages',
            'supervisor'  => '/supervisor/messages',
            'faculty'     => '/faculty/messages',
            'coordinator' => '/coordinator/messages',
            'director'    => '/director/messages',
            default       => '/',
        };
    }
}
