<?php

namespace App\Jobs;

use App\Models\PropertyFinderWebhookEvent;
use App\Services\PropertyFinder\PropertyFinderClient;
use App\Services\PropertyFinder\PropertyFinderLeadImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Turns one stored Property Finder webhook event (lead.created / lead.updated /
 * lead.assigned) into a CRM lead. Runs after the webhook has already answered PF.
 */
class ProcessPropertyFinderEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public int $eventRowId)
    {
        $this->onQueue(config('propertyfinder.queue', 'default'));
    }

    public function handle(PropertyFinderLeadImporter $importer): void
    {
        $event = PropertyFinderWebhookEvent::find($this->eventRowId);
        if (! $event || $event->status === 'processed') {
            return;
        }

        $pf = PropertyFinderLeadImporter::fromWebhookPayload($event->payload);

        // The API lead has more than the webhook (call recording, talk time, tags) —
        // use it when we can, otherwise the webhook payload alone is enough.
        if (PropertyFinderClient::isConfigured() && ! empty($pf['id'])) {
            try {
                $full = app(PropertyFinderClient::class)->getLead($pf['id']);
                if ($full) {
                    $pf = array_replace_recursive($pf, $full);
                }
            } catch (\Throwable $e) {
                Log::channel('propertyfinder')->warning('PF lead fetch failed, using webhook payload', [
                    'pf_lead_id' => $pf['id'], 'error' => $e->getMessage(),
                ]);
            }
        }

        $result = $importer->import($pf, 'webhook:' . $event->type);

        // skipped = a lead PF created before go-live (import_from) — not imported.
        $event->update([
            'status' => isset($result['skipped']) ? 'ignored' : 'processed',
            'lead_id' => $result['lead']?->id,
            'error' => $result['skipped'] ?? null,
            'processed_at' => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        PropertyFinderWebhookEvent::whereKey($this->eventRowId)->update([
            'status' => 'failed',
            'error' => mb_substr($e->getMessage(), 0, 2000),
        ]);
        Log::channel('propertyfinder')->error('PF event processing failed', [
            'event_row_id' => $this->eventRowId, 'error' => $e->getMessage(),
        ]);
    }
}
