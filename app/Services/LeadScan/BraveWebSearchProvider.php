<?php

namespace App\Services\LeadScan;

use App\Services\LeadScan\Contracts\WebSearchProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BraveWebSearchProvider implements WebSearchProvider
{
    private const ALLOWED_HOST = 'api.search.brave.com';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $endpoint,
        private readonly int $timeout = 12,
        private readonly int $count = 8,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->endpointIsAllowed();
    }

    public function name(): string
    {
        return 'brave';
    }

    public function search(string $query): array
    {
        if (! $this->isConfigured()) {
            throw new LeadScanException('unconfigured', 'Web search is not configured.');
        }

        try {
            $response = Http::withHeaders([
                'X-Subscription-Token' => $this->apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->acceptJson()
                ->get($this->endpoint, [
                    'q' => $query,
                    'count' => max(1, min(20, $this->count)),
                    'search_lang' => 'en',
                    'country' => 'AE',
                ]);
        } catch (ConnectionException) {
            throw new LeadScanException('timeout', 'The search provider timed out.');
        }

        if ($response->status() === 429) {
            throw new LeadScanException('rate_limit', 'The search provider rate limit was reached.');
        }

        if (! $response->successful()) {
            throw new LeadScanException('provider', 'The search provider returned an error.');
        }

        $rows = data_get($response->json(), 'web.results');
        if (! is_array($rows)) {
            return [];
        }

        $results = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $url = $this->publicHttpUrl((string) ($row['url'] ?? ''));
            if ($url === null) {
                continue;
            }
            $results[] = [
                'title' => $this->plain((string) ($row['title'] ?? 'Untitled result'), 180),
                'url' => $url,
                'excerpt' => $this->plain((string) ($row['description'] ?? ''), 280),
                'published_at' => $this->plain((string) ($row['page_age'] ?? $row['age'] ?? ''), 40) ?: null,
                'source_type' => $this->sourceType($url),
            ];
        }

        return $results;
    }

    private function endpointIsAllowed(): bool
    {
        $host = strtolower((string) parse_url($this->endpoint, PHP_URL_HOST));

        return $host === self::ALLOWED_HOST
            && str_starts_with(strtolower($this->endpoint), 'https://');
    }

    private function publicHttpUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 500) {
            return null;
        }
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user'])) {
            return null;
        }
        if ($this->isBlockedHost($host)) {
            return null;
        }

        return $url;
    }

    private function isBlockedHost(string $host): bool
    {
        if (in_array($host, ['localhost', 'metadata.google.internal'], true)) {
            return true;
        }
        if (str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return false;
    }

    private function sourceType(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (str_contains($host, 'linkedin.com')) {
            return 'linkedin_public';
        }
        if (preg_match('/(^|\.)(news|reuters|bloomberg|bbc|thenational|gulfnews|arabianbusiness)\./', $host)) {
            return 'news';
        }

        return 'web';
    }

    private function plain(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

        return Str::limit($value, $limit, '');
    }
}
