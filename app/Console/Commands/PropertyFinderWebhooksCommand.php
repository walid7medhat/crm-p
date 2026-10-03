<?php

namespace App\Console\Commands;

use App\Services\PropertyFinder\PropertyFinderClient;
use App\Services\PropertyFinder\PropertyFinderLeadImporter;
use Illuminate\Console\Command;

/**
 * Manage the Property Finder webhook subscriptions:
 *   php artisan propertyfinder:webhooks list
 *   php artisan propertyfinder:webhooks subscribe [--url=https://crm.example.com/api/propertyfinder/webhook]
 *   php artisan propertyfinder:webhooks unsubscribe
 */
class PropertyFinderWebhooksCommand extends Command
{
    protected $signature = 'propertyfinder:webhooks
        {action=list : list | subscribe | unsubscribe}
        {--url= : Callback URL (default: PF_WEBHOOK_URL or APP_URL/api/propertyfinder/webhook)}';

    protected $description = 'List / subscribe / unsubscribe Property Finder lead webhooks';

    public function handle(PropertyFinderClient $client): int
    {
        if (! PropertyFinderClient::isConfigured()) {
            $this->error('Set PF_API_KEY and PF_API_SECRET in .env first.');

            return self::FAILURE;
        }

        return match ($this->argument('action')) {
            'list' => $this->list($client),
            'subscribe' => $this->subscribe($client),
            'unsubscribe' => $this->unsubscribe($client),
            default => $this->invalidAction(),
        };
    }

    private function list(PropertyFinderClient $client): int
    {
        $this->line(json_encode($client->listWebhooks(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function subscribe(PropertyFinderClient $client): int
    {
        $url = $this->option('url')
            ?: config('propertyfinder.webhook_url')
            ?: rtrim(config('app.url'), '/') . '/api/propertyfinder/webhook';

        if (! str_starts_with($url, 'https://')) {
            $this->warn("Callback URL is not HTTPS ({$url}) — Property Finder needs a public HTTPS URL.");
        }

        $secret = config('propertyfinder.webhook_secret');
        if (blank($secret)) {
            $this->warn('PF_WEBHOOK_SECRET is empty — deliveries will not be signature-checked.');
        }

        // Fix the go-live moment now (if not set yet) — leads created before it are never imported.
        $this->line('Importing PF leads created from: ' . PropertyFinderLeadImporter::importFrom()->format('Y-m-d h:i A') . ' (UAE time)');

        $failed = false;
        foreach (config('propertyfinder.events') as $eventId) {
            $response = $client->subscribeWebhook($eventId, $url, $secret);
            if ($response->status() === 409) {
                $this->line("  {$eventId}: already subscribed (run unsubscribe first to change URL/secret)");
            } elseif ($response->successful()) {
                $this->info("  {$eventId}: subscribed → {$url}");
            } else {
                $failed = true;
                $this->error("  {$eventId}: failed ({$response->status()}) {$response->body()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function unsubscribe(PropertyFinderClient $client): int
    {
        foreach (config('propertyfinder.events') as $eventId) {
            $response = $client->deleteWebhook($eventId);
            $this->line("  {$eventId}: " . ($response->successful() ? 'removed' : "HTTP {$response->status()}"));
        }

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->error('Action must be one of: list, subscribe, unsubscribe');

        return self::INVALID;
    }
}
