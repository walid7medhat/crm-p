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

        $bitrixWebhook =config('bitrix24.webhook_url');

        $start = 0;
        $bitrixLeads = [];

        // 🔁 Fetch all Bitrix leads
        do {
            $response = Http::get($bitrixWebhook . 'crm.lead.list', [
                'start' => $start,
                'select' => ['ID', 'TITLE'],
            ]);

            $data = $response->json();

            if (!isset($data['result'])) {
                $this->error('Error fetching Bitrix data');
                return;
            }

            $bitrixLeads = array_merge($bitrixLeads, $data['result']);
            $start = $data['next'] ?? null;

        } while ($start);

        $this->info('Total Bitrix leads: ' . count($bitrixLeads));

        // 📌 Local IDs
        $localIds = Lead::whereNotNull('bitrix24_id')
            ->pluck('bitrix24_id')
            ->toArray();

        // 🔍 Find missing
        $missing = [];

        foreach ($bitrixLeads as $lead) {
            if (!in_array($lead['ID'], $localIds)) {
                $missing[] = $lead;
            }
        }

        $this->info('Missing leads: ' . count($missing));

        // 🧾 Log to separate file
        Log::channel('bitrix_missing')->info('Missing Leads Count: ' . count($missing));

        foreach ($missing as $lead) {
            Log::channel('bitrix_missing')->info('Lead Missing', [
                'bitrix_id' => $lead['ID'],
                'title' => $lead['TITLE'] ?? null,
            ]);
        }

        $this->info('Logged to storage/logs/bitrix_missing.log');

        return Command::SUCCESS;
    }
}