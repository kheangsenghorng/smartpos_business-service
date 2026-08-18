<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\Register;
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

        $business = $this->resolveBusiness($request);

        if (! $business) {
            return response()->json(['message' => 'Business not found.'], 404);
        }

        $roles = $request->attributes->get('jwt_roles', []);
        if (in_array('admin', $roles, true)) {
            return $next($request);
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

    private function resolveBusiness(Request $request): ?Business
    {
        $businessParam = $request->route('business');
        if ($businessParam instanceof Business) {
            return $businessParam;
        }
        if (is_string($businessParam)) {
            return Business::where('uuid', $businessParam)->first();
        }

        $outletParam = $request->route('outlet');
        if ($outletParam instanceof Outlet) {
            return $outletParam->business;
        }
        if (is_string($outletParam)) {
            $outlet = Outlet::where('uuid', $outletParam)->with('business')->first();
            return $outlet?->business;
        }

        $registerParam = $request->route('register');
        if ($registerParam instanceof Register) {
            return $registerParam->business;
        }
        if (is_string($registerParam)) {
            $register = Register::where('uuid', $registerParam)->with('business')->first();
            return $register?->business;
        }

        $posDeviceParam = $request->route('posDevice');
        if ($posDeviceParam instanceof PosDevice) {
            return $posDeviceParam->business;
        }
        if (is_string($posDeviceParam)) {
            $posDevice = PosDevice::where('uuid', $posDeviceParam)->with('business')->first();
            return $posDevice?->business;
        }

        return null;
    }
}
