<?php

namespace App\Services\Monitoring;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Live traffic snapshot for the Super Admin Request Monitor.
 *
 * Sources (read-only, tail only):
 *  - the web server access log (cPanel ~/access-logs/<domain>-ssl_log): every HTTP request
 *  - storage/logs/endpoint-usage-*.log (LogEndpointUsage middleware): board timings per user
 *
 * Files are read backwards in chunks and reading stops at the first line older than the
 * window, so cost scales with the window, not the file size.
 */
class RequestMonitorService
{
    private const CHUNK_BYTES = 1_048_576;      // 1 MB per backward read

    private const MAX_SCAN_BYTES = 80_000_000;   // hard stop per file (~250k access lines)

    private const ACCESS_LINE = '/^(\S+) \S+ \S+ \[([^\]]+)\] "(\S+) (\S+)[^"]*" (\d{3}) \S+(?: "[^"]*" "([^"]*)")?/';

    public function snapshot(int $minutes): array
    {
        $now = Carbon::now();
        $cutoff = $now->copy()->subMinutes($minutes);

        $access = $this->accessStats($cutoff, $now, $minutes);
        $board = $this->boardStats($cutoff);

        // Name each IP: users seen loading the board from it in this window first, then users
        // whose last login came from it (users.last_login_ip — already stored at login).
        $loginUsers = $this->usersByLastLoginIp(array_column($access['top_ips'], 'ip'));
        foreach ($access['top_ips'] as &$row) {
            $seen = $board['users_by_ip'][$row['ip']] ?? [];
            $seenIds = array_column($seen, 'id');
            $fromLogin = array_values(array_filter(
                $loginUsers[$row['ip']] ?? [],
                fn ($u) => ! in_array($u['id'], $seenIds, true)
            ));
            $row['users'] = array_values(array_slice($seen, 0, 3));
            $row['login_users'] = array_slice($fromLogin, 0, 5);
            $row['user_total'] = count($seen) + count($fromLogin);
        }
        unset($row, $board['users_by_ip']);

        return [
            'generated_at' => $now->toIso8601String(),
            'window_minutes' => $minutes,
            'server' => $this->serverStats(),
            'access' => $access,
            'board' => $board,
        ];
    }

    // ------------------------------------------------------------------ access log

