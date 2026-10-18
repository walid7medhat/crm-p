<?php

namespace App\Services\LeadScan\Contracts;

interface WebSearchProvider
{
    public function isConfigured(): bool;

    public function name(): string;

    /**
     * @return array<int, array{title: string, url: string, excerpt: string, published_at: ?string, source_type: string}>
     */
    public function search(string $query): array;
}
