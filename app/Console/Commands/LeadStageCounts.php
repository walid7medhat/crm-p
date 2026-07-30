<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lead;
use App\Models\Stage;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;

class LeadStageCounts extends Command
{
    protected $signature = 'leads:stage-counts';

    protected $description = 'Compare lead counts per stage: Bitrix24 total vs local total, and the difference';

    public function handle(Bitrix24Client $client)
    {
        $importer = new Bitrix24LeadImporter($client, 1);

        $this->info('Fetching status list from Bitrix24...');

        $statusResponse = $client->call('crm.status.list', [
            'filter' => ['ENTITY_ID' => 'STATUS'],
        ]);

        $statuses = $statusResponse['result'] ?? [];

        if (empty($statuses)) {
            $this->error('No statuses returned from Bitrix24.');
            return 1;
        }

        // Group by local stage_id: stage_id => ['name' => ..., 'statuses' => [...], 'bitrix_total' => 0]
        $grouped = [];

        $this->info('Fetching lead count per status from Bitrix24...');

        foreach ($statuses as $status) {
            $code = $status['STATUS_ID'] ?? null;
            $name = $status['NAME'] ?? $code;
            if (!$code) {
                continue;
            }

            // Lightweight count: Bitrix24 returns 'total' in the response
            // even when you only ask for 1 field, so we don't pull every row.
            $countResponse = $client->call('crm.lead.list', [
                'filter' => ['STATUS_ID' => $code],
                'select' => ['ID'],
            ]);

            $bitrixTotal = (int) ($countResponse['total'] ?? count($countResponse['result'] ?? []));

            $stageId = $importer->resolveStageId($code);
            if (!$stageId) {
                $stageId = 0; // unmapped bucket
            }

            if (!isset($grouped[$stageId])) {
                $grouped[$stageId] = [
                    'statuses'     => [],
                    'bitrix_total' => 0,
                ];
            }

            $grouped[$stageId]['statuses'][] = "{$name} ({$code})";
            $grouped[$stageId]['bitrix_total'] += $bitrixTotal;
        }

        // Local counts per stage_id
        $localCounts = Lead::selectRaw('stage_id, COUNT(*) as total')
            ->groupBy('stage_id')
            ->pluck('total', 'stage_id');

        $stageNames = Stage::pluck('name', 'id');

        $rows = [];
        $totalBitrix = 0;
        $totalLocal = 0;

        foreach ($grouped as $stageId => $data) {
            $stageName = $stageId === 0
                ? 'UNMAPPED (no matching local stage)'
                : ($stageNames[$stageId] ?? "Stage #{$stageId}");

            $localTotal = (int) ($localCounts[$stageId] ?? 0);
            $diff = $data['bitrix_total'] - $localTotal;

            $rows[] = [
                $stageId ?: '-',
                $stageName,
                implode(', ', $data['statuses']),
                $data['bitrix_total'],
                $localTotal,
                $diff,
            ];

            $totalBitrix += $data['bitrix_total'];
            $totalLocal  += $localTotal;
        }

        $this->newLine();
        $this->table(
            ['Stage ID', 'Stage Name', 'Bitrix24 Statuses', 'Bitrix Total', 'Local Total', 'Difference'],
            $rows
        );

        $this->newLine();
        $this->info("TOTAL — Bitrix: {$totalBitrix} | Local: {$totalLocal} | Difference: " . ($totalBitrix - $totalLocal));

        return 0;
    }
}