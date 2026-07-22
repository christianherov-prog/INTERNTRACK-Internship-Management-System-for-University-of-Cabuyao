<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    public const UNSENT_PLACEHOLDER = 'This message was unsent';

    /** Allowed upload extensions (mirrored in MessageController validation). */
    public const ATTACHMENT_MIMES = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx',
    ];

    public const ATTACHMENT_MAX_KB = 10240; // 10 MB

    protected $fillable = [
        'internship_id',
        'sender_id',
        'sender_role',
        'recipient_id',
        'recipient_role',
        'body',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime',
        'attachment_size',
        'read_at',
        'unsent_at',
    ];

    protected $casts = [
        'read_at'          => 'datetime',
        'unsent_at'        => 'datetime',
        'attachment_size'  => 'integer',
    ];

    protected $appends = [
        'is_unsent',
        'display_body',
    ];

    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function getIsUnsentAttribute(): bool
    {
        return $this->unsent_at !== null;
    }

    public function getDisplayBodyAttribute(): string
    {
        return $this->is_unsent ? self::UNSENT_PLACEHOLDER : (string) $this->body;
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    public function isImageAttachment(): bool
    {
        $mime = strtolower((string) $this->attachment_mime);
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        $ext = strtolower(pathinfo((string) $this->attachment_original_name, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    /** Delete stored file from the public disk (used on unsend). */
    public function deleteStoredAttachment(): void
    {
        if ($this->attachment_path) {
            Storage::disk('public')->delete($this->attachment_path);
        }
    }

    /** Public API shape — never exposes original body or attachment after unsend. */
    public function toClientArray(): array
    {
        $attachment = null;
        if (!$this->is_unsent && $this->hasAttachment()) {
            $attachment = [
                'url'           => $this->resolvePublicUrl($this->attachment_path),
                'filename'      => $this->attachment_original_name,
                'mime'          => $this->attachment_mime,
                'size'          => $this->attachment_size,
                'is_image'      => $this->isImageAttachment(),
            ];
        }

        $body = $this->display_body;
        // Attachment-only messages: empty body string for clients (not the unsent placeholder).
        if (!$this->is_unsent && $body === '' && $attachment) {
            $body = '';
        }

        return [
            'id'             => $this->id,
            'internship_id'  => $this->internship_id,
            'sender_id'      => $this->sender_id,
            'sender_role'    => $this->sender_role,
            'recipient_id'   => $this->recipient_id,
            'recipient_role' => $this->recipient_role,
            'body'           => $body,
            'attachment'     => $attachment,
            'is_unsent'      => $this->is_unsent,
            'read_at'        => $this->read_at,
            'unsent_at'      => $this->unsent_at,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }

    private function resolvePublicUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $base = rtrim(request()->getSchemeAndHttpHost(), '/');

        return $base.'/storage/'.ltrim($path, '/');
    }
}
