<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     * Usage in routes: ->middleware('role:student') or ->middleware('role:coordinator,admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user();

        \Illuminate\Support\Facades\Log::info("Role check:", ["user" => $user ? $user->id : null, "user_role" => $user ? $user->role : null, "required" => $roles]); 
        if (!$user || !$user->hasRole($roles)) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}

