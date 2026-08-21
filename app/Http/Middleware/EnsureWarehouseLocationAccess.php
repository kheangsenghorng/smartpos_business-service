<?php

namespace App\Http\Middleware;

use App\Models\BusinessUser;
use App\Models\WarehouseLocation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWarehouseLocationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $userUuid = $request->attributes->get('user_uuid');

        if (! $userUuid) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $locationParam = $request->route('warehouseLocation')
            ?? $request->route('warehouse_location')
            ?? $request->route('location');

        if ($locationParam instanceof WarehouseLocation) {
            $location = $locationParam;
        } elseif (is_string($locationParam)) {
            $location = WarehouseLocation::where('uuid', $locationParam)->first();
        } else {
            return $next($request);
        }

        if (! $location) {
            return response()->json(['message' => 'Warehouse location not found.'], 404);
        }

        $warehouse = $location->warehouse;
        if (! $warehouse) {
            return response()->json(['message' => 'Warehouse not found.'], 404);
        }

        $roles = $request->attributes->get('jwt_roles', []);
        if (in_array('admin', $roles, true)) {
            return $next($request);
        }

        $membership = BusinessUser::where('business_id', $warehouse->business_id)
            ->where('user_uuid', $userUuid)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            return response()->json(['message' => 'Forbidden. You do not have access to this warehouse location.'], 403);
        }

        // Owners have access to all locations in their business
        if ($membership->is_owner) {
            return $next($request);
        }

        // Non-owners: enforce specific outlet assignments if warehouse is assigned to an outlet
        if ($warehouse->outlet_id) {
            $hasSpecificAssignments = $membership->businessUserOutlets()->where('is_active', true)->exists();
            if ($hasSpecificAssignments) {
                $isAssignedToThisOutlet = $membership->businessUserOutlets()
                    ->where('outlet_id', $warehouse->outlet_id)
                    ->where('is_active', true)
                    ->exists();

                if (! $isAssignedToThisOutlet) {
                    return response()->json(['message' => 'Forbidden. You are not assigned to this warehouse location\'s outlet.'], 403);
                }
            } elseif ($membership->outlet_id !== null && $membership->outlet_id !== $warehouse->outlet_id) {
                return response()->json(['message' => 'Forbidden. You are not assigned to this warehouse location\'s outlet.'], 403);
            }
        }

        return $next($request);
    }
}
