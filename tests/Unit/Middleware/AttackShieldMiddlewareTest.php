<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\AttackShieldMiddleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AttackShieldMiddlewareTest extends TestCase
{
    protected AttackShieldMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new AttackShieldMiddleware();
    }

    public function test_blocks_known_malicious_user_agent_scanners(): void
    {
        $scanners = ['sqlmap/1.5', 'Nikto/2.1.6', 'dirbuster', 'gobuster/3.1'];

        foreach ($scanners as $scanner) {
            $request = Request::create('/api/v1/businesses', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => $scanner,
            ]);

            $response = $this->middleware->handle($request, function () {
                return new Response('OK');
            });

            $this->assertEquals(403, $response->getStatusCode(), "Scanner {$scanner} was not blocked.");
            $data = json_decode($response->getContent(), true);
            $this->assertEquals('FORBIDDEN', $data['error']);
        }
    }

    public function test_blocks_reconnaissance_path_probes(): void
    {
        $probes = ['.env', '.git/HEAD', 'wp-admin/index.php', 'phpmyadmin', 'config.json'];

        foreach ($probes as $probe) {
            $request = Request::create('/' . $probe, 'GET');

            $response = $this->middleware->handle($request, function () {
                return new Response('OK');
            });

            $this->assertEquals(404, $response->getStatusCode(), "Probe /{$probe} was not blocked.");
            $data = json_decode($response->getContent(), true);
            $this->assertEquals('NOT_FOUND', $data['error']);
        }
    }

    public function test_blocks_path_traversal_attempts(): void
    {
        // Traversal in path
        $requestPath = Request::create('/api/v1/../../etc/passwd', 'GET');
        $responsePath = $this->middleware->handle($requestPath, function () {
            return new Response('OK');
        });
        $this->assertEquals(400, $responsePath->getStatusCode());

        // Traversal in query
        $requestQuery = Request::create('/api/v1/files?file=../../secret', 'GET');
        $responseQuery = $this->middleware->handle($requestQuery, function () {
            return new Response('OK');
        });
        $this->assertEquals(400, $responseQuery->getStatusCode());
    }

    public function test_allows_benign_requests(): void
    {
        $request = Request::create('/api/v1/businesses', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
        ]);

        $response = $this->middleware->handle($request, function () {
            return new Response('Pass');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Pass', $response->getContent());
    }
}
