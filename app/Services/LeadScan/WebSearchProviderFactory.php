<?php

namespace App\Services\LeadScan;

use App\Services\LeadScan\Contracts\WebSearchProvider;

class WebSearchProviderFactory
{
    public function make(): WebSearchProvider
    {
        $name = strtolower(trim((string) config('lead_scan.provider')));
        $key = trim((string) config('lead_scan.api_key'));

        if ($name === 'brave' && $key !== '') {
            return new BraveWebSearchProvider(
                $key,
                (string) config('lead_scan.endpoint'),
                (int) config('lead_scan.timeout', 12),
                (int) config('lead_scan.results_per_query', 8),
            );
        }

        return new UnconfiguredWebSearchProvider();
    }
}