    private function accessStats(CarbonInterface $cutoff, CarbonInterface $now, int $minutes): array
    {
        $path = $this->accessLogPath();
        $empty = [
            'available' => false,
            'source' => $path ? basename($path) : null,
            'total' => 0,
            'per_minute_avg' => 0,
            'unique_ips' => 0,
            'status' => ['2xx' => 0, '3xx' => 0, '4xx' => 0, '429' => 0, '5xx' => 0],
            'timeline' => [],
            'top_urls' => [],
            'top_ips' => [],
            'recent_errors' => [],
            'truncated' => false,
        ];
        if ($path === null) {
            $empty['message'] = 'Access log not found. Set REQUEST_MONITOR_ACCESS_LOG in .env to its full path.';

            return $empty;
        }

        $cutoffTs = $cutoff->getTimestamp();
        $total = 0;
        $status = $empty['status'];
        $urls = [];
        $ips = [];
        $timeline = [];
        $errors = [];

        $truncated = ! $this->eachLineBackwards($path, function (string $line) use (
            $cutoffTs, &$total, &$status, &$urls, &$ips, &$timeline, &$errors
        ) {
            if (! preg_match(self::ACCESS_LINE, $line, $m)) {
                return true;
            }
            $ts = \DateTime::createFromFormat('d/M/Y:H:i:s O', $m[2]);
            if ($ts === false) {
                return true;
            }
            $t = $ts->getTimestamp();
            if ($t < $cutoffTs) {
                // Lines are written when a request ends but stamped with its start, so a slow
                // request can sit among newer lines. Skip it; stop only well past the window.
                return $t >= $cutoffTs - 120;
            }

            [$ip, $method, $rawPath, $code, $ua] = [$m[1], $m[3], $m[4], (int) $m[5], $m[6] ?? ''];
            $route = $method.' '.$this->normalizePath($rawPath);

            $total++;
            if ($code === 429) {
                $status['429']++;
            }
            $status[match (true) {
                $code >= 500 => '5xx',
                $code >= 400 => '4xx',
                $code >= 300 => '3xx',
                default => '2xx',
            }]++;

            $urls[$route] = ($urls[$route] ?? 0) + 1;

            if (! isset($ips[$ip])) {
                $ips[$ip] = ['count' => 0, 'routes' => [], 'ua' => $ua];
            }
            $ips[$ip]['count']++;
            $ips[$ip]['routes'][$route] = ($ips[$ip]['routes'][$route] ?? 0) + 1;

            $minuteKey = intdiv($t, 60) * 60;
            if (! isset($timeline[$minuteKey])) {
                $timeline[$minuteKey] = ['total' => 0, 'errors' => 0];
            }
            $timeline[$minuteKey]['total']++;
            $timeline[$minuteKey]['ips'][$ip] = ($timeline[$minuteKey]['ips'][$ip] ?? 0) + 1;
            if ($code >= 500) {
                $timeline[$minuteKey]['errors']++;
                if (count($errors) < 25) {
                    $errors[] = [
                        'at' => Carbon::createFromTimestamp($t)->toIso8601String(),
                        'status' => $code,
                        'route' => $route,
                        'ip' => $ip,
                    ];
                }
            }

            return true;
        });

        arsort($urls);
        uasort($ips, fn ($a, $b) => $b['count'] <=> $a['count']);

        $topUrls = [];
        foreach (array_slice($urls, 0, 25, true) as $route => $count) {
            $topUrls[] = [
                'route' => $route,
                'count' => $count,
                'per_minute' => round($count / max(1, $minutes), 1),
                'share' => $total ? round($count * 100 / $total, 1) : 0,
            ];
        }

        $topIps = [];
        foreach (array_slice($ips, 0, 15, true) as $ip => $info) {
            arsort($info['routes']);
            $topRoute = array_key_first($info['routes']);
            $routes = [];
            foreach (array_slice($info['routes'], 0, 6, true) as $r => $c) {
                $routes[] = [
                    'route' => $r,
                    'count' => $c,
                    'per_minute' => round($c / max(1, $minutes), 1),
                    'share' => round($c * 100 / max(1, $info['count']), 1),
                ];
            }
            $topIps[] = [
                'routes' => $routes,
                'ip' => $ip,
                'count' => $info['count'],
                'per_minute' => round($info['count'] / max(1, $minutes), 1),
                'share' => $total ? round($info['count'] * 100 / $total, 1) : 0,
                'top_route' => $topRoute,
                'top_route_count' => $topRoute ? $info['routes'][$topRoute] : 0,
                'user_agent' => mb_substr((string) $info['ua'], 0, 160),
            ];
        }

        // Dense minute series (zeros included) so the chart shows quiet minutes too.
        $series = [];
        $topIp = array_key_first($ips);
        $start = intdiv($cutoff->getTimestamp(), 60) * 60 + 60;
        $end = intdiv($now->getTimestamp(), 60) * 60;
        for ($m = $start; $m <= $end; $m += 60) {
            $series[] = [
                'minute' => Carbon::createFromTimestamp($m)->toIso8601String(),
                'total' => $timeline[$m]['total'] ?? 0,
                'errors' => $timeline[$m]['errors'] ?? 0,
                'top' => $topIp !== null ? ($timeline[$m]['ips'][$topIp] ?? 0) : 0,
            ];
        }

        return [
            'available' => true,
            'source' => basename($path),
            'total' => $total,
            'per_minute_avg' => round($total / max(1, $minutes), 1),
            'unique_ips' => count($ips),
            'status' => $status,
            'timeline' => $series,
            'top_urls' => $topUrls,
            'top_ips' => $topIps,
            'recent_errors' => $errors,
            'truncated' => $truncated,
        ];
    }

    private function accessLogPath(): ?string
    {
        $configured = config('request_monitor.access_log');
        if ($configured && is_file($configured) && is_readable($configured)) {
            return $configured;
        }

        // cPanel keeps per-domain logs in ~/access-logs. The CRM lives one level below ~.
        $homes = array_filter(array_unique([
            getenv('HOME') ?: null,
            function_exists('posix_getpwuid') && function_exists('posix_geteuid')
                ? (posix_getpwuid(posix_geteuid())['dir'] ?? null)
                : null,
            dirname(base_path()),
        ]));

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        foreach ($homes as $home) {
            $files = glob(rtrim($home, '/').'/access-logs/*') ?: [];
            $files = array_values(array_filter($files, fn ($f) => is_file($f) && is_readable($f)));
            if (! $files) {
                continue;
            }
            // Prefer the CRM domain's SSL log, else the most recently written file.
            usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
            if ($host) {
                foreach ($files as $f) {
                    if (str_contains(basename($f), $host)) {
                        return $f;
                    }
                }
            }

            return $files[0];
        }

        return null;
    }

    private function normalizePath(string $path): string
    {
        $path = strtok($path, '?') ?: '/';
        $path = preg_replace('#/\d+(?=/|$)#', '/{id}', $path) ?? $path;
        $path = preg_replace('#/[0-9a-f]{8}-[0-9a-f-]{27,}(?=/|$)#i', '/{uuid}', $path) ?? $path;

        return mb_substr($path, 0, 140);
    }

