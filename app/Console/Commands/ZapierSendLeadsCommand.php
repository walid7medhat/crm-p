<?php

namespace App\Console\Commands;

use App\Jobs\SendLeadToZapierJob;
use App\Models\Lead;
use App\Services\Zapier\ZapierLeadWebhook;
use Illuminate\Console\Command;
use Throwable;

/**
 * Backfill: sends existing OIA leads already sitting in a Zapier stage (Qualified /
 * Converted) that were not yet sent for that stage. Safe to re-run.
 */
class ZapierSendLeadsCommand extends Command
{
    protected $signature = 'zapier:send-leads
        {--dry-run : Only count the leads that would be sent}
        {--limit=0 : Send at most this many leads}
        {--sync : Send now from this process instead of queueing}
        {--sleep=250 : Milliseconds to wait between leads with --sync}';

    protected $description = 'Send existing OIA leads in Qualified/Converted stages to the Zapier lead webhook';

    public function handle(): int
    {
        if (! ZapierLeadWebhook::isEnabled()) {
            $this->error('ZAPIER_LEAD_WEBHOOK_URL is not set.');

            return self::FAILURE;
        }

        $stages = ZapierLeadWebhook::stages();
        if ($stages === []) {
            $this->error('No lead stages match services.zapier_lead_webhook.stages.');

            return self::FAILURE;
        }

        $query = Lead::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereNull('merged_into_lead_id')
            ->where(fn ($q) => $q->whereNull('brand_key')->orWhere('brand_key', 'oia'))
            ->whereIn('stage_id', array_keys($stages))
            ->where(fn ($q) => $q->whereNull('zapier_sent_stage_id')->orWhereColumn('zapier_sent_stage_id', '!=', 'stage_id'))
            ->orderBy('id');

        $limit = max(0, (int) $this->option('limit'));
        $total = (clone $query)->count();
        $toSend = $limit > 0 ? min($limit, $total) : $total;

        $this->info("Stages: ".implode(', ', $stages)." — {$total} OIA lead(s) not yet sent, sending {$toSend}.");

        if ($this->option('dry-run') || $toSend === 0) {
            return self::SUCCESS;
        }

        $sync = (bool) $this->option('sync');
        $sleepUs = max(0, (int) $this->option('sleep')) * 1000;
        $done = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($toSend);

        $query->select('id')->chunkById(500, function ($rows) use (&$done, &$failed, $toSend, $sync, $sleepUs, $bar) {
            foreach ($rows as $row) {
                if ($done + $failed >= $toSend) {
                    return false;
                }

                if (! $sync) {
                    SendLeadToZapierJob::dispatch((int) $row->id);
                    $done++;
                } else {
                    try {
                        $lead = Lead::withoutGlobalScopes()->find($row->id);
                        if ($lead && ZapierLeadWebhook::shouldSend($lead)) {
                            ZapierLeadWebhook::send($lead);
                        }
                        $done++;
                    } catch (Throwable $e) {
                        $failed++;
                        $this->newLine();
                        $this->warn($e->getMessage());
                    }
                    usleep($sleepUs);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info($sync ? "Sent {$done}, failed {$failed}." : "Queued {$done} job(s) — make sure a queue worker is running.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
