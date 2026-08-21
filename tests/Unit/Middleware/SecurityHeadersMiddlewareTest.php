<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SecurityHeadersMiddlewareTest extends TestCase
{
    protected SecurityHeadersMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SecurityHeadersMiddleware();
    }

    public function test_attaches_security_headers_to_api_response(): void
    {
        $request = Request::create('/api/v1/businesses', 'GET');

        $response = new Response('{"data": []}');
        $response->headers->set('X-Powered-By', 'PHP/8.2.0');

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $this->assertFalse($result->headers->has('X-Powered-By'));
        $this->assertEquals('nosniff', $result->headers->get('X-Content-Type-Options'));
        $this->assertEquals('DENY', $result->headers->get('X-Frame-Options'));
        $this->assertEquals('1; mode=block', $result->headers->get('X-XSS-Protection'));
        $this->assertEquals('strict-origin-when-cross-origin', $result->headers->get('Referrer-Policy'));
        $this->assertEquals("default-src 'none'; frame-ancestors 'none';", $result->headers->get('Content-Security-Policy'));
        $this->assertEquals('same-origin', $result->headers->get('Cross-Origin-Resource-Policy'));
        $this->assertStringContainsString('max-age=3153600', $result->headers->get('Strict-Transport-Security'));
        $this->assertStringContainsString('camera=()', $result->headers->get('Permissions-Policy'));
    }

    public function test_attaches_docs_csp_for_documentation_endpoints(): void
    {
        $request = Request::create('/docs/api', 'GET');

        $response = new Response('<h1>API Docs</h1>');

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $csp = $result->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('https://unpkg.com', $csp);
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $csp);
    }
}
