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
    ini_set('memory_limit', '512M'); // مهم

    $importer = new Bitrix24LeadImporter($client, 1);
    $onlyStage = $this->option('only-stage');
    $fresh = (bool) $this->option('fresh');

    $progressKey = $this->progressKey($onlyStage);

    if ($fresh) {
        Cache::forget($progressKey);
        $this->info('Starting fresh — cleared saved progress.');
    }

    $lastId = $fresh ? 0 : (int) Cache::get($progressKey, 0);

    $query = Lead::query()
        ->select('id', 'stage_id', 'bitrix24_id') // 🔥 مهم جدًا
        ->whereNotNull('bitrix24_id');

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
    $batchSize = 50;

    $alreadyProcessed = $lastId > 0 ? ($total - $remaining) : 0;

    $bar = $this->output->createProgressBar($total);
    $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% updated:%message%");
    $bar->setMessage('0, errors:0');
    $bar->start();

    if ($alreadyProcessed > 0) {
        $bar->setProgress($alreadyProcessed);
    }

    $query->orderBy('id')->chunkById($batchSize, function ($leads) use (
        $client, $importer, &$checked, &$updated, &$errors, $progressKey, $bar
    ) {

        $idMap = $leads->keyBy('bitrix24_id');
        $bitrixIds = $idMap->keys()->all();

        try {
            $response = $client->call('crm.lead.list', [
                'filter' => ['ID' => $bitrixIds],
                'select' => ['ID', 'STATUS_ID'],
            ]);
            if (isset($response['error'])) {
                    $errors++;

                    \Log::error('Bitrix24 API error', [
                        'error' => $response['error'],
                        'description' => $response['error_description'] ?? null,
                        'bitrix_ids' => $bitrixIds,
                    ]);

                    return; // skip this batch
                }

            $updates = []; // 🔥 bulk update

            foreach ($response['result'] ?? [] as $b24Lead) {
                $checked++;

                $b24Id = (int) ($b24Lead['ID'] ?? 0);
                $lead  = $idMap->get($b24Id);

                if (!$lead) {
                        \Log::channel('bitrix_missing')->warning('Missing lead during resync', [
                            'bitrix24_id' => $b24Id,
                            'status_id' => $b24Lead['STATUS_ID'] ?? null,
                        ]);
                         try {
                            // 🔥 اعمل import لليد الناقص
                            $importer->importOne($b24Lead);
                        } catch (\Throwable $e) {
                            $errors++;

                            \Log::error('Import missing lead failed', [
                                'bitrix24_id' => $b24Id,
                                'error' => $e->getMessage(),
                            ]);
                        }

                        $bar->advance();
                        continue;
                    }

                $statusId = $b24Lead['STATUS_ID'] ?? null;
                if (!$statusId) {
                    $bar->advance();
                    continue;
                }

                $newStageId = $importer->resolveStageId($statusId);

                if ($newStageId && (int) $lead->stage_id !== $newStageId) {
                    $updates[] = [
                        'id' => $lead->id,
                        'stage_id' => $newStageId,
                    ];
                    $updated++;
                }

                $bar->advance();
            }

            // 🔥 bulk update مرة واحدة
            if (!empty($updates)) {
                $cases = [];
                $ids = [];

                foreach ($updates as $u) {
                    $id = (int) $u['id'];
                    $stage = (int) $u['stage_id'];

                    $cases[] = "WHEN {$id} THEN {$stage}";
                    $ids[] = $id;
                }

                $idsList = implode(',', $ids);
                $casesSql = implode(' ', $cases);

                \DB::statement("
                    UPDATE leads
                    SET stage_id = CASE id
                        {$casesSql}
                    END
                    WHERE id IN ({$idsList})
                ");
            }

        } catch (\Throwable $e) {
            $errors++;
            \Log::error('Bitrix24 batch failed: ' . $e->getMessage());
        }

        $bar->setMessage("{$updated}, errors:{$errors}");

        // 🔥 save progress
        $lastIdInChunk = $leads->last()?->id;
        if ($lastIdInChunk) {
            Cache::forever($progressKey, $lastIdInChunk);
        }

        // 🔥 تنظيف الذاكرة (مهم جدًا)
        unset($leads, $idMap, $response, $updates);
        gc_collect_cycles();

        // خففنا sleep
        usleep(50000);
    });

    $bar->finish();
    $this->newLine(2);

    Cache::forget($progressKey);

    $this->info("Done! checked={$checked} updated={$updated} errors={$errors}");

    return 0;
}
}