<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Models\Lead;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;

class ResyncAllLeadStages extends Command
{
    protected $signature = 'leads:resync-stages
        {--only-stage= : Only resync leads currently on this local stage_id (optional, faster)}
        {--fresh : Ignore any saved progress and start over from the first lead}';

    protected $description = 'Re-fetch STATUS_ID for all leads from Bitrix24 and correct their local stage_id using the fixed resolver';

    /**
     * Cache key holding the last successfully-processed lead ID, so a
     * Ctrl+C / crash / terminal disconnect can resume instead of re-checking
     * everything from lead #1. Namespaced per --only-stage filter since that
     * changes which leads are in scope.
     */
    private function progressKey(?string $onlyStage): string
    {
        return 'leads:resync-stages:last_id:' . ($onlyStage !== null ? $onlyStage : 'all');
    }

    public function handle(Bitrix24Client $client)
    {
        $importer = new Bitrix24LeadImporter($client, 1);
        $onlyStage = $this->option('only-stage');
        $fresh = (bool) $this->option('fresh');

        $progressKey = $this->progressKey($onlyStage);

        if ($fresh) {
            Cache::forget($progressKey);
            $this->info('Starting fresh — cleared saved progress.');
        }

        $lastId = $fresh ? 0 : (int) Cache::get($progressKey, 0);

        $query = Lead::whereNotNull('bitrix24_id');
        if ($onlyStage !== null) {
            $query->where('stage_id', (int) $onlyStage);
            $this->info("Resyncing leads currently on stage_id {$onlyStage} only.");
        }

        $total = $query->count();

        if ($lastId > 0) {
            $remaining = (clone $query)->where('id', '>', $lastId)->count();
            $this->info("Resuming after lead ID {$lastId} — {$remaining} of {$total} leads remaining.");
            $query->where('id', '>', $lastId);
        } else {
            $this->info("Found {$total} leads to check.");
        }

        $checked = 0;
        $updated = 0;
        $errors  = 0;
        $batchSize = 50; // crm.lead.list max per call

        $query->orderBy('id')->chunkById($batchSize, function ($leads) use (
            $client, $importer, &$checked, &$updated, &$errors, $total, $progressKey
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

                    // Human-readable label for the Bitrix24 status code (e.g. "NEW" -> "New").
                    $statusName = $importer->statusName($statusId) ?? $statusId;

                    $newStageId = $importer->resolveStageId($statusId);

                    if ($newStageId && (int) $lead->stage_id !== $newStageId) {
                        $oldStage = $lead->stage_id;
                        $lead->update(['stage_id' => $newStageId]);
                        $updated++;
                        $this->line("Lead {$lead->id} (bitrix24_id={$b24Id}): stage {$oldStage} -> {$newStageId} (status={$statusId} \"{$statusName}\")");
                    } else {
                        $this->line("Lead {$lead->id} (bitrix24_id={$b24Id}): stage unchanged ({$lead->stage_id}) (status={$statusId} \"{$statusName}\")");
                    }
                }
            } catch (\Throwable $e) {
                $errors++;
                \Log::error('Bitrix24 stage resync batch failed: ' . $e->getMessage());
            }

            if ($checked % 1000 === 0 || $checked >= $total) {
                $this->info("--- Progress: {$checked}/{$total} | updated: {$updated} | errors: {$errors} ---");
            }

            // Save the highest lead ID we've finished this chunk, so a
            // Ctrl+C / crash right after this point resumes from here rather
            // than reprocessing from lead #1. chunkById iterates in ID order,
            // so the last item in $leads is the highest ID seen so far.
            $lastIdInChunk = $leads->last()?->id;
            if ($lastIdInChunk) {
                Cache::forever($progressKey, $lastIdInChunk);
            }

            usleep(200000); // 0.2s pause to respect Bitrix24 rate limits
        });

        // Full run completed — clear the saved checkpoint so the next
        // invocation (without --fresh) starts a new pass from the beginning.
        Cache::forget($progressKey);

        $this->info("Done! checked={$checked} updated={$updated} errors={$errors}");
        return 0;
    }
}