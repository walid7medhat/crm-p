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

   
public function handle(Bitrix24Client $client)
{
    $this->info('🚀 Start syncing...');

    $importer = new Bitrix24LeadImporter($client, 1);

    $leadIds = Lead::where('stage_id', 3)
        ->whereNotNull('bitrix24_id')
        ->pluck('id');

    $total = $leadIds->count();
    $this->info("Found {$total} leads to check.");

    $checked = 0;
    $updated = 0;
    $noData = 0;
    $noStatus = 0;
    $noStage = 0;
    $errors = 0;

    foreach ($leadIds->chunk(100) as $chunk) {

        $leads = Lead::whereIn('id', $chunk)->get();

        foreach ($leads as $lead) {
            $checked++;

            try {
                $response = $client->call('crm.lead.get', [
                    'id' => $lead->bitrix24_id,
                ]);

                $b24Lead = $response['result'] ?? null;
                if (!$b24Lead) {
                    $noData++;
                    $this->warn("Lead {$lead->id} (b24 {$lead->bitrix24_id}): no result from crm.lead.get");
                    continue;
                }

                $statusId = $b24Lead['STATUS_ID'] ?? null;
                if (!$statusId) {
                    $noStatus++;
                    $this->warn("Lead {$lead->id}: no STATUS_ID in response");
                    continue;
                }

                $newStageId = $importer->resolveStageId($statusId);
                $statusName = $importer->statusName($statusId);

                // اطبع كل حالة، حتى لو مفيش تغيير - عشان تشوف اللي بيحصل فعلاً
                $this->line("Lead {$lead->id}: STATUS_ID={$statusId} name='{$statusName}' → resolved stage_id=" . ($newStageId ?? 'NULL') . " (current stage_id={$lead->stage_id})");

                if (!$newStageId) {
                    $noStage++;
                    continue;
                }

                if ($lead->stage_id != $newStageId) {
                    $lead->update(['stage_id' => $newStageId]);
                    $updated++;
                }

            } catch (\Throwable $e) {
                $errors++;
                $this->error("Lead {$lead->id}: " . $e->getMessage());
                \Log::error("Lead {$lead->id}: " . $e->getMessage());
            }
        }

        $this->info("--- Progress: {$checked}/{$total} | updated: {$updated} | errors: {$errors} ---");
    }

    $this->info("🎉 Done! checked={$checked} updated={$updated} noData={$noData} noStatus={$noStatus} noStage={$noStage} errors={$errors}");
}
}