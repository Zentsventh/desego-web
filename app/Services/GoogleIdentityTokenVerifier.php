<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleIdentityTokenVerifier
{
    /** @return array<string, mixed> */
    public function verify(string $token, string $nonce): array
    {
        $parts = explode('.', $token);
        abort_unless(count($parts) === 3, 401, 'La credencial de Google no es válida.');
        $header = json_decode($this->decode($parts[0]), true);
        $claims = json_decode($this->decode($parts[1]), true);
        abort_unless(is_array($header) && is_array($claims) && ($header['alg'] ?? null) === 'RS256'
            && is_string($header['kid'] ?? null), 401, 'La credencial de Google no es válida.');

        $certificates = Cache::remember('google_identity_certificates', 3600, fn (): array => $this->certificates());
        if (! isset($certificates[$header['kid']])) {
            $certificates = $this->certificates();
            Cache::put('google_identity_certificates', $certificates, 3600);
        }
        $certificate = $certificates[$header['kid']] ?? null;
        abort_unless(is_string($certificate) && openssl_verify($parts[0].'.'.$parts[1], $this->decode($parts[2]), $certificate, OPENSSL_ALGO_SHA256) === 1,
            401, 'No se pudo verificar la firma de Google.');
        $clientId = (string) config('services.google.client_id');
        abort_unless($clientId !== '' && ($claims['aud'] ?? null) === $clientId
            && in_array($claims['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            && is_int($claims['exp'] ?? null) && $claims['exp'] > now()->timestamp
            && is_int($claims['iat'] ?? null) && $claims['iat'] <= now()->timestamp + 60
            && is_string($claims['nonce'] ?? null) && hash_equals($nonce, $claims['nonce'])
            && ($claims['email_verified'] ?? false) === true
            && is_string($claims['email'] ?? null) && filter_var($claims['email'], FILTER_VALIDATE_EMAIL)
            && is_string($claims['sub'] ?? null) && $claims['sub'] !== '',
            401, 'La credencial de Google venció o no pertenece a esta aplicación.');

        return $claims;
    }

    /** @return array<string, string> */
    private function certificates(): array
    {
        $response = Http::connectTimeout(5)->timeout(10)->get('https://www.googleapis.com/oauth2/v1/certs');
        abort_unless($response->successful() && is_array($response->json()), 503, 'Google no está disponible en este momento.');

        return $response->json();
    }

    private function decode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        abort_unless($decoded !== false, 401, 'La credencial de Google no es válida.');

        return $decoded;
    }
}
