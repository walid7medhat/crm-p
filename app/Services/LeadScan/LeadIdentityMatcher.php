<?php

namespace App\Services\LeadScan;

class LeadIdentityMatcher
{
    /**
     * @param  array<string, mixed>  $lead
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    public function rank(array $lead, array $results): array
    {
        $ranked = [];
        $seen = [];
        foreach ($results as $result) {
            $url = (string) ($result['url'] ?? '');
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $assessment = $this->assess($lead, $result);
            if ($assessment['tier'] === 'discard') {
                continue;
            }
            $ranked[] = array_merge($result, $assessment);
        }

        $weight = ['strong' => 0, 'possible' => 1, 'conflicting' => 2, 'weak' => 3];
        usort($ranked, function (array $a, array $b) use ($weight) {
            return ($weight[$a['tier']] ?? 9) <=> ($weight[$b['tier']] ?? 9);
        });

        return $ranked;
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array{status: string, message: string}
     */
    public function identity(array $candidates): array
    {
        $plausible = array_values(array_filter(
            $candidates,
            fn (array $candidate) => in_array($candidate['tier'], ['strong', 'possible'], true)
        ));

        if (count($plausible) > 1) {
            return [
                'status' => 'not_confirmed',
                'message' => 'Identity not confirmed — multiple people may match these details.',
            ];
        }

        $tier = $candidates[0]['tier'] ?? null;
        return match ($tier) {
            'strong' => [
                'status' => 'strongly_supported',
                'message' => 'One result has stronger public evidence. This is not a confirmed identity.',
            ],
            'possible' => [
                'status' => 'possible',
                'message' => 'One possible match needs manual verification.',
            ],
            'conflicting' => [
                'status' => 'conflicting',
                'message' => 'The public results conflict with the details stored on this lead.',
            ],
            'weak' => [
                'status' => 'insufficient',
                'message' => 'The available evidence is too weak to connect these results to this lead.',
            ],
            default => [
                'status' => 'not_found',
                'message' => 'Not found in available sources.',
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $lead
     * @param  array<string, mixed>  $result
     * @return array{tier: string, reasons: array<int, string>, uncertainties: array<int, string>, name_ratio: float}
     */
    private function assess(array $lead, array $result): array
    {
        $text = $this->norm(($result['title'] ?? '').' '.($result['excerpt'] ?? '').' '.($result['url'] ?? ''));
        $reasons = [];
        $uncertainties = [];
        $email = (string) ($lead['email'] ?? '');
        $phone = (string) ($lead['phone'] ?? '');
        $company = $this->norm($lead['company'] ?? '');
        $position = $this->norm($lead['position'] ?? '');
        $location = $this->norm($lead['location'] ?? '');
        $ratio = $this->nameRatio((string) ($lead['name'] ?? ''), $text);

        $emailHit = $email !== '' && str_contains($text, $this->norm($email));
        $phoneHit = $phone !== '' && str_contains(preg_replace('/\D+/', '', $text) ?? '', substr($phone, -8));
        $companyHit = $company !== '' && str_contains($text, $company);
        $positionHit = $position !== '' && str_contains($text, $position);
        $locationHit = $location !== '' && str_contains($text, $location);
        $conflict = $this->conflictingEmployer($company, (string) ($result['excerpt'] ?? '').' '.(string) ($result['title'] ?? ''));

        if ($emailHit) {
            $reasons[] = 'The exact email address appears in this public result.';
        }
        if ($phoneHit) {
            $reasons[] = 'The phone number appears in this public result.';
        }
        if ($ratio >= 0.5) {
            $reasons[] = 'The name is similar to the lead name.';
        }
        if ($companyHit) {
            $reasons[] = 'The stored company name appears in this result.';
        }
        if ($positionHit) {
            $reasons[] = 'The stored job title appears in this result.';
        }
        if ($locationHit) {
            $reasons[] = 'The stored location appears in this result.';
        }
        if ($conflict) {
            $reasons[] = 'A different employer is named, and the stored company is absent.';
        }
        if (empty($result['published_at'])) {
            $uncertainties[] = 'This source has no publication date, so a job title here may be outdated.';
        }
        if (! $emailHit && ! $phoneHit) {
            $uncertainties[] = 'No exact email or phone match was found in this result.';
        }

        $tier = 'discard';
        if ($conflict && $ratio >= 0.5 && ! $emailHit) {
            $tier = 'conflicting';
        } elseif ($emailHit || ($phoneHit && $ratio >= 0.5) || ($ratio >= 0.8 && $companyHit)) {
            $tier = 'strong';
        } elseif ($ratio >= 0.66 || ($ratio >= 0.5 && ($companyHit || $locationHit || $positionHit))) {
            $tier = 'possible';
        } elseif ($ratio >= 0.5 || $phoneHit) {
            $tier = 'weak';
        }

        if ($tier === 'discard') {
            $reasons = [];
        }

        return [
            'tier' => $tier,
            'reasons' => $reasons,
            'uncertainties' => $uncertainties,
            'name_ratio' => $ratio,
        ];
    }

    private function conflictingEmployer(string $company, string $text): bool
    {
        if (strlen($company) < 3) {
            return false;
        }
        if (str_contains($this->norm($text), $company)) {
            return false;
        }
        if (! preg_match('/\b(?:at|with)\s+([A-Z][A-Za-z0-9&]+(?:\s+[A-Z][A-Za-z0-9&]+){0,3})/u', $text, $match)) {
            return false;
        }

        return ! str_contains($this->norm($match[1]), $company);
    }

    private function nameRatio(string $name, string $text): float
    {
        $tokens = $this->tokens($name);
        if ($tokens === []) {
            return 0.0;
        }
        $hits = 0;
        foreach ($tokens as $token) {
            if (str_contains($text, $token)) {
                $hits++;
            }
        }

        return $hits / count($tokens);
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $value): array
    {
        $parts = preg_split('/\s+/u', $this->norm($value)) ?: [];
        $skip = ['mr', 'mrs', 'ms', 'dr', 'the', 'and'];

        return array_values(array_filter($parts, fn (string $part) => strlen($part) >= 2 && ! in_array($part, $skip, true)));
    }

    private function norm(mixed $value): string
    {
        $value = strtolower(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?? ''));

        return $value;
    }
}
