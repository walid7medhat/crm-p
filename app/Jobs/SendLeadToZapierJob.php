<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\Zapier\ZapierLeadWebhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/** POSTs one OIA lead (id, phone, email, stage) to the Zapier lead webhook. */
class SendLeadToZapierJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $leadId)
    {
    }

    public function handle(): void
    {
        // Brand / branch scopes depend on the request; the queue has none — load the row directly.
        $lead = Lead::withoutGlobalScopes()->find($this->leadId);

        // Re-check at run time: the lead may have moved on, been merged, or already been sent.
        if (! $lead || ! ZapierLeadWebhook::shouldSend($lead)) {
            return;
        }

        ZapierLeadWebhook::send($lead);
    }

    public function failed(Throwable $e): void
    {
        Log::warning('SendLeadToZapierJob failed', ['lead_id' => $this->leadId, 'error' => $e->getMessage()]);
    }
}
