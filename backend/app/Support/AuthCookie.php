<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * HttpOnly cookie that carries the Sanctum personal access token for the SPA.
 *
 * Cookies are shared by every tab of the same browser, so a new tab keeps the
 * signed-in session without JavaScript ever reading the token. SameSite=Strict
 * keeps other sites from sending it, and the cookie is only honored together
 * with an X-Requested-With header (see AuthenticateFromTokenCookie), which
 * forces a CORS preflight limited to the allowed InternTrack origins.
 */
final class AuthCookie
{
    public static function name(): string
    {
        return (string) config('interntrack.auth_cookie', 'interntrack_token');
    }

    public static function minutes(): int
    {
        $minutes = (int) config('sanctum.expiration', 1440);

        return $minutes > 0 ? $minutes : 1440;
    }

    public static function make(string $plainTextToken, Request $request): Cookie
    {
        return cookie(
            self::name(),
            $plainTextToken,
            self::minutes(),
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'strict'
        );
    }

    public static function forget(Request $request): Cookie
    {
        return cookie(self::name(), '', -2628000, '/', null, $request->isSecure(), true, false, 'strict');
    }
}
