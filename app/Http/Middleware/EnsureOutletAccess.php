<?php

namespace App\Http\Middleware;

use App\Models\BusinessUser;
use App\Models\Outlet;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOutletAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $userUuid = $request->attributes->get('user_uuid');

        if (! $userUuid) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $outletParam = $request->route('outlet');

        if ($outletParam instanceof Outlet) {
            $outlet = $outletParam;
        } elseif (is_string($outletParam)) {
            $outlet = Outlet::where('uuid', $outletParam)->first();
        } else {
            return $next($request);
        }

        if (! $outlet) {
            return response()->json(['message' => 'Outlet not found.'], 404);
        }

        $membership = BusinessUser::where('business_id', $outlet->business_id)
            ->where('user_uuid', $userUuid)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            return response()->json(['message' => 'Forbidden. You do not have access to this outlet.'], 403);
        }

        return $next($request);
    }
}
