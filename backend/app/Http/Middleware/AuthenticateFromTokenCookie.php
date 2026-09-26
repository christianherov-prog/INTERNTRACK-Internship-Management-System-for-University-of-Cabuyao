<?php

namespace App\Http\Middleware;

use App\Support\AuthCookie;
use Closure;
use Illuminate\Http\Request;

/**
 * Lets the SPA authenticate with the HttpOnly session cookie instead of a token
 * kept in JavaScript storage. The cookie is copied into the Authorization header
 * only when the request carries no bearer token and was sent by the SPA
 * (X-Requested-With: XMLHttpRequest). Sanctum then validates the token as usual,
 * so revoked or expired tokens and deactivated or locked accounts are rejected.
 */
class AuthenticateFromTokenCookie
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $request->bearerToken()
            && $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            $token = $request->cookies->get(AuthCookie::name());
            if (is_string($token) && $token !== '' && strlen($token) <= 255) {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }
}
