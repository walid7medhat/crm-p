<?php

namespace App\Services\LeadScan;

class LeadScanReportBuilder
{
    /**
     * @param  array<string, mixed>  $lead
     * @param  array<int, array<string, mixed>>  $candidates
     * @param  array<int, string>  $providerErrors
     * @param  array{status: string, message: string}  $identity
     * @return array<string, mixed>
     */
    public function build(array $lead, array $candidates, array $identity, array $providerErrors, ?string $manualSearchUrl): array
    {
        $retrievedAt = now()->toIso8601String();
        $sections = [
            'identity' => $this->identitySection($lead, $candidates, $identity, $retrievedAt),
            'career' => $this->mentionedSection($lead, $candidates, 'position', 'Stored job title', $retrievedAt),
            'company' => $this->companySection($lead, $candidates, $retrievedAt),
            'presence' => $this->presenceSection($candidates, $retrievedAt),
            'findings' => $this->findings($candidates, $providerErrors, $retrievedAt),
        ];

        return [
            'identity_status' => $identity['status'],
            'identity_message' => $identity['message'],
            'manual_search_url' => $manualSearchUrl,
            'provider_errors' => array_values(array_unique($providerErrors)),
            'sections' => $sections,
            'comparison' => count($candidates) > 1 ? $this->comparison($candidates) : [],
            'ai' => [
                'status' => 'not_configured',
                'summary' => null,
            ],
            'limitation' => 'A matching name is not a confirmed identity. LinkedIn member data is not accessed; only public search results are shown.',
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<int, array<string, mixed>>  $allowedSources
     * @return array<string, mixed>
     */
    public function applyAiSummary(array $report, array $ai, array $allowedSources): array
    {
        $allowed = [];
        foreach ($allowedSources as $source) {
            $allowed[(string) ($source['url'] ?? '')] = true;
        }
        $cited = [];
        foreach ($ai['sources'] ?? [] as $url) {
            if (isset($allowed[(string) $url])) {
                $cited[] = (string) $url;
            }
        }
        $summary = trim((string) ($ai['summary'] ?? ''));
        if ($summary === '' || $cited === []) {
            $report['ai'] = ['status' => 'discarded', 'summary' => null];

            return $report;
        }

        $report['ai'] = [
            'status' => 'summary',
            'summary' => mb_substr($summary, 0, 600),
            'quality' => 'unverified',
            'label' => 'AI-generated summary of source material',
            'sources' => array_values(array_unique($cited)),
        ];

        return $report;
    }

    /**
     * @param  array<string, mixed>  $lead
     * @param  array<int, array<string, mixed>>  $candidates
     * @param  array{status: string, message: string}  $identity
     * @return array<int, array<string, mixed>>
     */
    private function identitySection(array $lead, array $candidates, array $identity, string $retrievedAt): array
    {
        if ($candidates === []) {
            return [[
                'label' => 'Potential professional identity',
                'value' => 'Not found in available sources',
                'quality' => 'not-found',
                'sources' => [],
            ]];
        }

        $items = [[
            'label' => 'Identity-match assessment',
            'value' => $identity['message'],
            'quality' => $this->identityQuality($identity['status']),
            'sources' => [],
        ]];

        foreach (array_slice($candidates, 0, 3) as $candidate) {
            $source = $this->source($candidate, $retrievedAt);
            $items[] = [
                'label' => 'Candidate page',
                'value' => $candidate['title'] ?: 'Untitled result',
                'quality' => $this->tierQuality($candidate['tier']),
                'note' => 'This is the public page title, not a confirmed name or current job.',
                'reasons' => $candidate['reasons'] ?? [],
                'uncertainties' => $candidate['uncertainties'] ?? [],
                'sources' => [$source],
            ];
        }

        if (($lead['name'] ?? '') === '') {
            $items[] = [
                'label' => 'Lead name',
                'value' => 'Not found in available sources',
                'quality' => 'not-found',
                'sources' => [],
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $lead
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    private function mentionedSection(array $lead, array $candidates, string $field, string $label, string $retrievedAt): array
    {
        $needle = strtolower(trim((string) ($lead[$field] ?? '')));
        if ($needle === '') {
            return [[
                'label' => $label,
                'value' => 'Not found in available sources',
                'quality' => 'not-found',
                'sources' => [],
            ]];
        }

        $hits = $this->sourcesContaining($candidates, $needle, $retrievedAt);
        if ($hits === []) {
            return [[
                'label' => $label,
                'value' => 'Not found in available sources',
                'quality' => 'not-found',
                'note' => 'The value stored on the lead was not repeated by a searched source.',
                'sources' => [],
            ]];
        }

        return [[
            'label' => $label,
            'value' => (string) $lead[$field],
            'quality' => count($this->hosts($hits)) > 1 ? 'corroborated' : 'source-supported',
            'note' => 'The stored value appears in the cited source. A missing source date means it may be outdated, so this is not a confirmed current fact.',
            'sources' => $hits,
        ]];
    }

    /**
     * @param  array<string, mixed>  $lead
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    private function companySection(array $lead, array $candidates, string $retrievedAt): array
    {
        $items = $this->mentionedSection($lead, $candidates, 'company', 'Stored company', $retrievedAt);
        $website = (string) ($lead['website'] ?? '');
        if ($website !== '') {
            $items[] = [
                'label' => 'Website stored on the lead',
                'value' => $website,
                'quality' => 'unverified',
                'note' => 'This URL comes from the CRM record. The scan did not treat it as a discovered fact.',
                'sources' => [],
            ];
        } else {
            $items[] = [
                'label' => 'Official company website',
                'value' => 'Not found in available sources',
                'quality' => 'not-found',
                'sources' => [],
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    private function presenceSection(array $candidates, string $retrievedAt): array
    {
        if ($candidates === []) {
            return [[
                'label' => 'Public professional presence',
                'value' => 'Not found in available sources',
                'quality' => 'not-found',
                'sources' => [],
            ]];
        }

        return array_map(function (array $candidate) use ($retrievedAt) {
            return [
                'label' => ($candidate['source_type'] ?? '') === 'linkedin_public' ? 'Public LinkedIn result' : 'Public page',
                'value' => $candidate['title'] ?: 'Untitled result',
                'quality' => 'source-supported',
                'note' => 'Only the search snippet is stored. The page itself was not opened.',
                'sources' => [$this->source($candidate, $retrievedAt)],
            ];
        }, array_slice($candidates, 0, 6));
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @param  array<int, string>  $errors
     * @return array<int, array<string, mixed>>
     */
    private function findings(array $candidates, array $errors, string $retrievedAt): array
    {
        $items = [];
        foreach (array_slice($candidates, 0, 4) as $candidate) {
            if (($candidate['excerpt'] ?? '') === '') {
                continue;
            }
            $items[] = [
                'label' => 'Source excerpt',
                'value' => $candidate['excerpt'],
                'quality' => 'source-supported',
                'note' => 'Extracted from the search snippet. This is the source statement, not an interpretation.',
                'sources' => [$this->source($candidate, $retrievedAt)],
            ];
        }
        if ($items === []) {
            $items[] = [
                'label' => 'Important findings',
                'value' => 'Not found in available sources',
                'quality' => 'not-found',
                'sources' => [],
            ];
        }
        foreach ($errors as $error) {
            $items[] = [
                'label' => 'Search limitation',
                'value' => $this->errorText($error),
                'quality' => 'unverified',
                'sources' => [],
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    private function comparison(array $candidates): array
    {
        return array_map(fn (array $candidate) => [
            'title' => $candidate['title'] ?? '',
            'url' => $candidate['url'] ?? '',
            'tier' => $candidate['tier'] ?? 'weak',
            'reasons' => $candidate['reasons'] ?? [],
            'uncertainties' => $candidate['uncertainties'] ?? [],
        ], $candidates);
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    private function sourcesContaining(array $candidates, string $needle, string $retrievedAt): array
    {
        $hits = [];
        foreach ($candidates as $candidate) {
            $haystack = strtolower(($candidate['title'] ?? '').' '.($candidate['excerpt'] ?? ''));
            if (str_contains($haystack, $needle)) {
                $hits[] = $this->source($candidate, $retrievedAt);
            }
        }

        return $hits;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sources
     * @return array<int, string>
     */
    private function hosts(array $sources): array
    {
        $hosts = [];
        foreach ($sources as $source) {
            $host = strtolower((string) parse_url((string) ($source['url'] ?? ''), PHP_URL_HOST));
            if ($host !== '') {
                $hosts[$host] = true;
            }
        }

        return array_keys($hosts);
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function source(array $candidate, string $retrievedAt): array
    {
        return [
            'title' => $candidate['title'] ?? '',
            'url' => $candidate['url'] ?? '',
            'source_type' => $candidate['source_type'] ?? 'web',
            'excerpt' => $candidate['excerpt'] ?? '',
            'published_at' => $candidate['published_at'] ?? null,
            'retrieved_at' => $retrievedAt,
            'origin' => 'extracted',
        ];
    }

    private function identityQuality(string $status): string
    {
        return match ($status) {
            'strongly_supported' => 'source-supported',
            'possible' => 'possible-match',
            'conflicting' => 'conflicting',
            'not_found' => 'not-found',
            default => 'unverified',
        };
    }

    private function tierQuality(string $tier): string
    {
        return match ($tier) {
            'strong' => 'source-supported',
            'possible' => 'possible-match',
            'conflicting' => 'conflicting',
            'weak' => 'unverified',
            default => 'not-found',
        };
    }

    private function errorText(string $error): string
    {
        return match ($error) {
            'timeout' => 'A search request timed out. Results may be incomplete.',
            'rate_limit' => 'The search provider rate limit was reached. Results may be incomplete.',
            'provider' => 'A search request failed. Results may be incomplete.',
            default => 'Part of the search could not be completed.',
        };
    }
}
