<?php

namespace App\Services\PropertyFinder;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the Property Finder Enterprise API (https://atlas.propertyfinder.com).
 * Auth: POST /v1/auth/token with apiKey + apiSecret → JWT valid ~30 min, cached here.
 */
class PropertyFinderClient
{
    private const TOKEN_CACHE_KEY = 'propertyfinder.access_token';

    public static function isConfigured(): bool
    {
        return filled(config('propertyfinder.api_key')) && filled(config('propertyfinder.api_secret'));
    }

    /** GET /v1/leads — filters are scalar query params (page, perPage, createdAtFrom, id, …). */
    public function leads(array $query = []): array
    {
        return $this->request('get', '/v1/leads', $query)->json() ?? [];
    }

    /** One lead by PF id, or null when PF doesn't return it. */
    public function getLead(string $pfLeadId): ?array
    {
        $data = $this->leads(['id' => $pfLeadId, 'perPage' => 1])['data'] ?? [];

        return $data[0] ?? null;
    }

    /** One listing by PF listing id (GET /v1/listings?filter[ids]=…). */
    public function getListing(string $listingId): ?array
    {
        $results = $this->request('get', '/v1/listings', ['filter[ids]' => $listingId, 'perPage' => 1])->json('results') ?? [];

        return $results[0] ?? null;
    }

    /** GET /v1/projects/{id} (Primary Plus project). */
    public function getProject(string $projectId): ?array
    {
        return $this->request('get', '/v1/projects/' . rawurlencode($projectId))->json() ?: null;
    }

    /** One location with its parent tree (CITY "Dubai" / "Abu Dhabi", …). */
    public function getLocation(string|int $locationId): ?array
    {
        $data = $this->request('get', '/v1/locations', ['filter[id]' => (string) $locationId, 'perPage' => 1])->json('data') ?? [];

        return $data[0] ?? null;
    }

    public function listWebhooks(): array
    {
        return $this->request('get', '/v1/webhooks')->json() ?? [];
    }

    /** Returns the HTTP response so callers can tell 201 from 409 (already subscribed). */
    public function subscribeWebhook(string $eventId, string $callbackUrl, ?string $secret = null): Response
    {
        $body = ['eventId' => $eventId, 'callbackUrl' => $callbackUrl];
        if (filled($secret)) {
            $body['secret'] = $secret;
        }

        return $this->request('post', '/v1/webhooks', $body, throw: false);
    }

    public function deleteWebhook(string $eventId): Response
    {
        return $this->request('delete', '/v1/webhooks/' . rawurlencode($eventId), throw: false);
    }

    private function request(string $method, string $path, array $data = [], bool $throw = true): Response
    {
        $response = $this->send($method, $path, $data, $this->token());

        // Token expired/revoked early → refresh once.
        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = $this->send($method, $path, $data, $this->token());
        }

        if ($throw && $response->failed()) {
            throw new RuntimeException(sprintf(
                'Property Finder %s %s failed (%d): %s',
                strtoupper($method), $path, $response->status(), mb_substr($response->body(), 0, 500)
            ));
        }

        return $response;
    }

    private function send(string $method, string $path, array $data, string $token): Response
    {
        $http = Http::baseUrl(config('propertyfinder.base_url'))
            ->timeout(config('propertyfinder.http_timeout', 20))
            ->acceptJson()
            ->withToken($token)
            // 429 rate limit / 5xx → back off and retry, without throwing from retry().
            ->retry(3, fn (int $attempt) => $attempt * 1000, fn ($e, $request) => $e instanceof \Illuminate\Http\Client\RequestException
                && ($e->response->status() === 429 || $e->response->serverError()), throw: false);

        return match ($method) {
            'get' => $http->get($path, $data),
            'post' => $http->post($path, $data),
            'delete' => $http->delete($path, $data),
        };
    }

    private function token(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if ($cached) {
            return $cached;
        }

        if (! self::isConfigured()) {
            throw new RuntimeException('Property Finder API is not configured (PF_API_KEY / PF_API_SECRET).');
        }

        $response = Http::baseUrl(config('propertyfinder.base_url'))
            ->timeout(config('propertyfinder.http_timeout', 20))
            ->acceptJson()
            ->post('/v1/auth/token', [
                'apiKey' => config('propertyfinder.api_key'),
                'apiSecret' => config('propertyfinder.api_secret'),
            ]);

        if ($response->failed() || ! $response->json('accessToken')) {
            throw new RuntimeException(sprintf(
                'Property Finder auth failed (%d): %s', $response->status(), mb_substr($response->body(), 0, 500)
            ));
        }

        $token = $response->json('accessToken');
        // Refresh a minute before PF expires it (expiresIn is seconds, typically 1800).
        $ttl = max(60, (int) $response->json('expiresIn', 1800) - 60);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }
}
