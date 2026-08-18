<?php

namespace App\Http\Middleware;

use App\Models\CashierSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCashierSessionActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sessionParam = $request->route('cashierSession') ?? $request->header('X-Cashier-Session-Id');

        if ($sessionParam instanceof CashierSession) {
            $session = $sessionParam;
        } elseif (is_string($sessionParam)) {
            $session = CashierSession::where('uuid', $sessionParam)->first();
        } else {
            return $next($request);
        }

        if (! $session) {
            return response()->json(['message' => 'Cashier session not found.'], 404);
        }

        if ($session->status === 'locked') {
            return response()->json([
                'message' => 'Cashier session is locked. Please unlock first.',
                'status' => 'locked',
            ], 423); // 423 Locked
        }

        if ($session->status !== 'active') {
            return response()->json([
                'message' => 'Cashier session is not active. Current status: '.$session->status,
                'status' => $session->status,
            ], 403);
        }

        return $next($request);
    }
}
