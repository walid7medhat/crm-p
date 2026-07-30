<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Lead;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;

class CheckQualifiedLeads extends Command
{
    protected $signature = 'leads:check-qualified
        {--fix : Import missing leads and correct stage_id for existing ones}';

    protected $description = 'Fetch Qualified/Future-Prospected leads from Bitrix24 (UC_NLMQTU, UC_ADTE25) and verify they exist locally on stage_id 6';

    // Bitrix24 STATUS_ID codes that represent "Qualified"
    private const STATUS_IDS = ['UC_NLMQTU', 'UC_ADTE25'];

    // Local stage_id that all these leads must be on
    private const TARGET_STAGE_ID = 6;

    public function handle(Bitrix24Client $client)
    {
        $fix = $this->option('fix');
        $importer = new Bitrix24LeadImporter($client, 1);

        $this->info('📥 Fetching leads from Bitrix24 (Qualified + Future Prospected)...');

        // 1) Fetch all leads whose STATUS_ID is in the list (IN filter, paginated)
        $b24Leads = [];
        $start = 0;
        do {
            $response = $client->call('crm.lead.list', [
                'filter' => ['STATUS_ID' => self::STATUS_IDS],
                'select' => ['ID', 'STATUS_ID', 'TITLE', 'NAME', 'LAST_NAME'],
                'start'  => $start,
            ]);
            $result = $response['result'] ?? [];
            $b24Leads = array_merge($b24Leads, $result);
            $start = $response['next'] ?? null;
        } while ($start !== null);

        $total = count($b24Leads);
        $this->info("Bitrix24 has {$total} leads with status Qualified/Future Prospected.");

        if ($total === 0) {
            $this->info('Nothing to do.');
            return 0;
        }

        // 2) Fetch locally existing leads (bitrix24_id => Lead)
        $bitrixIds = array_map(fn ($l) => (int) $l['ID'], $b24Leads);
        $localLeads = Lead::whereIn('bitrix24_id', $bitrixIds)
            ->get(['id', 'bitrix24_id', 'stage_id', 'lead_name'])
            ->keyBy(fn ($l) => (int) $l->bitrix24_id);

        // 3) Classify each lead
        $missing = [];       // not found locally at all
        $wrongStage = [];    // exists, but not on stage_id 6
        $correct = 0;        // exists and on the correct stage

        foreach ($b24Leads as $b24Lead) {
            $id = (int) $b24Lead['ID'];
            $name = trim(($b24Lead['NAME'] ?? '') . ' ' . ($b24Lead['LAST_NAME'] ?? '')) ?: ($b24Lead['TITLE'] ?? '');
            $statusId = $b24Lead['STATUS_ID'] ?? '';

            if (!$localLeads->has($id)) {
                $missing[] = ['id' => $id, 'name' => $name, 'status' => $statusId];
                continue;
            }

            $local = $localLeads[$id];
            if ((int) $local->stage_id !== self::TARGET_STAGE_ID) {
                $wrongStage[] = [
                    'id' => $id,
                    'local_id' => $local->id,
                    'name' => $name,
                    'status' => $statusId,
                    'current_stage' => $local->stage_id,
                ];
            } else {
                $correct++;
            }
        }

        // 4) Print summary
        $this->newLine();
        $this->info("✅ Found locally and correctly on stage_id 6: {$correct}");
        $this->info('⚠️  Found locally but on the wrong stage: ' . count($wrongStage));
        $this->info('❌ Completely missing locally: ' . count($missing));

        if (!empty($wrongStage)) {
            $this->newLine();
            $this->line('Leads on the wrong stage:');
            $this->table(
                ['Local ID', 'Bitrix ID', 'Name', 'Bitrix Status', 'Current stage_id'],
                array_map(fn ($w) => [$w['local_id'], $w['id'], $w['name'], $w['status'], $w['current_stage']], $wrongStage)
            );
        }

        if (!empty($missing)) {
            $this->newLine();
            $this->line('Completely missing leads:');
            $this->table(
                ['Bitrix ID', 'Name', 'Bitrix Status'],
                array_map(fn ($m) => [$m['id'], $m['name'], $m['status']], $missing)
            );
        }

        if (!$fix) {
            $this->newLine();
            $this->comment('Run the command with --fix to correct the wrong stages and import the missing leads.');
            return 0;
        }

        // === FIX MODE ===
        $this->newLine();
        $this->info('🔧 Fixing...');

        // a) Correct the stage for existing leads that are wrong
        foreach ($wrongStage as $w) {
            Lead::whereKey($w['local_id'])->update(['stage_id' => self::TARGET_STAGE_ID]);
            $this->line("Fixed lead {$w['local_id']} (bitrix24_id={$w['id']}) -> stage_id 6");
        }

        // b) Import the missing leads (importOne resolves stage_id automatically from STATUS_ID)
        if (!empty($missing)) {
            $this->info('Importing ' . count($missing) . ' missing leads...');
            $missingIds = array_column($missing, 'id');

            foreach (array_chunk($missingIds, 50) as $chunk) {
                $response = $client->call('crm.lead.list', [
                    'filter' => ['ID' => $chunk],
                ]);
                foreach ($response['result'] ?? [] as $b24Lead) {
                    try {
                        $result = $importer->importOne($b24Lead);
                        $newLead = $result['lead'];
                        // Extra safety: force stage_id 6 even if resolveStageId disagreed
                        if ((int) $newLead->stage_id !== self::TARGET_STAGE_ID) {
                            $newLead->update(['stage_id' => self::TARGET_STAGE_ID]);
                        }
                        $this->line("Imported lead (bitrix24_id={$b24Lead['ID']}) -> local id {$newLead->id}");
                    } catch (\Throwable $e) {
                        \Log::error('Import failed for bitrix24_id ' . $b24Lead['ID'] . ': ' . $e->getMessage());
                        $this->error("Failed to import bitrix24_id={$b24Lead['ID']}: " . $e->getMessage());
                    }
                }
            }
        }

        $this->info('🎉 Done!');
        return 0;
    }
}