    // ------------------------------------------------------------------ board timings

    private function boardStats(CarbonInterface $cutoff): array
    {
        $files = glob(storage_path('logs/endpoint-usage-*.log')) ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        // Today's file, plus yesterday's when the window crosses midnight.
        $files = array_slice($files, 0, 2);

        $cutoffStr = $cutoff->format('Y-m-d H:i:s');
        $allMs = [];
        $byUser = [];
        $usersByIp = [];
        $endpointMs = [];
        $endpointDb = [];

        foreach ($files as $file) {
            $reachedCutoff = false;
            $this->eachLineBackwards($file, function (string $line) use ($cutoffStr, &$allMs, &$byUser, &$usersByIp, &$reachedCutoff, &$endpointMs, &$endpointDb) {
                if (strlen($line) < 22 || $line[0] !== '[') {
                    return true;
                }
                if (substr($line, 1, 19) < $cutoffStr) {
                    $reachedCutoff = true;

                    return false;
                }
                // "[ts] env.INFO: api/route/{param} {json} []" — the message is the route uri.
                if (! preg_match('/\.INFO: (\S+) (\{.*\})/', $line, $mm)) {
                    return true;
                }
                $ctx = json_decode(substr($mm[2], 0, strrpos($mm[2], '}') + 1), true);
                if (! is_array($ctx) || ! isset($ctx['ms'])) {
                    return true;
                }

                $ms = (float) $ctx['ms'];
                $endpointKey = strtoupper((string) ($ctx['method'] ?? 'GET')).' /'.ltrim($mm[1], '/');
                $endpointMs[$endpointKey][] = $ms;
                if (isset($ctx['db_ms'])) {
                    $db = &$endpointDb[$endpointKey];
                    $db['n'] = ($db['n'] ?? 0) + 1;
                    $db['db_ms'] = ($db['db_ms'] ?? 0) + (float) $ctx['db_ms'];
                    $db['queries'] = ($db['queries'] ?? 0) + (int) ($ctx['db_queries'] ?? 0);
                    if (! empty($ctx['slowest_sql']) && (float) ($ctx['slowest_ms'] ?? 0) > ($db['slowest_ms'] ?? 0)) {
                        $db['slowest_ms'] = (float) $ctx['slowest_ms'];
                        $db['slowest_sql'] = (string) $ctx['slowest_sql'];
                    }
                    unset($db);
                }

                if (! str_contains($mm[1], 'stages-with-leads')) {
                    return true;
                }
                $uid = (int) ($ctx['user_id'] ?? 0);
                $allMs[] = $ms;

                if (! isset($byUser[$uid])) {
                    $byUser[$uid] = ['ms' => [], 'role' => $ctx['role'] ?? null, 'per_page' => []];
                }
                $byUser[$uid]['ms'][] = $ms;
                $pp = (string) ($ctx['filters']['per_page'] ?? '');
                if ($pp !== '') {
                    $byUser[$uid]['per_page'][$pp] = ($byUser[$uid]['per_page'][$pp] ?? 0) + 1;
                }
                if (! empty($ctx['ip']) && $uid) {
                    $usersByIp[$ctx['ip']][$uid] = $uid;
                }

                return true;
            });
            if ($reachedCutoff) {
                break;
            }
        }

        $ids = array_values(array_filter(array_unique(array_merge(
            array_keys($byUser),
            ...array_values(array_map('array_values', $usersByIp))
        ))));
        $names = [];
        if ($ids) {
            try {
                $names = User::query()
                    ->whereIn('id', $ids)
                    ->get(['id', 'name', 'display_name'])
                    ->mapWithKeys(fn ($u) => [$u->id => $u->display_name ?: $u->name])
                    ->all();
            } catch (\Throwable) {
                // Names are cosmetic — fall back to "User #id".
            }
        }

        $users = [];
        foreach ($byUser as $uid => $info) {
            sort($info['ms']);
            arsort($info['per_page']);
            $users[] = [
                'user_id' => $uid,
                'name' => $names[$uid] ?? ($uid ? "User #{$uid}" : 'Guest'),
                'role' => $info['role'],
                'loads' => count($info['ms']),
                'avg_ms' => (int) round(array_sum($info['ms']) / count($info['ms'])),
                'p90_ms' => (int) round($this->percentile($info['ms'], 0.9)),
                'max_ms' => (int) round(end($info['ms'])),
                'per_page' => array_key_first($info['per_page']),
            ];
        }
        usort($users, fn ($a, $b) => $b['loads'] <=> $a['loads']);

        sort($allMs);
        $ipUsers = [];
        foreach ($usersByIp as $ip => $ids) {
            $ipUsers[$ip] = array_map(fn ($id) => ['id' => $id, 'name' => $names[$id] ?? "User #{$id}"], array_values($ids));
        }

        $endpoints = [];
        foreach ($endpointMs as $key => $list) {
            sort($list);
            $db = $endpointDb[$key] ?? null;
            $endpoints[] = [
                'route' => $key,
                'count' => count($list),
                'median_ms' => (int) round($this->percentile($list, 0.5)),
                'p90_ms' => (int) round($this->percentile($list, 0.9)),
                'max_ms' => (int) round(end($list)),
                // Averages over requests logged with DB stats (older log lines have none).
                'avg_db_ms' => $db ? (int) round($db['db_ms'] / $db['n']) : null,
                'avg_queries' => $db ? (int) round($db['queries'] / $db['n']) : null,
                'slowest_query_ms' => $db && isset($db['slowest_ms']) ? (int) round($db['slowest_ms']) : null,
                'slowest_query' => $db['slowest_sql'] ?? null,
            ];
        }
        usort($endpoints, fn ($a, $b) => $b['p90_ms'] <=> $a['p90_ms']);

        return [
            'available' => (bool) $files,
            'endpoints' => $endpoints,
            'loads' => count($allMs),
            'avg_ms' => $allMs ? (int) round(array_sum($allMs) / count($allMs)) : 0,
            'median_ms' => (int) round($this->percentile($allMs, 0.5)),
            'p90_ms' => (int) round($this->percentile($allMs, 0.9)),
            'max_ms' => $allMs ? (int) round(end($allMs)) : 0,
            'users' => array_slice($users, 0, 20),
            'users_by_ip' => $ipUsers,
        ];
    }

