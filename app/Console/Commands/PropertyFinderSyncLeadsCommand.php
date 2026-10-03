<?php

namespace App\Console\Commands;

use App\Services\PropertyFinder\PropertyFinderClient;
use App\Services\PropertyFinder\PropertyFinderLeadImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Safety net for missed webhooks: pulls recent leads from GET /v1/leads and imports
 * any the CRM doesn't have yet (existing ones get their PF details refreshed).
 * Scheduled every 10 minutes. Never goes back before go-live
 * (PropertyFinderLeadImporter::importFrom()), so old PF leads are not imported.
 */
class PropertyFinderSyncLeadsCommand extends Command
{
    protected $signature = 'propertyfinder:sync-leads {--minutes=60 : Look back this many minutes}';

    protected $description = 'Import recent Property Finder leads (fallback for missed webhooks)';

    public function handle(PropertyFinderClient $client, PropertyFinderLeadImporter $importer): int
    {
        if (! PropertyFinderClient::isConfigured()) {
            $this->line('Property Finder not configured (PF_API_KEY / PF_API_SECRET) — skipping.');

            return self::SUCCESS;
        }

        // PF rejects createdAtFrom older than 3 months; never look before go-live (no old leads).
        $minutes = min(max(1, (int) $this->option('minutes')), 89 * 24 * 60);
        $from = now()->utc()->subMinutes($minutes)
            ->max(PropertyFinderLeadImporter::importFrom()->utc())
            ->format('Y-m-d\TH:i:s\Z');

        $created = $refreshed = $failed = 0;
        $page = 1;

        do {
            $response = $client->leads(['createdAtFrom' => $from, 'page' => $page, 'perPage' => 50]);

            foreach ($response['data'] ?? [] as $pfLead) {
                try {
                    $result = $importer->import($pfLead, 'sync');
                    if (! isset($result['skipped'])) {
                        $result['created'] ? $created++ : $refreshed++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    Log::channel('propertyfinder')->error('PF sync: lead import failed', [
                        'pf_lead_id' => $pfLead['id'] ?? null, 'error' => $e->getMessage(),
                    ]);
                }
            }

            $page = $response['pagination']['nextPage'] ?? null;
        } while ($page);

        $summary = "Property Finder sync (since {$from}): {$created} created, {$refreshed} refreshed, {$failed} failed";
        $this->info($summary);
        if ($created || $failed) {
            Log::channel('propertyfinder')->info($summary);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
