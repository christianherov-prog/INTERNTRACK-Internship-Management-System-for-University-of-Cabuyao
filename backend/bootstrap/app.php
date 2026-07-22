<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated. Please login.'], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
            }
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
        });

        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'The attachment must not be larger than 10 MB.',
                ], 413);
            }
        });

        // Context-aware 429 JSON for API (named limiters supply their own copy when possible).
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            $retry = (int) ($e->getHeaders()['Retry-After'] ?? 60);

            if ($request->is('api/v1/auth/login')) {
                return response()->json([
                    'message' => "Too many login attempts. Please try again in {$retry} seconds.",
                ], 429, $e->getHeaders());
            }

            if ($request->is('api/v1/messages')) {
                return response()->json([
                    'message' => 'Too many messages sent. Please wait a moment before sending again.',
                ], 429, $e->getHeaders());
            }

            return response()->json([
                'message' => "Too many requests. Please try again in {$retry} seconds.",
            ], 429, $e->getHeaders());
        });
    })->create();
