<?php

namespace App\Services\Zapier;

use App\Jobs\SendLeadToZapierJob;
use App\Models\Lead;
use App\Models\Stage;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends OIA leads that enter a configured lead stage (default: Qualified, Converted)
 * to the Zapier catch hook in services.zapier_lead_webhook.url.
 * Payload: id, phone, email, stage.
 */
class ZapierLeadWebhook
{
    public static function isEnabled(): bool
    {
        return trim((string) config('services.zapier_lead_webhook.url')) !== '';
    }

    /** @return array<int, string> stage id => stage name */
    public static function stages(): array
    {
        return once(function () {
            $names = array_map('strtolower', (array) config('services.zapier_lead_webhook.stages', []));
            if ($names === []) {
                return [];
            }

            return Stage::query()
                ->where('stage_type', 'lead')
                ->whereIn(\DB::raw('LOWER(name)'), $names)
                ->pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])
                ->all();
        });
    }

    /** OIA = brand_key 'oia' or legacy NULL. Luxvant is never sent. */
    public static function isOia(Lead $lead): bool
    {
        return $lead->brand_key === null || $lead->brand_key === 'oia';
    }

    public static function shouldSend(Lead $lead): bool
    {
        return self::isEnabled()
            && self::isOia($lead)
            && $lead->merged_into_lead_id === null
            && $lead->deleted_at === null
            && array_key_exists((int) $lead->stage_id, self::stages())
            && (int) $lead->zapier_sent_stage_id !== (int) $lead->stage_id;
    }

    /** Called from the Lead model when a lead is created or its stage changes. */
    public static function queueIfEligible(Lead $lead): void
    {
        if (self::shouldSend($lead)) {
            SendLeadToZapierJob::dispatch((int) $lead->id)->afterCommit();
        }
    }

    public static function payload(Lead $lead): array
    {
        return [
            'id' => (int) $lead->id,
            'phone' => $lead->work_phone,
            'email' => $lead->email,
            'stage' => self::stages()[(int) $lead->stage_id] ?? null,
        ];
    }

    /** POSTs the lead and marks it sent for its current stage. Throws on failure (job retries). */
    public static function send(Lead $lead): void
    {
        $response = Http::timeout(15)
            ->acceptJson()
            ->post((string) config('services.zapier_lead_webhook.url'), self::payload($lead));

        if (! $response->successful()) {
            throw new RuntimeException(
                "Zapier webhook failed for lead {$lead->id}: HTTP {$response->status()} ".mb_substr($response->body(), 0, 300)
            );
        }

        $lead->forceFill([
            'zapier_sent_stage_id' => $lead->stage_id,
            'zapier_sent_at' => now(),
        ])->saveQuietly();
    }
}
