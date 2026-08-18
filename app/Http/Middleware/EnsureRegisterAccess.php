<?php

namespace App\Http\Middleware;

use App\Models\BusinessUser;
use App\Models\Register;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegisterAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $userUuid = $request->attributes->get('user_uuid');

        if (! $userUuid) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $registerParam = $request->route('register');

        if ($registerParam instanceof Register) {
            $register = $registerParam;
        } elseif (is_string($registerParam)) {
            $register = Register::where('uuid', $registerParam)->first();
        } else {
            return $next($request);
        }

        if (! $register) {
            return response()->json(['message' => 'Register not found.'], 404);
        }

        $roles = $request->attributes->get('jwt_roles', []);
        if (in_array('admin', $roles, true)) {
            return $next($request);
        }

        $membership = BusinessUser::where('business_id', $register->business_id)
            ->where('user_uuid', $userUuid)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            return response()->json(['message' => 'Forbidden. You do not have access to this register.'], 403);
        }

        // Owners have access to all registers in their business
        if ($membership->is_owner) {
            return $next($request);
        }

        // Non-owners: enforce specific outlet assignments if configured
        $hasSpecificAssignments = $membership->businessUserOutlets()->where('is_active', true)->exists();
        if ($hasSpecificAssignments) {
            $isAssignedToThisOutlet = $membership->businessUserOutlets()
                ->where('outlet_id', $register->outlet_id)
                ->where('is_active', true)
                ->exists();

            if (! $isAssignedToThisOutlet) {
                return response()->json(['message' => 'Forbidden. You are not assigned to this register\'s outlet.'], 403);
            }
        } elseif ($membership->outlet_id !== null && $membership->outlet_id !== $register->outlet_id) {
            return response()->json(['message' => 'Forbidden. You are not assigned to this register\'s outlet.'], 403);
        }

        return $next($request);
    }
}
