<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Announcement extends Model
{
    use SoftDeletes;

    /** Same allow-list / size as messaging attachments for consistency. */
    public const ATTACHMENT_MIMES = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx',
    ];

    public const ATTACHMENT_MAX_KB = 10240; // 10 MB

    public const CATEGORIES = [
        'general',
        'policy_update',
    ];

    protected $fillable = [
        'created_by',
        'title',
        'content',
        'target_role',
        'category',
        'is_pinned',
        'expires_at',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime',
        'attachment_size',
    ];

    protected $casts = [
        'is_pinned'        => 'boolean',
        'expires_at'       => 'datetime',
        'attachment_size'  => 'integer',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function deleteStoredAttachment(): void
    {
        if ($this->attachment_path) {
            Storage::disk('public')->delete($this->attachment_path);
        }
    }

    public function clearAttachmentFields(): void
    {
        $this->attachment_path = null;
        $this->attachment_original_name = null;
        $this->attachment_mime = null;
        $this->attachment_size = null;
    }

    /** Store upload on public disk under announcements/{id or tmp}/. */
    public function storeUploadedAttachment(UploadedFile $file): void
    {
        $originalName = self::sanitizeAttachmentFilename($file->getClientOriginalName());
        $safeBase = pathinfo($originalName, PATHINFO_FILENAME);
        $safeBase = Str::slug($safeBase) ?: 'file';
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $storedName = $safeBase.'_'.Str::lower(Str::random(8)).'.'.$ext;
        $dir = 'announcements/'.($this->id ?: 'pending');

        if ($this->attachment_path) {
            Storage::disk('public')->delete($this->attachment_path);
        }

        $this->attachment_path = $file->storeAs($dir, $storedName, 'public');
        $this->attachment_original_name = $originalName;
        $this->attachment_mime = $file->getMimeType() ?: $file->getClientMimeType();
        $this->attachment_size = (int) $file->getSize();
    }

    public static function sanitizeAttachmentFilename(string $name): string
    {
        $name = str_replace(["\0", '\\'], '', $name);
        $name = basename(str_replace(['/', '\\'], '-', $name));
        $name = preg_replace('/[^\w.\- ()\[\]]+/u', '_', $name) ?: 'file';
        $name = trim($name, '._ ');

        return Str::limit($name !== '' ? $name : 'file', 180, '');
    }

    public static function attachmentValidationRules(): array
    {
        return [
            'nullable',
            'file',
            'max:'.self::ATTACHMENT_MAX_KB,
            'mimes:'.implode(',', self::ATTACHMENT_MIMES),
        ];
    }

    public static function attachmentValidationMessages(): array
    {
        return [
            'attachment.max'   => 'The attachment must not be larger than 10 MB.',
            'attachment.mimes' => 'The attachment must be an image (jpg, jpeg, png, gif, webp) or document (pdf, doc, docx, xls, xlsx).',
        ];
    }

    /** Public API shape with host-aware attachment URL. */
    public function toClientArray(): array
    {
        $this->loadMissing('author');

        $attachment = null;
        if ($this->hasAttachment()) {
            $attachment = [
                'url'      => $this->resolvePublicUrl($this->attachment_path),
                'filename' => $this->attachment_original_name,
                'mime'     => $this->attachment_mime,
                'size'     => $this->attachment_size,
                'is_image' => $this->isImageAttachment(),
            ];
        }

        return [
            'id'          => $this->id,
            'created_by'  => $this->created_by,
            'title'       => $this->title,
            'content'     => $this->content,
            'target_role' => $this->target_role,
            'category'    => $this->category ?: 'general',
            'is_pinned'   => (bool) $this->is_pinned,
            'expires_at'  => $this->expires_at,
            'attachment'  => $attachment,
            'author'      => $this->author ? [
                'id'       => $this->author->id,
                'username' => $this->author->username,
                'name'     => $this->author->profile_name ?: $this->author->username,
            ] : null,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
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
