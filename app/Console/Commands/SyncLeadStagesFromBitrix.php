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

    $importer = new Bitrix24LeadImporter($client, 1);

    // خد الـ IDs كلها الأول (snapshot) عشان التحديث مايأثرش على شرط where
    $leadIds = Lead::where('stage_id', 3)
        ->whereNotNull('bitrix24_id')
        ->pluck('id');

    $this->info("Found {$leadIds->count()} leads to check.");

    $bar = $this->output->createProgressBar($leadIds->count());

    foreach ($leadIds->chunk(100) as $chunk) {

        $leads = Lead::whereIn('id', $chunk)->get();

        foreach ($leads as $lead) {
            try {
                $response = $client->call('crm.lead.get', [
                    'id' => $lead->bitrix24_id,
                ]);

                $b24Lead = $response['result'] ?? null;
                if (!$b24Lead) {
                    $bar->advance();
                    continue;
                }

                $statusId = $b24Lead['STATUS_ID'] ?? null;
                if (!$statusId) {
                    $bar->advance();
                    continue;
                }

                $newStageId = $importer->resolveStageId($statusId);
                $statusName = $importer->statusName($statusId);

                if ($newStageId && $lead->stage_id != $newStageId) {
                    $lead->update(['stage_id' => $newStageId]);
                    $this->line("\nLead {$lead->id}: {$statusName} → stage {$newStageId}");
                }

            } catch (\Throwable $e) {
                \Log::error("Lead {$lead->id}: " . $e->getMessage());
            }

            $bar->advance();
        }
    }

    $bar->finish();
    $this->info("\n🎉 Done!");
}
}