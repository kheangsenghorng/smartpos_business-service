<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $permissions = $request->attributes->get('jwt_permissions', []);
        $roles = $request->attributes->get('jwt_roles', []);

        // Superadmin bypass if role includes super_admin or admin
        if (in_array('super_admin', $roles) || in_array('admin', $roles)) {
            return $next($request);
        }

        if (! in_array($permission, $permissions)) {
            return response()->json([
                'message' => 'Forbidden. Required permission: ' . $permission,
            ], 403);
        }

        return $next($request);
    }
}
