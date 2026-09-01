<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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



        // Allow Scramble documentation in production
        Gate::define('viewApiDocs', function ($user = null) {
            return true;
        });

        /*
        |--------------------------------------------------------------------------
        | API Documentation
        |--------------------------------------------------------------------------
        */

        Scramble::configure()
        ->expose(
            ui: '/docs/business',
            document: '/docs/business.json',
        );

        /*
        |--------------------------------------------------------------------------
        | Global API Rate Limiter
        |--------------------------------------------------------------------------
        */

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

        RateLimiter::for('auth', function (Request $request) {
            $machineId = strtolower(
                (string) ($request->input('machine_id') ?? '')
            );

            $key = $machineId !== ''
                ? "auth:{$machineId}|{$request->ip()}"
                : $request->ip();

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

        RateLimiter::for('cashier_pin', function (Request $request) {
            $session = $request->route('cashierSession');

            $sessionId = is_object($session)
                ? $session->id
                : (string) ($session ?? '');

            $key = $sessionId !== ''
                ? "cashier_pin:{$sessionId}|{$request->ip()}"
                : "cashier_pin:{$request->ip()}";

            return Limit::perMinute(5)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'error' => 'TOO_MANY_PIN_ATTEMPTS',
                        'message' => 'Too many PIN verification attempts. Please wait before trying again.',
                        'retry_after_seconds' => (int) ($headers['Retry-After'] ?? 60),
                    ], 429, $headers);
                });
        });
    }
}