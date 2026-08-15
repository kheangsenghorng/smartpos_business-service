<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Models\BusinessUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessMember
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

        if (! $business || $business->status !== 'active') {
            return response()->json(['message' => 'Business is inactive or not found.'], 403);
        }

        $membership = BusinessUser::where('business_id', $business->id)
            ->where('user_uuid', $userUuid)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            return response()->json(['message' => 'Forbidden. You are not an active member of this business.'], 403);
        }

        $request->attributes->set('business_membership', $membership);

        return $next($request);
    }
}
