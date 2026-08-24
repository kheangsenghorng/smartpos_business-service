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

        // Decode and validate header
        $headerJson = $this->base64UrlDecode($headerB64);
        $header = json_decode($headerJson, true);

        if (! is_array($header) || empty($header['alg'])) {
            return null;
        }

        $tokenAlgo = strtoupper((string) $header['alg']);
        if ($tokenAlgo === 'NONE') {
            return null;
        }

        $publicKey = config('jwt.public_key');
        if (is_string($publicKey) && str_starts_with($publicKey, 'file://')) {
            $keyPath = substr($publicKey, 7);
            if (! str_starts_with($keyPath, '/') && function_exists('base_path')) {
                $keyPath = base_path($keyPath);
            }
            $publicKey = file_exists($keyPath) ? file_get_contents($keyPath) : null;
        }

        $secret = config('jwt.secret');

        $isVerified = false;

        // Asymmetric Verification (RS256, RS384, RS512)
        if (in_array($tokenAlgo, ['RS256', 'RS384', 'RS512'], true) && ! empty($publicKey)) {
            $algoMap = [
                'RS256' => OPENSSL_ALGO_SHA256,
                'RS384' => OPENSSL_ALGO_SHA384,
                'RS512' => OPENSSL_ALGO_SHA512,
            ];
            $openSslAlgo = $algoMap[$tokenAlgo] ?? OPENSSL_ALGO_SHA256;
            $rawSig = $this->base64UrlDecodeBinary($sigB64);
            if ($rawSig !== false && $rawSig !== null) {
                $isVerified = openssl_verify("$headerB64.$payloadB64", $rawSig, $publicKey, $openSslAlgo) === 1;
            }
        } elseif ($tokenAlgo === 'HS256' && ! empty($secret)) {
            // Symmetric HMAC Verification
            $expectedSig = $this->base64UrlEncode(
                hash_hmac('sha256', "$headerB64.$payloadB64", $secret, true)
            );
            $isVerified = hash_equals($expectedSig, $sigB64);
        }

        if (! $isVerified) {
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
        $verifyIssuer = config('jwt.verify_issuer', false);
        if ($verifyIssuer || isset($payload['iss'])) {
            if (! isset($payload['iss']) || ! $this->isIssuerValid($payload['iss'])) {
                return null;
            }
        }

        // Verify audience
        $expectedAudience = config('jwt.audience');
        $verifyAudience = config('jwt.verify_audience', false);
        if ($verifyAudience && $expectedAudience) {
            if (! isset($payload['aud']) || $payload['aud'] !== $expectedAudience) {
                return null;
            }
        } elseif (isset($payload['aud']) && $expectedAudience) {
            if ($payload['aud'] !== $expectedAudience) {
                return null;
            }
        }

        return $payload;
    }

    private function isIssuerValid(?string $iss): bool
    {
        if (! $iss) {
            return false;
        }

        $expectedIssuer = config('jwt.issuer', 'smartpos-auth-service');
        if ($iss === $expectedIssuer || $iss === 'smartpos-auth-service') {
            return true;
        }

        $identityUrl = config('jwt.identity_service_url');
        $appUrl = config('app.url');

        $allowedPrefixes = array_filter(array_unique([
            $identityUrl ? rtrim((string) $identityUrl, '/') : null,
            $appUrl ? rtrim((string) $appUrl, '/') : null,
            ...(app()->environment('local', 'testing') ? [
                'http://localhost',
                'http://127.0.0.1',
                'http://localhost:8001',
                'http://127.0.0.1:8001',
                'http://api.smartpos.test',
                'http://api.smartpos.test:8001',
            ] : []),
        ]));

        foreach ($allowedPrefixes as $prefix) {
            if (! empty($prefix) && str_starts_with($iss, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }

    private function base64UrlDecodeBinary(string $data): ?string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
