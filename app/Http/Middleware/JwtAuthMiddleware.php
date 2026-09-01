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
    
        /*
        |--------------------------------------------------------------------------
        | Decode JWT Header
        |--------------------------------------------------------------------------
        */
    
        $headerJson = $this->base64UrlDecode($headerB64);
        $header = json_decode($headerJson, true);
    
        if (! is_array($header) || empty($header['alg'])) {
            return null;
        }
    
        $tokenAlgo = strtoupper((string) $header['alg']);
    
        /*
        |--------------------------------------------------------------------------
        | Reject unsafe / unexpected algorithms
        |--------------------------------------------------------------------------
        */
    
        if ($tokenAlgo === 'NONE') {
            return null;
        }
    
        $configuredAlgo = strtoupper((string) config('jwt.algo', 'RS256'));
    
        if ($tokenAlgo !== $configuredAlgo) {
            return null;
        }
    
        $signingInput = $headerB64.'.'.$payloadB64;
        $rawSig = $this->base64UrlDecodeBinary($sigB64);
    
        if ($rawSig === null) {
            return null;
        }
    
        $isVerified = false;
    
        /*
        |--------------------------------------------------------------------------
        | RSA JWT Verification
        |--------------------------------------------------------------------------
        */
    
        if (in_array($tokenAlgo, ['RS256', 'RS384', 'RS512'], true)) {
            $publicKeyConfig = config('jwt.public_key');
    
            if (! is_string($publicKeyConfig) || trim($publicKeyConfig) === '') {
                logger()->error('JWT public key configuration is missing');
    
                return null;
            }
    
            /*
             * Support:
             *
             * /run/secrets/identity-jwt/public.pem
             * file:///run/secrets/identity-jwt/public.pem
             * -----BEGIN PUBLIC KEY----- ...
             */
    
            if (str_starts_with($publicKeyConfig, 'file://')) {
                $keyPath = substr($publicKeyConfig, 7);
    
                if (! str_starts_with($keyPath, '/')) {
                    $keyPath = base_path($keyPath);
                }
    
                if (! is_file($keyPath) || ! is_readable($keyPath)) {
                    logger()->error('JWT public key file is unavailable', [
                        'path' => $keyPath,
                    ]);
    
                    return null;
                }
    
                $publicKeyPem = file_get_contents($keyPath);
    
            } elseif (
                str_contains($publicKeyConfig, '-----BEGIN PUBLIC KEY-----') ||
                str_contains($publicKeyConfig, '-----BEGIN RSA PUBLIC KEY-----')
            ) {
                // Public key directly stored as PEM
                $publicKeyPem = $publicKeyConfig;
    
            } else {
                // Treat configuration as a normal filesystem path
                $keyPath = $publicKeyConfig;
    
                if (! str_starts_with($keyPath, '/')) {
                    $keyPath = base_path($keyPath);
                }
    
                if (! is_file($keyPath) || ! is_readable($keyPath)) {
                    logger()->error('JWT public key file is unavailable', [
                        'path' => $keyPath,
                    ]);
    
                    return null;
                }
    
                $publicKeyPem = file_get_contents($keyPath);
            }
    
            if (! is_string($publicKeyPem) || trim($publicKeyPem) === '') {
                logger()->error('JWT public key file is empty');
    
                return null;
            }
    
            $publicKey = openssl_pkey_get_public($publicKeyPem);
    
            if ($publicKey === false) {
                logger()->error('JWT public key is invalid', [
                    'openssl_error' => openssl_error_string(),
                ]);
    
                return null;
            }
    
            $algoMap = [
                'RS256' => OPENSSL_ALGO_SHA256,
                'RS384' => OPENSSL_ALGO_SHA384,
                'RS512' => OPENSSL_ALGO_SHA512,
            ];
    
            $isVerified = openssl_verify(
                $signingInput,
                $rawSig,
                $publicKey,
                $algoMap[$tokenAlgo]
            ) === 1;
        }
    
        /*
        |--------------------------------------------------------------------------
        | HMAC Verification
        |--------------------------------------------------------------------------
        */
    
        elseif ($tokenAlgo === 'HS256') {
            $secret = config('jwt.secret');
    
            if (! is_string($secret) || $secret === '') {
                return null;
            }
    
            $expectedSig = $this->base64UrlEncode(
                hash_hmac(
                    'sha256',
                    $signingInput,
                    $secret,
                    true
                )
            );
    
            $isVerified = hash_equals($expectedSig, $sigB64);
        }
    
        if (! $isVerified) {
            return null;
        }
    
        /*
        |--------------------------------------------------------------------------
        | Decode JWT Payload
        |--------------------------------------------------------------------------
        */
    
        $payloadJson = $this->base64UrlDecode($payloadB64);
        $payload = json_decode($payloadJson, true);
    
        if (! is_array($payload)) {
            return null;
        }
    
        /*
        |--------------------------------------------------------------------------
        | Expiration
        |--------------------------------------------------------------------------
        */
    
        if (
            isset($payload['exp']) &&
            is_numeric($payload['exp']) &&
            time() >= (int) $payload['exp']
        ) {
            return null;
        }
    
        /*
        |--------------------------------------------------------------------------
        | Not Before
        |--------------------------------------------------------------------------
        */
    
        if (
            isset($payload['nbf']) &&
            is_numeric($payload['nbf']) &&
            time() < (int) $payload['nbf']
        ) {
            return null;
        }
    
        /*
        |--------------------------------------------------------------------------
        | Issuer
        |--------------------------------------------------------------------------
        */
    
        if (config('jwt.verify_issuer', true)) {
            if (
                ! isset($payload['iss']) ||
                ! $this->isIssuerValid((string) $payload['iss'])
            ) {
                return null;
            }
        }
    
        /*
        |--------------------------------------------------------------------------
        | Audience
        |--------------------------------------------------------------------------
        */
    
        $expectedAudience = config('jwt.audience');
    
        if (
            config('jwt.verify_audience', true) &&
            $expectedAudience
        ) {
            if (! isset($payload['aud'])) {
                return null;
            }
    
            $audiences = is_array($payload['aud'])
                ? $payload['aud']
                : [$payload['aud']];
    
            if (! in_array($expectedAudience, $audiences, true)) {
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
        $remainder = strlen($data) % 4;
    
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
    
        $decoded = base64_decode(
            strtr($data, '-_', '+/'),
            true
        );
    
        return $decoded === false ? '' : $decoded;
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
