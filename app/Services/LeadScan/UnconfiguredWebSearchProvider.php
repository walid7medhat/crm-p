<?php

namespace App\Services\LeadScan;

use App\Services\LeadScan\Contracts\WebSearchProvider;

class UnconfiguredWebSearchProvider implements WebSearchProvider
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'unconfigured';
    }

    public function search(string $query): array
    {
        throw new LeadScanException('unconfigured', 'Web search is not configured.');
    }
}
