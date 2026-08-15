<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Models\BusinessUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $userUuid = $request->attributes->get('user_uuid');

        if (! $userUuid) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $businessParam = $request->route('business');

        if ($businessParam instanceof Business) {
            $business = $businessParam;
        } elseif (is_string($businessParam)) {
            $business = Business::where('uuid', $businessParam)->first();
        } else {
            return $next($request);
        }

        if (! $business) {
            return response()->json(['message' => 'Business not found.'], 404);
        }

        $membership = BusinessUser::where('business_id', $business->id)
            ->where('user_uuid', $userUuid)
            ->where('status', 'active')
            ->where('is_owner', true)
            ->first();

        if (! $membership) {
            return response()->json(['message' => 'Forbidden. Owner privileges required.'], 403);
        }

        return $next($request);
    }
}
