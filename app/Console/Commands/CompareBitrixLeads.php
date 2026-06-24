<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Lead;

class CompareBitrixLeads extends Command
{
    protected $signature = 'bitrix:compare-leads';
    protected $description = 'Compare Bitrix leads with local DB and log missing ones';

    public function handle()
    {
        $this->info('Fetching leads from Bitrix24...');

        $bitrixWebhook = config('bitrix24.webhook_url');

        // 📌 Local IDs
        $localIds = Lead::whereNotNull('bitrix24_id')
            ->pluck('bitrix24_id')
            ->toArray();

        $localIds = array_flip($localIds);

        $start = 0;
        $missingCount = 0;

        do {
            $response = Http::get($bitrixWebhook . 'crm.lead.list', [
                'start' => $start,
                'select' => ['ID', 'TITLE'],
            ]);

            $data = $response->json();

            if (!isset($data['result'])) {
                $this->error('Error fetching Bitrix data');
                return Command::FAILURE;
            }

            foreach ($data['result'] as $lead) {
                if (!isset($localIds[$lead['ID']])) {

                    $missingCount++;

                    Log::channel('bitrix_missing')->info('Lead Missing', [
                        'bitrix_id' => $lead['ID'],
                        'title' => $lead['TITLE'] ?? null,
                    ]);
                }
            }

            $start = $data['next'] ?? null;

        } while ($start);

        // ✅ Final summary log
        Log::channel('bitrix_missing')->info('Missing Leads Count: ' . $missingCount);

        $this->info('Missing leads: ' . $missingCount);
        $this->info('Logged to storage/logs/bitrix_missing.log');

        return Command::SUCCESS;
    }
}