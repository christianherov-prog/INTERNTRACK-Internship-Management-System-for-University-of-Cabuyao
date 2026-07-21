<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by(strtolower((string) $request->input('username')).'|'.$request->ip())
                ->response(function (Request $request, array $headers) {
                    $retry = (int) ($headers['Retry-After'][0] ?? $headers['retry-after'][0] ?? 60);

                    return response()->json([
                        'message' => "Too many login attempts. Please try again in {$retry} seconds.",
                    ], 429, $headers);
                });
        });

        RateLimiter::for('messages', function (Request $request) {
            return Limit::perMinute(30)
                ->by((string) ($request->user()?->id ?: $request->ip()))
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many messages sent. Please wait a moment before sending again.',
                    ], 429);
                });
        });
    }
}
