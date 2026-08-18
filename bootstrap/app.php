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
        $middleware->trustProxies(at: '*');
        $middleware->append(\App\Http\Middleware\AttackShieldMiddleware::class);
        $middleware->append(\App\Http\Middleware\SanitizeInputMiddleware::class);
        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);

        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\JwtAuthMiddleware::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'business.member' => \App\Http\Middleware\EnsureBusinessMember::class,
            'business.owner' => \App\Http\Middleware\EnsureBusinessOwner::class,
            'outlet.access' => \App\Http\Middleware\EnsureOutletAccess::class,
            'register.access' => \App\Http\Middleware\EnsureRegisterAccess::class,
            'pos_device.access' => \App\Http\Middleware\EnsurePosDeviceAccess::class,
            'cashier_session.active' => \App\Http\Middleware\EnsureCashierSessionActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (\Illuminate\Database\QueryException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'DATABASE_ERROR',
                    'message' => 'An unexpected database error occurred. Details have been logged.',
                ], 500);
            }
        });

        $exceptions->render(function (\PDOException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'DATABASE_CONNECTION_ERROR',
                    'message' => 'Database service is currently unreachable.',
                ], 500);
            }
        });
    })->create();
