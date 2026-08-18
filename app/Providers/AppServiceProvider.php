<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->header('X-User-Uuid') ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'error' => 'TOO_MANY_REQUESTS',
                        'message' => 'Too many requests. Please slow down and try again later.',
                        'retry_after_seconds' => (int) ($headers['Retry-After'] ?? 60),
                    ], 429, $headers);
                });
        });

        // 3. Strict Auth Rate Limiter (5 attempts/min per Machine ID & IP)
        RateLimiter::for('auth', function (Request $request) {
            $machineId = strtolower((string) ($request->input('machine_id') ?? ''));
            $key = $machineId !== '' ? "auth:{$machineId}|{$request->ip()}" : $request->ip();

            return Limit::perMinute(5)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'error' => 'TOO_MANY_ATTEMPTS',
                        'message' => 'Too many authentication attempts. Please try again later.',
                        'retry_after_seconds' => (int) ($headers['Retry-After'] ?? 60),
                    ], 429, $headers);
                });
        });

        // 4. Mutations Rate Limiter for write operations (30 writes/min)
        RateLimiter::for('mutations', function (Request $request) {
            return Limit::perMinute(30)
                ->by($request->header('X-User-Uuid') ?: $request->ip())
                ->response(function (Request $request, array $headers) {
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
