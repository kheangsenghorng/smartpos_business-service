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

        $membership = BusinessUser::where('business_id', $register->business_id)
            ->where('user_uuid', $userUuid)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            return response()->json(['message' => 'Forbidden. You do not have access to this register.'], 403);
        }

        return $next($request);
    }
}
