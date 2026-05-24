<?php

namespace App\Jobs;

use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBitrixLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;

    public function __construct(
        public array $b24,
        public int $userId
    ) {}

    public function handle(): void
    {
        try {
            $client   = new Bitrix24Client();
            $importer = new Bitrix24LeadImporter($client, $this->userId);

            $importer->importOne($this->b24);

        } catch (\Throwable $e) {
            Log::error('Process lead failed', [
                'lead' => $this->b24['ID'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}