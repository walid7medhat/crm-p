<?php

namespace App\Jobs;

use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        $client = new Bitrix24Client();
        $importer = new Bitrix24LeadImporter($client, $this->userId);

        $cursor = $this->start;

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

            // move cursor
            if (empty($page['next'])) {
                break;
            }

            $cursor = $page['next'];
        }
    }
}