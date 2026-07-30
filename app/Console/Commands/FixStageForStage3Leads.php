<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lead;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;

class FixStageForStage3Leads extends Command
{
    protected $signature = 'leads:fix-stage3';
    protected $description = 'Fix stage_id for leads where stage_id = 3 using Bitrix';

    public function handle(Bitrix24Client $client, Bitrix24LeadImporter $importer)
    {
        $this->info('🚀 Start fixing leads...');

        Lead::where('stage_id', 3)
            ->whereNotNull('bitrix24_id')
            ->chunkById(100, function ($leads) use ($client, $importer) {

                foreach ($leads as $lead) {

                    try {
                        $response = $client->call('crm.lead.get', [
                            'id' => $lead->bitrix24_id
                        ]);

                        $b24Lead = $response['result'] ?? null;

                        if (!$b24Lead) {
                            continue;
                        }

                        $statusId = $b24Lead['STATUS_ID'] ?? null;

                        if (!$statusId) {
                            continue;
                        }

                        // 🔥 أهم حاجة: نفس logic بتاعك
                        $newStageId = $importer->resolveStageId($statusId);

                        if ($newStageId && $newStageId != $lead->stage_id) {

                            $lead->update([
                                'stage_id' => $newStageId
                            ]);

                            $this->info("Updated Lead {$lead->id} → {$newStageId}");
                        }

                    } catch (\Throwable $e) {
                        \Log::error("Lead {$lead->id} failed: " . $e->getMessage());
                    }
                }
            });

        $this->info('🎉 Done!');
    }
}