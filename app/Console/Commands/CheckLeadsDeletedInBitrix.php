<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\Bitrix24\Bitrix24Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckLeadsDeletedInBitrix extends Command
{
    protected $signature = 'leads:check-deleted-in-bitrix
        {--delete : Actually delete local leads confirmed removed from Bitrix24 (default: report only)}';

    protected $description = 'Find leads that exist locally but no longer exist in Bitrix24 (deleted there)';

    private const BATCH_SIZE = 50;

    public function handle(Bitrix24Client $client): int
    {
        $delete = (bool) $this->option('delete');

        $total = Lead::whereNotNull('bitrix24_id')->count();
        $this->info("Checking {$total} leads with a bitrix24_id against Bitrix24...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $deletedLeads = [];

        Lead::query()
            ->select('id', 'bitrix24_id', 'lead_name')
            ->whereNotNull('bitrix24_id')
            ->orderBy('id')
            ->chunkById(self::BATCH_SIZE, function ($leads) use ($client, $bar, &$deletedLeads) {
                $idMap = $leads->keyBy('bitrix24_id');
                $bitrixIds = $idMap->keys()->all();

                try {
                    $response = $client->call('crm.lead.list', [
                        'filter' => ['ID' => $bitrixIds],
                        'select' => ['ID'],
                    ]);

                    $existingRemoteIds = collect($response['result'] ?? [])
                        ->pluck('ID')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    foreach ($idMap as $bitrixId => $lead) {
                        if (! in_array((int) $bitrixId, $existingRemoteIds, true)) {
                            $deletedLeads[] = $lead;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('leads:check-deleted-in-bitrix batch failed: ' . $e->getMessage());
                }

                $bar->advance($leads->count());
            });

        $bar->finish();
        $this->newLine(2);

        if (empty($deletedLeads)) {
            $this->info('No leads found that were deleted from Bitrix24.');
            return self::SUCCESS;
        }

        $this->warn(count($deletedLeads) . ' lead(s) exist locally but are no longer in Bitrix24:');

        foreach ($deletedLeads as $lead) {
            $this->line("  - local id {$lead->id} | bitrix24_id {$lead->bitrix24_id} | {$lead->lead_name}");

            Log::channel('bitrix_deleted')->warning('Lead deleted in Bitrix24 but still local', [
                'local_id' => $lead->id,
                'bitrix24_id' => $lead->bitrix24_id,
                'lead_name' => $lead->lead_name,
            ]);
        }

        if ($delete) {
            $ids = collect($deletedLeads)->pluck('id')->all();
            Lead::whereIn('id', $ids)->delete();
            $this->warn(count($ids) . ' local lead(s) deleted.');
        } else {
            $this->info('Dry run — no local leads were deleted. Re-run with --delete to remove them.');
        }

        return self::SUCCESS;
    }
}
