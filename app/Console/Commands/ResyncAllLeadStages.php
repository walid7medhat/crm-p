<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lead;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;

class ResyncAllLeadStages extends Command
{
    protected $signature = 'leads:resync-stages
        {--only-stage= : Only resync leads currently on this local stage_id (optional, faster)}';

    protected $description = 'Re-fetch STATUS_ID for all leads from Bitrix24 and correct their local stage_id using the fixed resolver';

    public function handle(Bitrix24Client $client)
    {
        $importer = new Bitrix24LeadImporter($client, 1);
        $onlyStage = $this->option('only-stage');

        $query = Lead::whereNotNull('bitrix24_id');
        if ($onlyStage !== null) {
            $query->where('stage_id', (int) $onlyStage);
            $this->info("Resyncing leads currently on stage_id {$onlyStage} only.");
        }

        $total = $query->count();
        $this->info("Found {$total} leads to check.");

        $checked = 0;
        $updated = 0;
        $errors  = 0;
        $batchSize = 50; // crm.lead.list max per call

        $query->orderBy('id')->chunkById($batchSize, function ($leads) use (
            $client, $importer, &$checked, &$updated, &$errors, $total
        ) {
            $idMap = $leads->keyBy('bitrix24_id');
            $bitrixIds = $idMap->keys()->all();

            try {
                $response = $client->call('crm.lead.list', [
                    'filter' => ['ID' => $bitrixIds],
                    'select' => ['ID', 'STATUS_ID'],
                ]);

                foreach ($response['result'] ?? [] as $b24Lead) {
                    $checked++;
                    $b24Id = (int) ($b24Lead['ID'] ?? 0);
                    $lead  = $idMap->get($b24Id);
                    if (!$lead) {
                        continue;
                    }

                    $statusId = $b24Lead['STATUS_ID'] ?? null;
                    if (!$statusId) {
                        continue;
                    }

                    $newStageId = $importer->resolveStageId($statusId);

                    if ($newStageId && (int) $lead->stage_id !== $newStageId) {
                        $oldStage = $lead->stage_id;
                        $lead->update(['stage_id' => $newStageId]);
                        $updated++;
                        $this->line("Lead {$lead->id} (bitrix24_id={$b24Id}): stage {$oldStage} -> {$newStageId} (status={$statusId})");
                    }
                }
            } catch (\Throwable $e) {
                $errors++;
                \Log::error('Bitrix24 stage resync batch failed: ' . $e->getMessage());
            }

            if ($checked % 1000 === 0 || $checked >= $total) {
                $this->info("--- Progress: {$checked}/{$total} | updated: {$updated} | errors: {$errors} ---");
            }

            usleep(200000); // 0.2s pause to respect Bitrix24 rate limits
        });

        $this->info("Done! checked={$checked} updated={$updated} errors={$errors}");
        return 0;
    }
}