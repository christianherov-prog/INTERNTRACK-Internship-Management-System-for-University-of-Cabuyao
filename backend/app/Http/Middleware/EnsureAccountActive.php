<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Revalidates account status on every authenticated request.
 *
 * A token issued before an account was deactivated or locked must not keep
 * working, so the current token is revoked and the request is rejected with
 * 401. The SPA then clears its session in every open tab.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if ($user && (! $user->is_active || $user->locked_at !== null)) {
            $token = $user->currentAccessToken();
            if ($token && method_exists($token, 'delete')) {
                $token->delete();
            }

            return response()->json([
                'message' => $user->locked_at !== null
                    ? 'This account is locked. Contact the University MISD office or your InternTrack administrator.'
                    : 'This account is inactive. Please contact your coordinator.',
            ], 401);
        }

        return $next($request);
    }
}
