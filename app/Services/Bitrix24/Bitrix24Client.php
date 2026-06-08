<?php

namespace App\Services\Bitrix24;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Bitrix24Client
{
    private string $baseUrl;
    private int $timeout;
    private int $maxRetries;

    public function __construct()
    {
        $url = config('bitrix24.webhook_url');
        if (!$url) {
            throw new \RuntimeException('BITRIX24_WEBHOOK_URL is not configured.');
        }
        $this->baseUrl = rtrim($url, '/') . '/';
        $this->timeout = (int) config('bitrix24.http_timeout', 60);
        $this->maxRetries = (int) config('bitrix24.api_max_retries', 5);
    }

    public function call(string $method, array $params = []): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            try {
                $response = Http::timeout($this->timeout)
                    ->asForm()
                    ->post($this->baseUrl . $method, $params);

                $json = $response->json();
                $status = $response->status();

                if (is_array($json) && $this->isRateLimited($json, $status)) {
                    $code = (string) ($json['error'] ?? 'rate_limit');
                    $this->sleepBackoff($attempt, $method, $code);
                    continue;
                }

                if (is_array($json) && (isset($json['error']) || isset($json['error_description']))) {
                    $desc = $json['error_description'] ?? $json['error'] ?? 'unknown error';
                    $code = (string) ($json['error'] ?? '');

                    if ($this->isRetryableErrorCode($code)) {
                        $this->sleepBackoff($attempt, $method, $code);
                        continue;
                    }

                    throw new Bitrix24Exception("Bitrix24 {$method}: {$desc}", $status, $desc);
                }

                if (!$response->successful()) {
                    if ($this->isRetryableHttpStatus($status)) {
                        $this->sleepBackoff($attempt, $method, 'http_' . $status);
                        continue;
                    }

                    throw new Bitrix24Exception(
                        "Bitrix24 {$method} failed: HTTP {$status} " . $response->body(),
                        $status
                    );
                }

                return is_array($json) ? $json : [];
            } catch (ConnectionException $e) {
                $lastException = $e;
                $this->sleepBackoff($attempt, $method, 'connection');
                continue;
            } catch (Bitrix24Exception $e) {
                throw $e;
            } catch (\Throwable $e) {
                $lastException = $e;
                if ($attempt < $this->maxRetries) {
                    $this->sleepBackoff($attempt, $method, 'exception');
                    continue;
                }
                throw $e;
            }
        }

        Log::error('Bitrix24 API exhausted retries', [
            'method'   => $method,
            'attempts' => $this->maxRetries,
            'error'    => $lastException?->getMessage(),
        ]);

        $hint = str_contains((string) $lastException?->getMessage(), 'OPERATION_TIME_LIMIT')
            ? ' Bitrix24 portal is rate-limited — wait 15–30 minutes and retry with fewer parallel workers.'
            : '';

        throw new Bitrix24Exception(
            "Bitrix24 {$method}: failed after {$this->maxRetries} attempts"
            . ($lastException ? ' — ' . $lastException->getMessage() : '')
            . $hint,
            503
        );
    }

    private function isRateLimited(array $json, int $httpStatus): bool
    {
        $error = (string) ($json['error'] ?? '');
        $desc = strtolower((string) ($json['error_description'] ?? ''));

        return $httpStatus === 429
            || $error === 'QUERY_LIMIT_EXCEEDED'
            || $error === 'OPERATION_TIME_LIMIT'
            || str_contains($desc, 'operation time limit')
            || str_contains($desc, 'limit')
            || str_contains($desc, 'too many')
            || str_contains($desc, 'blocked');
    }

    private function isRetryableErrorCode(string $code): bool
    {
        return in_array($code, [
            'QUERY_LIMIT_EXCEEDED',
            'OPERATION_TIME_LIMIT',
            'INTERNAL_SERVER_ERROR',
            'SERVER_ERROR',
            'SERVICE_UNAVAILABLE',
        ], true);
    }

    private function isRetryableHttpStatus(int $status): bool
    {
        return in_array($status, [429, 500, 502, 503, 504], true);
    }

    private function sleepBackoff(int $attempt, string $method, string $reason): void
    {
        $baseMs = (int) config('bitrix24.api_retry_base_ms', 500);
        $maxMs = (int) config('bitrix24.api_retry_max_ms', 30000);
        $sleepMs = min($maxMs, $baseMs * (2 ** max(0, $attempt - 1)));

        // Portal-wide block (OPERATION_TIME_LIMIT) needs longer waits than query limits.
        if (str_contains(strtolower($reason), 'operation_time_limit')
            || str_contains(strtolower($reason), 'rate_limit')
        ) {
            $sleepMs = min(180000, 30000 * $attempt);
        }

        Log::warning('Bitrix24 API retry', [
            'method'    => $method,
            'attempt'   => $attempt,
            'reason'    => $reason,
            'sleep_ms'  => $sleepMs,
        ]);

        usleep($sleepMs * 1000);
    }

    public function listLeads(?int $start = 0, array $filter = []): array
    {
        $params = [
            'start'  => $start ?? 0,
            'order'  => ['ID' => 'ASC'],
            'select' => ['*', 'UF_*', 'EMAIL', 'PHONE'],
        ];
        if (!empty($filter)) {
            $params['filter'] = $filter;
        }

        return $this->call('crm.lead.list', $params);
    }

    /**
     * Fetch multiple consecutive Bitrix24 pages in one job (50 leads/page API max).
     *
     * @return array{result: array, total: int, next: int|null, pages_fetched: int}
     */
    public function listLeadPages(?int $start, int $maxPages): array
    {
        $maxPages = max(1, min(50, $maxPages));
        $merged = [];
        $total = 0;
        $next = $start ?? 0;
        $pages = 0;

        $hardCap = (int) config('bitrix24.max_pages_per_request', 50);

        do {
            $page = $this->listLeads($next);
            $total = (int) ($page['total'] ?? $total);
            $chunk = $page['result'] ?? [];
            if (!empty($chunk)) {
                $merged = array_merge($merged, $chunk);
            }
            $next = isset($page['next']) ? (int) $page['next'] : null;
            $pages++;

            if ($pages >= $hardCap) {
                Log::warning('Bitrix24 listLeadPages hit hard page cap', [
                    'cap'   => $hardCap,
                    'next'  => $next,
                ]);
                break;
            }
        } while ($next !== null && $pages < $maxPages);

        return [
            'result'         => $merged,
            'total'          => $total,
            'next'           => $next,
            'pages_fetched'  => $pages,
        ];
    }

    /**
     * Fetch pages for an ID shard using filter + pagination.
     */
    public function listLeadPagesForShard(
        int $minId,
        int $maxId,
        ?int $start,
        int $maxPages,
    ): array {
        return $this->listLeadPagesWithFilter(
            ['>=ID' => $minId, '<=ID' => $maxId],
            $start,
            $maxPages,
        );
    }

    /**
     * @return array{result: array, total: int, next: int|null, pages_fetched: int}
     */
    public function listLeadPagesWithFilter(array $filter, ?int $start, int $maxPages): array
    {
        $maxPages = max(1, min(50, $maxPages));
        $merged = [];
        $total = 0;
        $next = $start ?? 0;
        $pages = 0;

        do {
            $page = $this->listLeads($next, $filter);
            $total = (int) ($page['total'] ?? $total);
            $chunk = $page['result'] ?? [];
            if (!empty($chunk)) {
                foreach ($chunk as $row) {
                    $id = (int) ($row['ID'] ?? 0);
                    if ($id >= ($filter['>=ID'] ?? 0) && $id <= ($filter['<=ID'] ?? PHP_INT_MAX)) {
                        $merged[] = $row;
                    }
                }
            }
            $next = isset($page['next']) ? (int) $page['next'] : null;
            $pages++;
        } while ($next !== null && $pages < $maxPages);

        return [
            'result'        => $merged,
            'total'         => $total,
            'next'          => $next,
            'pages_fetched' => $pages,
        ];
    }

    /**
     * Probe portal bounds for parallel ID-range sharding.
     *
     * @return array{min_id: int, max_id: int, total: int}
     */
    public function probeLeadIdBounds(): array
    {
        $first = $this->listLeads(0);
        $total = (int) ($first['total'] ?? 0);
        $firstRows = $first['result'] ?? [];
        $minId = 0;
        foreach ($firstRows as $row) {
            $id = (int) ($row['ID'] ?? 0);
            if ($id > 0 && ($minId === 0 || $id < $minId)) {
                $minId = $id;
            }
        }

        $maxId = $minId;
        foreach ($firstRows as $row) {
            $id = (int) ($row['ID'] ?? 0);
            if ($id > $maxId) {
                $maxId = $id;
            }
        }

        if ($total > 50) {
            $lastStart = max(0, $total - 50);
            $last = $this->listLeads($lastStart);
            foreach ($last['result'] ?? [] as $row) {
                $id = (int) ($row['ID'] ?? 0);
                if ($id > $maxId) {
                    $maxId = $id;
                }
            }
        }

        if ($minId === 0 && $maxId === 0) {
            $minId = 1;
        }

        return [
            'min_id' => $minId,
            'max_id' => $maxId,
            'total'  => $total,
        ];
    }

    /**
     * Bitrix24 REST batch — up to 50 commands per HTTP request.
     *
     * @param  array<string, string>  $commands  e.g. ['p0' => 'crm.lead.list?start=0']
     */
    public function batch(array $commands): array
    {
        if (empty($commands)) {
            return [];
        }

        return $this->call('batch', ['cmd' => $commands]);
    }

    public function getLead(int $id): ?array
    {
        $r = $this->call('crm.lead.get', ['id' => $id]);
        return $r['result'] ?? null;
    }

    /** Single activity (used to resolve its owner lead from a webhook event). */
    public function getActivity(int $id): ?array
    {
        $r = $this->call('crm.activity.get', ['id' => $id]);
        return $r['result'] ?? null;
    }

    /** Single timeline comment (used to resolve its lead from a webhook event). */
    public function getTimelineComment(int $id): ?array
    {
        $r = $this->call('crm.timeline.comment.get', ['id' => $id]);
        return $r['result'] ?? null;
    }

    public function listTimelineComments(int $leadId): array
    {
        $all = [];
        $start = 0;
        do {
            $r = $this->call('crm.timeline.comment.list', [
                'filter' => ['ENTITY_TYPE' => 'lead', 'ENTITY_ID' => $leadId],
                'order'  => ['CREATED' => 'ASC'],
                'start'  => $start,
            ]);
            $all = array_merge($all, $r['result'] ?? []);
            $start = $r['next'] ?? null;
        } while ($start !== null);
        return $all;
    }

    public function listActivities(int $leadId): array
    {
        $all = [];
        $start = 0;
        do {
            $r = $this->call('crm.activity.list', [
                'filter' => ['OWNER_ID' => $leadId, 'OWNER_TYPE_ID' => 1],
                'order'  => ['CREATED' => 'ASC'],
                'start'  => $start,
            ]);
            $all = array_merge($all, $r['result'] ?? []);
            $start = $r['next'] ?? null;
        } while ($start !== null);
        return $all;
    }

    /**
     * Full Bitrix24 timeline for a lead (status changes, system notes, etc.).
     * Distinct from comments (crm.timeline.comment.list) and activities
     * (crm.activity.list) — those have their own dedicated endpoints.
     * entityTypeId=1 = lead.
     */
    public function listTimelineItems(int $leadId): array
    {
        $all = [];
        $start = 0;
        do {
            $r = $this->call('crm.timeline.item.list', [
                'filter' => ['entityTypeId' => 1, 'entityId' => $leadId],
                'order'  => ['id' => 'ASC'],
                'start'  => $start,
            ]);
            $all = array_merge($all, $r['result'] ?? []);
            $start = $r['next'] ?? null;
        } while ($start !== null);
        return $all;
    }

    /**
     * Older-portal-friendly timeline fallback. crm.timeline.bindings.list
     * exists on more Bitrix24 versions than crm.timeline.item.list, and
     * returns the associations of timeline records to a CRM entity. Each
     * binding carries enough metadata (TIMELINE_ID, TYPE_ID, CREATED) to log
     * a coarse-grained history entry, even when the full timeline item API
     * isn't available. ENTITY_TYPE_ID=1 = lead.
     */
    public function listTimelineBindings(int $leadId): array
    {
        $all = [];
        $start = 0;
        do {
            // Bitrix24 wants OWNER_ID / OWNER_TYPE_ID here, not ENTITY_*.
            // (Verified against a live portal — the ENTITY_* variant returns
            // "OWNER_ID is not defined or invalid".)
            $r = $this->call('crm.timeline.bindings.list', [
                'filter' => ['OWNER_TYPE_ID' => 1, 'OWNER_ID' => $leadId],
                'order'  => ['CREATED' => 'ASC'],
                'start'  => $start,
            ]);
            $all = array_merge($all, $r['result'] ?? []);
            $start = $r['next'] ?? null;
        } while ($start !== null);
        return $all;
    }

    /**
     * crm.timeline.logmessage.list — system log messages on a CRM entity.
     * Uses camelCase params (entityTypeId / entityId). 1 = lead.
     * On many portals this returns empty for leads, but it's a cheap extra
     * source to check when richer timeline endpoints aren't available.
     */
    public function listTimelineLogMessages(int $leadId): array
    {
        try {
            $r = $this->call('crm.timeline.logmessage.list', [
                'entityTypeId' => 1,
                'entityId'     => $leadId,
            ]);
        } catch (\Throwable $e) {
            throw $e;
        }
        // Response wraps under "logMessages" instead of "result".
        $result = $r['result'] ?? [];
        if (isset($result['logMessages']) && is_array($result['logMessages'])) {
            return $result['logMessages'];
        }
        return is_array($result) ? $result : [];
    }

    public function getUser(int $id): ?array
    {
        // Cache globally (shared across queue jobs) so a 30k-lead sync resolves
        // each Bitrix24 user via the throttled REST API at most once per hour
        // instead of once per lead. `false` is the "known-missing" sentinel —
        // Cache::remember would treat a cached null as a miss and re-fetch.
        $key = "bitrix_user_{$id}";
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached === false ? null : $cached;
        }

        try {
            $r = $this->call('user.get', ['ID' => $id]);
            $user = $r['result'][0] ?? null;
        } catch (\Throwable) {
            $user = null;
        }

        Cache::put($key, $user ?? false, 3600);
        return $user;
    }

    /**
     * Fetch every Bitrix24 user, paging through user.get (50 rows per page).
     * Pass a FILTER (e.g. ['ACTIVE' => false]) to scope the result.
     *
     * @param  array<string, mixed>  $filter
     * @return array<int, array<string, mixed>>
     */
    public function listUsers(array $filter = []): array
    {
        $all = [];
        $start = 0;
        do {
            $params = ['start' => $start];
            if ($filter !== []) {
                $params['FILTER'] = $filter;
            }
            $r = $this->call('user.get', $params);
            $page = $r['result'] ?? [];
            $all = array_merge($all, $page);
            $start = isset($r['next']) ? (int) $r['next'] : null;
        } while ($start !== null && $page !== []);

        return $all;
    }
}
