<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. API Documentation Exposure
        Scramble::configure()
            ->expose(
                ui: '/docs/business',
                document: '/docs/business.json',
            );

        // 2. Global API Rate Limiter (60 requests/min per User/IP)
        \Illuminate\Support\Facades\RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)
                ->by($request->header('X-User-Uuid') ?: $request->ip())
                ->response(function (\Illuminate\Http\Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'error' => 'TOO_MANY_REQUESTS',
                        'message' => 'Too many requests. Please slow down and try again later.',
                        'retry_after_seconds' => (int) ($headers['Retry-After'] ?? 60),
                    ], 429, $headers);
                });
        });

        // 3. Strict Auth Rate Limiter (5 attempts/min per IP)
        \Illuminate\Support\Facades\RateLimiter::for('auth', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)
                ->by($request->ip())
                ->response(function (\Illuminate\Http\Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'error' => 'TOO_MANY_ATTEMPTS',
                        'message' => 'Too many authentication attempts. Please try again later.',
                        'retry_after_seconds' => (int) ($headers['Retry-After'] ?? 60),
                    ], 429, $headers);
                });
        });

        // 4. Mutations Rate Limiter for write operations (30 writes/min)
        \Illuminate\Support\Facades\RateLimiter::for('mutations', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(30)
                ->by($request->header('X-User-Uuid') ?: $request->ip())
                ->response(function (\Illuminate\Http\Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'error' => 'TOO_MANY_REQUESTS',
                        'message' => 'Too many modification requests. Please throttle your client.',
                        'retry_after_seconds' => (int) ($headers['Retry-After'] ?? 60),
                    ], 429, $headers);
                });
        });
    }
}
