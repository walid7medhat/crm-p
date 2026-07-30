<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lead;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;

class SyncLeadStagesFromBitrix extends Command
{
    protected $signature = 'leads:sync-stages';
    protected $description = 'Sync leads stage from Bitrix';

    private const STATUS_NAME_SYNONYMS = [
        'not qualified' => 'unqualified',
        'non qualified' => 'unqualified',
        'unqualified' => 'unqualified',
        'unqualified lead' => 'unqualified',
        'disqualified' => 'unqualified',
        'junk' => 'unqualified',
        'spam' => 'unqualified',
        'declined' => 'unqualified',
        'rejected' => 'unqualified',
        'job seeker' => 'unqualified',

        'in process' => 'contacted',
        'in progress' => 'contacted',
        'processing' => 'contacted',
        'follow up' => 'contacted',
        'follow-up' => 'contacted',

        'processed' => 'converted',
        'closed won' => 'converted',
        'won' => 'converted',
        'success' => 'converted',
        'completed' => 'converted',

        'closed lost' => 'lost',
        'failed' => 'lost',
        'dead' => 'lost',

        'fresh' => 'new',

        'future prospect' => 'qualified',
    ];

    public function handle(Bitrix24Client $client)
    {
        $this->info('🚀 Start syncing...');

        $importer = new Bitrix24LeadImporter(1); // 👈 مهم

        $stageMap = [
            'qualified'    => 6,
            'converted'    => 8,
            'unqualified'  => 11,
            'contacted'    => 5,
            'lost'         => 2,
            'new'          => 3,
        ];

        Lead::where('stage_id', 3)
            ->whereNotNull('bitrix24_id')
            ->chunkById(100, function ($leads) use ($client, $stageMap, $importer) {

                foreach ($leads as $lead) {

                    try {
                        $response = $client->call('crm.lead.get', [
                            'id' => $lead->bitrix24_id
                        ]);

                        $b24Lead = $response['result'] ?? null;
                        if (!$b24Lead) continue;

                        // ✅ 👇 هنا بقى الصح
                        $statusName = strtolower(
                            $importer->statusName($b24Lead['STATUS_ID'] ?? '') ?? ''
                        );

                        if (!$statusName) {
                            $this->warn("No status name for lead {$lead->id}");
                            continue;
                        }

                        $normalized = null;

                        foreach (self::STATUS_NAME_SYNONYMS as $key => $value) {
                            if (str_contains($statusName, $key)) {
                                $normalized = $value;
                                break;
                            }
                        }

                        if (!$normalized) {
                            $this->warn("No match: {$statusName}");
                            continue;
                        }

                        $newStageId = $stageMap[$normalized] ?? null;
                        if (!$newStageId) continue;

                        if ($lead->stage_id != $newStageId) {

                            $lead->update([
                                'stage_id' => $newStageId
                            ]);

                            $this->line("Lead {$lead->id}: {$statusName} → {$normalized} → {$newStageId}");
                        }

                    } catch (\Throwable $e) {
                        \Log::error("Lead {$lead->id}: " . $e->getMessage());
                    }
                }
            });

        $this->info('🎉 Done!');
    }
}