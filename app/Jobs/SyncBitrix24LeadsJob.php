<?php

namespace App\Jobs;

use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use \App\Models\BitrixSyncState;
class SyncBitrix24LeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $start;
    public int $userId;
    public bool $skipExisting;

    public function __construct(int $userId, int $start = 0, bool $skipExisting = false)
    {
        $this->userId = $userId;
        $this->start = $start;
        $this->skipExisting = $skipExisting;
    }

   public function handle(): void
{
    $state = BitrixSyncState::firstOrCreate([
        'key' => 'global_sync'
    ]);

    $cursor = $state->cursor; // 👈 هنا السر

    $client = new Bitrix24Client();
    $importer = new Bitrix24LeadImporter($client, $this->userId);

    while (true) {

        $page = $client->listLeads($cursor);

        $b24Leads = $page['result'] ?? [];

        foreach ($b24Leads as $lead) {
            try {
                $importer->importOne($lead);
            } catch (\Throwable $e) {
                \Log::error('Bitrix sync error', [
                    'lead' => $lead['ID'] ?? null,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // 🔥 هنا بنحفظ آخر نقطة وصلنا لها
        $cursor = $page['next'] ?? null;

        $state->update([
            'cursor' => $cursor ?? $state->cursor
        ]);

        if (!$cursor) {
            break;
        }
    }
}
}