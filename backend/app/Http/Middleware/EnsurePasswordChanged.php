<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePasswordChanged
{
    /**
     * Paths allowed while must_change_password is true (method => path patterns).
     * Paths are normalized without a leading slash.
     */
    private const ALLOWED = [
        'GET' => [
            'api/v1/auth/user',
            'api/v1/auth/notification-preferences',
        ],
        'POST' => [
            'api/v1/auth/change-password',
            'api/v1/auth/request-password-change',
            'api/v1/auth/confirm-password-change',
            'api/v1/auth/logout',
            'api/v1/auth/avatar',
        ],
        'PUT' => [
            'api/v1/auth/notification-preferences',
        ],
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (!$user || !$user->must_change_password) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        $method = strtoupper($request->method());
        $allowed = self::ALLOWED[$method] ?? [];

        if (in_array($path, $allowed, true)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Password change required before continuing.',
        ], 403);
    }
}
