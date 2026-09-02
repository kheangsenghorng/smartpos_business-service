<?php

use App\Http\Middleware\AttackShieldMiddleware;
use App\Http\Middleware\EnsureBusinessMember;
use App\Http\Middleware\EnsureBusinessOwner;
use App\Http\Middleware\EnsureCashierSessionActive;
use App\Http\Middleware\EnsureOutletAccess;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsurePosDeviceAccess;
use App\Http\Middleware\EnsureRegisterAccess;
use App\Http\Middleware\EnsureWarehouseAccess;
use App\Http\Middleware\EnsureWarehouseLocationAccess;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SanitizeInputMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Database\QueryException;
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
        $middleware->trustProxies(
            at: '*',
            headers:
                Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->append(AttackShieldMiddleware::class);
        $middleware->append(SanitizeInputMiddleware::class);
        $middleware->append(SecurityHeadersMiddleware::class);

        $middleware->alias([
            'jwt.auth' => JwtAuthMiddleware::class,
            'permission' => EnsurePermission::class,
            'business.member' => EnsureBusinessMember::class,
            'business.owner' => EnsureBusinessOwner::class,
            'outlet.access' => EnsureOutletAccess::class,
            'register.access' => EnsureRegisterAccess::class,
            'pos_device.access' => EnsurePosDeviceAccess::class,
            'warehouse.access' => EnsureWarehouseAccess::class,
            'warehouse_location.access' => EnsureWarehouseLocationAccess::class,
            'cashier_session.active' => EnsureCashierSessionActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) =>
                $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(
            function (QueryException $e, Request $request) {
                if ($request->is('api/*') || $request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'DATABASE_ERROR',
                        'message' => 'An unexpected database error occurred. Details have been logged.',
                    ], 500);
                }

                return null;
            }
        );

        $exceptions->render(
            function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request) {
                if ($request->is('api/*') || $request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'RESOURCE_NOT_FOUND',
                        'message' => 'The requested resource was not found.',
                    ], 404);
                }

                return null;
            }
        );

        $exceptions->render(
            function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
                if ($request->is('api/*') || $request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'RESOURCE_NOT_FOUND',
                        'message' => 'The requested resource was not found.',
                    ], 404);
                }

                return null;
            }
        );

        $exceptions->render(
            function (\PDOException $e, Request $request) {
                if ($request->is('api/*') || $request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'DATABASE_CONNECTION_ERROR',
                        'message' => 'Database service is currently unreachable.',
                    ], 500);
                }

                return null;
            }
        );
    })
    ->create();
