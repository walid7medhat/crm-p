<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lead;
use App\Services\Bitrix24\Bitrix24Client;

class SyncLeadStagesFromBitrix extends Command
{
    protected $signature = 'leads:sync-stages';
    protected $description = 'Sync leads stage from Bitrix using STATUS_NAME';

    // ✅ synonyms
    private const STATUS_NAME_SYNONYMS = [
        'not qualified'        => 'unqualified',
        'non qualified'        => 'unqualified',
        'unqualified'          => 'unqualified',
        'unqualified lead'     => 'unqualified',
        'disqualified'         => 'unqualified',
        'junk'                 => 'unqualified',
        'spam'                 => 'unqualified',
        'declined'             => 'unqualified',
        'rejected'             => 'unqualified',
        'jop seeker'           => 'unqualified',
        'job seeker'           => 'unqualified',

        'in process'           => 'contacted',
        'in progress'          => 'contacted',
        'processing'           => 'contacted',
        'follow up'            => 'contacted',
        'follow-up'            => 'contacted',
        'follow-up / contacted'=> 'contacted',

        'processed'            => 'converted',
        'closed won'           => 'converted',
        'closed-won'           => 'converted',
        'won'                  => 'converted',
        'success'              => 'converted',
        'completed'            => 'converted',

        'closed lost'          => 'lost',
        'closed-lost'          => 'lost',
        'failed'               => 'lost',
        'dead'                 => 'lost',

        'fresh'                => 'new',

        'successfull'          => 'qualified',
        'future prospect'      => 'qualified',
        'future prospected'    => 'qualified',
    ];

    public function handle(Bitrix24Client $client)
    {
        $this->info('🚀 Start syncing leads...');

        // ✅ mapping النهائي
        $stageMap = [
            'qualified'    => 6,
            'converted'    => 8,
            'unqualified'  => 11,
            'contacted'    => 5,
            'lost'         => 2,
            'new'=>3
        ];

        Lead::where('stage_id', 3)
            ->whereNotNull('bitrix24_id')
            ->chunkById(100, function ($leads) use ($client, $stageMap) {

                foreach ($leads as $lead) {

                    try {
                        $response = $client->call('crm.lead.get', [
                            'id' => $lead->bitrix24_id
                        ]);

                        $b24Lead = $response['result'] ?? null;
                        if (!$b24Lead) continue;

                        // ✅ اسم الاستيج
                        $statusName = strtolower($b24Lead['STATUS_NAME'] ?? '');
                        if (!$statusName) continue;

                        // ✅ normalize
                        $normalized = null;

                        foreach (self::STATUS_NAME_SYNONYMS as $key => $value) {
                            if (str_contains($statusName, $key)) {
                                $normalized = $value;
                                break;
                            }
                        }

                        if (!$normalized) continue;

                        // ✅ تحويل ل stage_id
                        $newStageId = $stageMap[$normalized] ?? null;
                        if (!$newStageId) continue;

                        // ✅ update
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