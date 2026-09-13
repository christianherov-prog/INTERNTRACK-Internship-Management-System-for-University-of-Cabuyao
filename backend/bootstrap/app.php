<?php

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureUserHasRole;
use App\Support\UniqueWrite;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'password.changed' => EnsurePasswordChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated. Please login.'], 401);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
        });

        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => \App\Support\UploadLimits::requestTooLargeMessage(),
                ], 413);
            }
        });

        $exceptions->render(function (QueryException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if (UniqueWrite::isDuplicate($e) && str_contains($e->getMessage(), 'faculty_number')) {
                return response()->json([
                    'message' => 'This ID is already assigned to another account.',
                    'errors' => [
                        'faculty_number' => ['This ID is already assigned to another account.'],
                    ],
                ], 422);
            }

            Log::error('API query failed', [
                'message' => $e->getMessage(),
                'sql' => $e->getSql(),
            ]);

            return response()->json([
                'message' => 'Something went wrong. Please try again.',
            ], 500);
        });

        // Context-aware 429 JSON for API (named limiters supply their own copy when possible).
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*')) {
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
