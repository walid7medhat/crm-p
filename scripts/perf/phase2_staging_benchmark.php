<?php

/**
 * OIA Phase 2 — staging / production-like benchmark harness (READ-ONLY HTTP).
 *
 * Usage (staging only — refuse non-local / non-staging hosts by default):
 *
 *   BASE_URL=https://staging.example.com \
 *   JWT=... \
 *   LEAD_ID=12345 \
 *   php scripts/perf/phase2_staging_benchmark.php
 *
 * Optional:
 *   SAMPLES=15
 *   ALLOW_REMOTE=1          # required for non-localhost hosts
 *   ENDPOINTS=lead_show,comments,kanban
 *
 * Never copies production PII. Point at a staging DB populated with synthetic
 * or approved-anonymized data (see OIA_PHASE2_PERFORMANCE_REPORT.md).
 */

declare(strict_types=1);

$base = rtrim((string) (getenv('BASE_URL') ?: 'http://127.0.0.1:8000'), '/');
$jwt = (string) (getenv('JWT') ?: '');
$leadId = (int) (getenv('LEAD_ID') ?: 0);
$samples = max(3, (int) (getenv('SAMPLES') ?: 11));
$allowRemote = (string) (getenv('ALLOW_REMOTE') ?: '') === '1';
$endpoints = array_filter(array_map('trim', explode(',', (string) (getenv('ENDPOINTS') ?: 'lead_show,comments,kanban'))));

$host = parse_url($base, PHP_URL_HOST) ?: '';
$isLocal = in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
if (! $isLocal && ! $allowRemote) {
    fwrite(STDERR, "Refusing non-local BASE_URL without ALLOW_REMOTE=1\n");
    exit(1);
}
if ($jwt === '' || $leadId <= 0) {
    fwrite(STDERR, "JWT and LEAD_ID are required\n");
    exit(1);
}

function percentile(array $values, float $p): float
{
    sort($values);
    $n = count($values);
    if ($n === 0) {
        return 0.0;
    }
    $idx = (int) max(0, min($n - 1, (int) ceil($p * $n) - 1));

    return round($values[$idx], 2);
}

function requestJson(string $method, string $url, string $jwt): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer '.$jwt,
        ],
        CURLOPT_TIMEOUT => 120,
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException($err);
    }
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $totalMs = (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
    curl_close($ch);
    $body = substr($raw, $headerSize);

    return [
        'status' => $status,
        'ms' => $totalMs,
        'bytes' => strlen($body),
    ];
}

$map = [
    'lead_show' => fn () => requestJson('GET', $base.'/api/leads/'.$leadId, $jwt),
    'comments' => fn () => requestJson('GET', $base.'/api/leads/'.$leadId.'/comments', $jwt),
    'kanban' => fn () => requestJson('GET', $base.'/api/stages/kanban/stages-with-leads?per_page=15', $jwt),
    'history' => fn () => requestJson('GET', $base.'/api/leads/'.$leadId.'/history', $jwt),
    'matches' => fn () => requestJson('GET', $base.'/api/leads/'.$leadId.'/matches', $jwt),
];

$results = [];
foreach ($endpoints as $name) {
    if (! isset($map[$name])) {
        fwrite(STDERR, "Unknown endpoint: {$name}\n");
        continue;
    }
    $ms = [];
    $bytes = [];
    $statuses = [];
    // Discard first cold sample.
    try {
        $map[$name]();
    } catch (Throwable $e) {
        fwrite(STDERR, "{$name} cold failed: {$e->getMessage()}\n");
        continue;
    }
    for ($i = 0; $i < $samples; $i++) {
        $row = $map[$name]();
        $ms[] = $row['ms'];
        $bytes[] = $row['bytes'];
        $statuses[] = $row['status'];
    }
    $results[$name] = [
        'n' => $samples,
        'p50_ms' => percentile($ms, 0.50),
        'p95_ms' => percentile($ms, 0.95),
        'p99_ms' => percentile($ms, 0.99),
        'avg_ms' => round(array_sum($ms) / max(1, count($ms)), 2),
        'avg_bytes' => (int) round(array_sum($bytes) / max(1, count($bytes))),
        'statuses' => array_count_values($statuses),
    ];
}

$out = [
    'base_url' => $base,
    'lead_id' => $leadId,
    'samples' => $samples,
    'generated_at' => date('c'),
    'results' => $results,
];

$path = sys_get_temp_dir().'/oia_phase2_benchmark_'.date('Ymd_His').'.json';
file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT));
echo json_encode($out, JSON_PRETTY_PRINT).PHP_EOL;
echo "Wrote {$path}\n";
