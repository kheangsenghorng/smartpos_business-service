<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\SanitizeInputMiddleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SanitizeInputMiddlewareTest extends TestCase
{
    protected SanitizeInputMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SanitizeInputMiddleware();
    }

    public function test_rejects_payload_exceeding_max_allowed_size(): void
    {
        $largeContent = str_repeat('A', 3 * 1024 * 1024); // 3MB

        $request = Request::create(
            '/api/v1/test',
            'POST',
            [],
            [],
            [],
            ['HTTP_CONTENT_LENGTH' => (string) strlen($largeContent)],
            $largeContent
        );

        $response = $this->middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertEquals(413, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('PAYLOAD_TOO_LARGE', $data['error']);
    }

    public function test_strips_null_bytes_from_inputs(): void
    {
        $request = Request::create('/api/v1/test', 'POST', [
            'name' => "Shop\0Name",
            'nested' => [
                'field' => "Value\0With\0Null",
            ],
            'number' => 123,
        ]);

        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertEquals('ShopName', $req->input('name'));
            $this->assertEquals('ValueWithNull', $req->input('nested.field'));
            $this->assertEquals(123, $req->input('number'));

            return new Response('Cleaned');
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_passes_valid_clean_inputs_through(): void
    {
        $request = Request::create('/api/v1/test', 'POST', [
            'name' => 'Normal POS Outlet',
            'email' => 'pos@domain.test',
        ]);

        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;
            $this->assertEquals('Normal POS Outlet', $req->input('name'));
            $this->assertEquals('pos@domain.test', $req->input('email'));

            return new Response('OK');
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals('OK', $response->getContent());
    }
}
