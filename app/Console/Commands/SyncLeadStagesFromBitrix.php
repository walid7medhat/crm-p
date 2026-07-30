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

        Lead::where('stage_id', 3)
            ->whereNotNull('bitrix24_id')
            ->chunkById(100, function ($leads) use ($client, $importer) {

                foreach ($leads as $lead) {
                    try {
                        $response = $client->call('crm.lead.get', [
                            'id' => $lead->bitrix24_id,
                        ]);

                        $b24Lead = $response['result'] ?? null;
                        if (!$b24Lead) {
                            $this->warn("Lead {$lead->id}: no data from Bitrix24");
                            continue;
                        }

                        $statusId = $b24Lead['STATUS_ID'] ?? null;
                        if (!$statusId) {
                            $this->warn("Lead {$lead->id}: no STATUS_ID");
                            continue;
                        }

                        // Use the importer's own (public) resolver — same logic
                        // used everywhere else in the sync, cached, and handles
                        // exact/substring/synonym matching for you.
                        $newStageId = $importer->resolveStageId($statusId);
                        $statusName = $importer->statusName($statusId);

                        if (!$newStageId) {
                            $this->warn("Lead {$lead->id}: could not resolve stage for status '{$statusName}'");
                            continue;
                        }

                        if ($lead->stage_id != $newStageId) {
                            $lead->update(['stage_id' => $newStageId]);
                            $this->line("Lead {$lead->id}: {$statusName} → stage {$newStageId}");
                        }

                    } catch (\Throwable $e) {
                        $this->error("Lead {$lead->id}: " . $e->getMessage());
                        \Log::error("Lead {$lead->id}: " . $e->getMessage());
                    }
                }
            });

        $this->info('🎉 Done!');
    }
}