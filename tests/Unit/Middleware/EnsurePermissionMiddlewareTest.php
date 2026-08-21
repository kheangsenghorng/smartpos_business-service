<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsurePermission;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EnsurePermissionMiddlewareTest extends TestCase
{
    protected EnsurePermission $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsurePermission();
    }

    public function test_allows_request_when_permission_matches(): void
    {
        $request = Request::create('/api/v1/businesses', 'POST');
        $request->attributes->set('jwt_permissions', ['businesses.create', 'businesses.read']);
        $request->attributes->set('jwt_roles', ['user']);

        $response = $this->middleware->handle($request, function () {
            return new Response('Allowed');
        }, 'businesses.create');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Allowed', $response->getContent());
    }

    public function test_allows_superadmin_or_admin_role_bypass(): void
    {
        $request = Request::create('/api/v1/businesses', 'POST');
        $request->attributes->set('jwt_permissions', []); // No specific permissions
        $request->attributes->set('jwt_roles', ['super_admin']);

        $response = $this->middleware->handle($request, function () {
            return new Response('Bypassed');
        }, 'businesses.create');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Bypassed', $response->getContent());
    }

    public function test_denies_request_when_permission_missing(): void
    {
        $request = Request::create('/api/v1/businesses', 'POST');
        $request->attributes->set('jwt_permissions', ['businesses.read']);
        $request->attributes->set('jwt_roles', ['user']);

        $response = $this->middleware->handle($request, function () {
            return new Response('Allowed');
        }, 'businesses.delete');

        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('businesses.delete', $data['message']);
    }
}
