<?php

namespace App\Services\Bitrix24;

use Illuminate\Support\Facades\Http;

class Bitrix24Client
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $url = config('bitrix24.webhook_url');
        if (!$url) {
            throw new \RuntimeException('BITRIX24_WEBHOOK_URL is not configured.');
        }
        $this->baseUrl = rtrim($url, '/') . '/';
        $this->timeout = (int) config('bitrix24.http_timeout', 30);
    }

    public function call(string $method, array $params = []): array
    {
        $response = Http::timeout($this->timeout)
            ->asForm()
            ->post($this->baseUrl . $method, $params);

        $json = $response->json();

        // Bitrix24 returns JSON with `error` / `error_description` on most failures,
        // sometimes with HTTP 4xx (e.g. lead not found returns 400). Prefer the
        // structured description over raw body.
        if (is_array($json) && (isset($json['error']) || isset($json['error_description']))) {
            $desc = $json['error_description'] ?? $json['error'] ?? 'unknown error';
            throw new Bitrix24Exception("Bitrix24 {$method}: {$desc}", $response->status(), $desc);
        }

        if (!$response->successful()) {
            throw new Bitrix24Exception(
                "Bitrix24 {$method} failed: HTTP " . $response->status() . ' ' . $response->body(),
                $response->status()
            );
        }

        return is_array($json) ? $json : [];
    }

    public function listLeads(int $start = 0): array
    {
        return $this->call('crm.lead.list', [
            'start'  => $start,
            'order'  => ['ID' => 'ASC'],
            'select' => ['*', 'UF_*', 'EMAIL', 'PHONE'],
        ]);
    }

    public function getLead(int $id): ?array
    {
        $r = $this->call('crm.lead.get', ['id' => $id]);
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
        try {
            $r = $this->call('user.get', ['ID' => $id]);
            return $r['result'][0] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
