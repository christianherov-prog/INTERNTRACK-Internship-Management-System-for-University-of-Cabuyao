<?php

namespace App\Support;

/**
 * Canonical public URL for users.avatar_path.
 *
 * Avatars are served by PublicAvatarController so the UI does not depend on
 * a working public/storage symlink (often broken on OneDrive / Windows).
 */
final class AvatarUrl
{
    public static function fromPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        $filename = basename($normalized);

        if ($filename === '' || $filename === '.' || $filename === '..') {
            return null;
        }

        $base = rtrim(request()->getSchemeAndHttpHost(), '/');

        return $base.'/api/v1/media/avatars/'.$filename;
    }
}