    /**
     * @param  string[]  $ips
     * @return array<string, array<int, array{id:int,name:string,last_login_at:?string}>>
     */
    private function usersByLastLoginIp(array $ips): array
    {
        if (! $ips) {
            return [];
        }

        try {
            $rows = User::query()
                ->whereIn('last_login_ip', $ips)
                ->orderByDesc('last_login_at')
                ->limit(300)
                ->get(['id', 'name', 'display_name', 'last_login_ip', 'last_login_at']);
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($rows as $u) {
            $out[$u->last_login_ip][] = [
                'id' => (int) $u->id,
                'name' => $u->display_name ?: $u->name,
                'last_login_at' => $u->last_login_at ? Carbon::parse($u->last_login_at)->toIso8601String() : null,
            ];
        }

        return $out;
    }

    // ------------------------------------------------------------------ server

    private function serverStats(): array
    {
        $load = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: null) : null;

        $cpus = null;
        if (is_readable('/proc/cpuinfo')) {
            $cpus = substr_count((string) @file_get_contents('/proc/cpuinfo'), "\nprocessor") + 1;
        }

        return [
            'load' => $load ? array_map(fn ($v) => round($v, 2), $load) : null,
            'cpus' => $cpus,
            'php_version' => PHP_VERSION,
            'opcache' => function_exists('opcache_get_status') && (bool) (@opcache_get_status(false)['opcache_enabled'] ?? false),
            'time' => Carbon::now()->toIso8601String(),
        ];
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Feed lines newest-first to $fn until it returns false or the file start / scan cap.
     * Returns false only when the scan cap stopped it (window may be incomplete).
     */
    private function eachLineBackwards(string $path, callable $fn): bool
    {
        $fh = @fopen($path, 'rb');
        if (! $fh) {
            return true;
        }

        try {
            $pos = (int) (fstat($fh)['size'] ?? 0);
            $scanned = 0;
            $carry = '';

            while ($pos > 0) {
                if ($scanned >= self::MAX_SCAN_BYTES) {
                    return false;
                }
                $read = min(self::CHUNK_BYTES, $pos);
                $pos -= $read;
                fseek($fh, $pos);
                $chunk = fread($fh, $read).$carry;
                $scanned += $read;

                $lines = explode("\n", $chunk);
                // First piece may be a partial line — keep it for the next (earlier) chunk.
                $carry = $pos > 0 ? array_shift($lines) : '';

                for ($i = count($lines) - 1; $i >= 0; $i--) {
                    $line = rtrim($lines[$i], "\r");
                    if ($line === '') {
                        continue;
                    }
                    if ($fn($line) === false) {
                        return true;
                    }
                }
            }

            if ($carry !== '') {
                $fn(rtrim($carry, "\r"));
            }

            return true;
        } finally {
            fclose($fh);
        }
    }

    private function percentile(array $sorted, float $p): float
    {
        $n = count($sorted);
        if ($n === 0) {
            return 0.0;
        }

        return (float) $sorted[min($n - 1, (int) floor($p * $n))];
    }
}
