<?php

namespace App\Http\Middleware;

use App\Models\BusinessUser;
use App\Models\PosDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePosDeviceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $userUuid = $request->attributes->get('user_uuid');

        if (! $userUuid) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $deviceParam = $request->route('posDevice');

        if ($deviceParam instanceof PosDevice) {
            $posDevice = $deviceParam;
        } elseif (is_string($deviceParam)) {
            $posDevice = PosDevice::where('uuid', $deviceParam)->first();
        } else {
            return $next($request);
        }

        if (! $posDevice) {
            return response()->json(['message' => 'POS device not found.'], 404);
        }

        $roles = $request->attributes->get('jwt_roles', []);
        if (in_array('admin', $roles, true)) {
            return $next($request);
        }

        $membership = BusinessUser::where('business_id', $posDevice->business_id)
            ->where('user_uuid', $userUuid)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            return response()->json(['message' => 'Forbidden. You do not have access to this POS device.'], 403);
        }

        // Owners have access to all POS devices in their business
        if ($membership->is_owner) {
            return $next($request);
        }

        // Non-owners: enforce specific outlet assignments if configured
        if ($posDevice->outlet_id) {
            $hasSpecificAssignments = $membership->businessUserOutlets()->where('is_active', true)->exists();
            if ($hasSpecificAssignments) {
                $isAssignedToThisOutlet = $membership->businessUserOutlets()
                    ->where('outlet_id', $posDevice->outlet_id)
                    ->where('is_active', true)
                    ->exists();

                if (! $isAssignedToThisOutlet) {
                    return response()->json(['message' => 'Forbidden. You are not assigned to this POS device\'s outlet.'], 403);
                }
            } elseif ($membership->outlet_id !== null && $membership->outlet_id !== $posDevice->outlet_id) {
                return response()->json(['message' => 'Forbidden. You are not assigned to this POS device\'s outlet.'], 403);
            }
        }

        return $next($request);
    }
}
