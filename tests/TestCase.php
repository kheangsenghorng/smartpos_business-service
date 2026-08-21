<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\RateLimiter::for('api', function () {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10000);
        });
    }
    protected function createJwtToken(?string $userUuid = null, array $permissions = [], array $roles = ['user']): string
    {
        $userUuid = $userUuid ?? (string) Str::uuid();

        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $payload = [
            'iss' => 'smartpos-auth-service',
            'aud' => 'smartpos-api',
            'sub' => 1,
            'user_uuid' => $userUuid,
            'sid' => (string) Str::uuid(),
            'roles' => $roles,
            'permissions' => $permissions,
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $headerB64 = $this->base64UrlEncode(json_encode($header));
        $payloadB64 = $this->base64UrlEncode(json_encode($payload));

        $secret = config('jwt.secret', 'test-jwt-secret-key-for-phpunit-testing-only-1234567890');
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "$headerB64.$payloadB64", $secret, true)
        );

        return "$headerB64.$payloadB64.$signature";
    }

    protected function withJwtAuth(?string $userUuid = null, array $permissions = [], array $roles = ['user']): static
    {
        $token = $this->createJwtToken($userUuid, $permissions, $roles);

        return $this->withHeader('Authorization', "Bearer $token");
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
