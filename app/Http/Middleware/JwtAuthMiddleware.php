<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');

        if (! $header || ! str_starts_with($header, 'Bearer ')) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $token = substr($header, 7);
        $payload = $this->verifyToken($token);

        if (! $payload) {
            return response()->json([
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        // Attach decoded claims to request attributes
        $request->attributes->set('jwt_payload', $payload);
        $request->attributes->set('user_uuid', $payload['user_uuid'] ?? $payload['sub'] ?? null);
        $request->attributes->set('jwt_permissions', $payload['permissions'] ?? []);
        $request->attributes->set('jwt_roles', $payload['roles'] ?? []);

        return $next($request);
    }

    private function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;

        $secret = env('JWT_SECRET', config('jwt.secret'));

        if (! $secret) {
            return null;
        }

        // Verify signature
        $expectedSig = $this->base64UrlEncode(
            hash_hmac('sha256', "$headerB64.$payloadB64", $secret, true)
        );

        if (! hash_equals($expectedSig, $sigB64)) {
            return null;
        }

        // Decode payload
        $payloadJson = $this->base64UrlDecode($payloadB64);
        $payload = json_decode($payloadJson, true);

        if (! is_array($payload)) {
            return null;
        }

        // Check expiration
        if (isset($payload['exp']) && time() >= $payload['exp']) {
            return null;
        }

        // Verify issuer
        $expectedIssuer = config('jwt.issuer');
        $verifyIssuer = config('jwt.verify_issuer', false);
        if (($verifyIssuer || isset($payload['iss'])) && $expectedIssuer) {
            if (! isset($payload['iss']) || $payload['iss'] !== $expectedIssuer) {
                return null;
            }
        }

        // Verify audience
        $expectedAudience = config('jwt.audience');
        $verifyAudience = config('jwt.verify_audience', false);
        if (($verifyAudience || isset($payload['aud'])) && $expectedAudience) {
            if (! isset($payload['aud']) || $payload['aud'] !== $expectedAudience) {
                return null;
            }
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
