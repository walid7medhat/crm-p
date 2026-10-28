<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Stage;
use Illuminate\Support\Collection;

/**
 * Read-only duplicate analysis across Contacted, Qualified, and Assigned.
 *
 * Phone matching reuses Lead::phoneDigits / work_phone_digits (CRM duplicate core).
 * Email matching uses mb_strtolower(trim) — an extension of the phone-only CRM rule.
 * Soft-deleted and soft-merged leads are excluded (Lead SoftDeletes + notMerged scope).
 */
class StageDuplicateAnalysisService
{
    public const STAGE_NAMES = ['Contacted', 'Qualified', 'Assigned'];

    public function analyze(): array
    {
        $stages = $this->resolveStages();
        $stageIds = array_values(array_map(fn ($s) => $s['id'], $stages));
        $idToName = [];
        foreach ($stages as $name => $meta) {
            $idToName[$meta['id']] = $name;
        }

        $leads = Lead::query()
            ->whereIn('stage_id', $stageIds)
            ->get([
                'id',
                'lead_name',
                'first_name',
                'work_phone',
                'work_phone_digits',
                'email',
                'stage_id',
            ]);

        $byStage = [];
        foreach (self::STAGE_NAMES as $name) {
            $byStage[$name] = $leads->where('stage_id', $stages[$name]['id'] ?? 0)->values();
        }

        $comparisons = [
            $this->comparePair($byStage['Contacted'], $byStage['Qualified'], 'Contacted', 'Qualified', $idToName),
            $this->comparePair($byStage['Contacted'], $byStage['Assigned'], 'Contacted', 'Assigned', $idToName),
            $this->comparePair($byStage['Assigned'], $byStage['Qualified'], 'Assigned', 'Qualified', $idToName),
        ];

        $groups = $this->buildIdentityGroups($leads, $idToName);

        $stageCounts = [];
        foreach (self::STAGE_NAMES as $name) {
            $stageCounts[$name] = [
                'stage_id' => $stages[$name]['id'] ?? null,
                'order' => $stages[$name]['order'] ?? null,
                'active_leads' => $byStage[$name]->count(),
            ];
        }

        return [
            'stages' => $stageCounts,
            'rules' => [
                'phone' => 'Lead::phoneDigits / work_phone_digits (primary work_phone only); empty/null never match',
                'email' => 'mb_strtolower(trim(email)); empty/null never match — extension beyond CRM phone-only duplicate badge/merge',
                'excluded' => 'soft-deleted (deleted_at) and soft-merged (merged_into_lead_id) leads',
                'included_fields' => ['work_phone', 'email'],
                'not_used' => ['work_phone_2', 'secondary_email', 'whatsapp_number'],
            ],
            'summary' => [
                'comparisons' => array_map(fn ($c) => [
                    'label' => $c['label'],
                    'duplicate_pairs' => $c['duplicate_pairs'],
                    'unique_leads_involved' => $c['unique_leads_involved'],
                    'matches_by_phone' => $c['matches_by_phone'],
                    'matches_by_email' => $c['matches_by_email'],
                    'matches_by_both' => $c['matches_by_both'],
                    'leads_matching_multiple' => $c['leads_matching_multiple'],
                ], $comparisons),
                'groups_exactly_two_stages' => count($groups['exactly_two_stages']),
                'groups_all_three_stages' => count($groups['all_three_stages']),
                'unique_duplicate_groups' => count($groups['exactly_two_stages']) + count($groups['all_three_stages']),
                'ambiguous_bridges' => count($groups['ambiguous_bridges']),
            ],
            'comparisons' => $comparisons,
            'groups' => $groups,
        ];
    }

    /**
     * @return array<string, array{id:int, order:int|null, name:string}>
     */
    private function resolveStages(): array
    {
        $rows = Stage::query()
            ->where('stage_type', 'lead')
            ->whereIn('name', self::STAGE_NAMES)
            ->get(['id', 'name', 'order']);

        $out = [];
        foreach (self::STAGE_NAMES as $name) {
            $row = $rows->first(fn ($s) => strcasecmp((string) $s->name, $name) === 0);
            if (! $row) {
                throw new \RuntimeException("Lead stage \"{$name}\" was not found.");
            }
            $out[$name] = [
                'id' => (int) $row->id,
                'order' => $row->order !== null ? (int) $row->order : null,
                'name' => (string) $row->name,
            ];
        }

        return $out;
    }

    private function comparePair(Collection $setA, Collection $setB, string $labelA, string $labelB, array $idToName): array
    {
        $pairs = [];
        $unique = [];
        $by = ['phone' => 0, 'email' => 0, 'both' => 0];
        $partnerCounts = [];

        foreach ($setA as $a) {
            foreach ($setB as $b) {
                $kind = $this->classifyPair($a, $b);
                if ($kind === 'none') {
                    continue;
                }
                $pairs[] = [
                    'match' => $kind,
                    'lead_a' => $this->serializeLead($a, $idToName),
                    'lead_b' => $this->serializeLead($b, $idToName),
                ];
                $unique[$a->id] = true;
                $unique[$b->id] = true;
                $by[$kind]++;
                $partnerCounts[$a->id] = ($partnerCounts[$a->id] ?? 0) + 1;
                $partnerCounts[$b->id] = ($partnerCounts[$b->id] ?? 0) + 1;
            }
        }

        $multi = [];
        foreach ($partnerCounts as $leadId => $count) {
            if ($count > 1) {
                $multi[] = ['lead_id' => (int) $leadId, 'partner_count' => $count];
            }
        }

        return [
            'label' => "{$labelA} vs {$labelB}",
            'stage_a' => $labelA,
            'stage_b' => $labelB,
            'duplicate_pairs' => count($pairs),
            'unique_leads_involved' => count($unique),
            'matches_by_phone' => $by['phone'],
            'matches_by_email' => $by['email'],
            'matches_by_both' => $by['both'],
            'leads_matching_multiple' => $multi,
            'pairs' => $pairs,
        ];
    }

    /**
     * Identity-safe groups: one shared phone key OR one shared email key spanning stages.
     * Ambiguous OR-bridged graphs (different phones linked only via a shared email with
     * unrelated phone-only members) are listed separately and not counted as clean groups.
     */
    private function buildIdentityGroups(Collection $leads, array $idToName): array
    {
        $byPhone = [];
        $byEmail = [];
        foreach ($leads as $lead) {
            $phone = Lead::phoneDigits($lead->work_phone);
            if ($phone !== '') {
                $byPhone[$phone][] = $lead;
            }
            $email = $this->emailKey($lead->email);
            if ($email !== '') {
                $byEmail[$email][] = $lead;
            }
        }

        $exactlyTwo = [];
        $allThree = [];
        // Fingerprint of sorted lead ids → index into exactlyTwo/allThree bucket.
        $memberIndex = [];

        $consume = function (string $type, string $key, array $members) use (&$exactlyTwo, &$allThree, &$memberIndex, $idToName) {
            if (count($members) < 2) {
                return;
            }

            $stageSet = [];
            $serialized = [];
            $ids = [];
            foreach ($members as $lead) {
                $stage = $idToName[$lead->stage_id] ?? (string) $lead->stage_id;
                $stageSet[$stage] = true;
                $serialized[] = $this->serializeLead($lead, $idToName);
                $ids[] = (int) $lead->id;
            }
            sort($ids);
            $stageCount = count($stageSet);
            if ($stageCount < 2) {
                return; // within a single stage only — not a cross-stage group
            }

            $fp = implode(',', $ids);
            $bucket = $stageCount >= 3 ? 'allThree' : 'exactlyTwo';

            if (isset($memberIndex[$fp])) {
                // Same lead set already recorded under the other key → upgrade to both.
                [$existingBucket, $idx] = $memberIndex[$fp];
                $list = $existingBucket === 'allThree' ? $allThree : $exactlyTwo;
                $existing = $list[$idx];
                $keys = array_values(array_unique(array_merge($existing['match_keys'] ?? [], [$key])));
                $existing['match_keys'] = $keys;
                if (count($keys) > 1) {
                    $existing['match_type'] = 'both';
                }
                $existing['match_key'] = implode(' + ', $keys);
                if ($existingBucket === 'allThree') {
                    $allThree[$idx] = $existing;
                } else {
                    $exactlyTwo[$idx] = $existing;
                }

                return;
            }

            $payload = [
                'match_type' => $type,
                'match_key' => $key,
                'match_keys' => [$key],
                'stages' => array_values(array_keys($stageSet)),
                'stage_count' => $stageCount,
                'lead_count' => count($serialized),
                'leads' => $serialized,
            ];

            if ($bucket === 'allThree') {
                $memberIndex[$fp] = ['allThree', count($allThree)];
                $allThree[] = $payload;
            } else {
                $memberIndex[$fp] = ['exactlyTwo', count($exactlyTwo)];
                $exactlyTwo[] = $payload;
            }
        };

        foreach ($byPhone as $key => $members) {
            $consume('phone', $key, $members);
        }
        foreach ($byEmail as $key => $members) {
            $consume('email', $key, $members);
        }

        return [
            'exactly_two_stages' => $exactlyTwo,
            'all_three_stages' => $allThree,
            'ambiguous_bridges' => $this->findAmbiguousBridges($leads, $idToName),
        ];
    }

    /**
     * Connected components via phone OR email that contain 2+ distinct phones —
     * these are not treated as a single client without manual review.
     */
    private function findAmbiguousBridges(Collection $leads, array $idToName): array
    {
        $nodes = $leads->keyBy('id');
        $ids = $nodes->keys()->all();
        $adj = [];
        foreach ($ids as $id) {
            $adj[$id] = [];
        }

        for ($i = 0; $i < count($ids); $i++) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $a = $nodes[$ids[$i]];
                $b = $nodes[$ids[$j]];
                if ($this->classifyPair($a, $b) === 'none') {
                    continue;
                }
                $adj[$a->id][] = $b->id;
                $adj[$b->id][] = $a->id;
            }
        }

        $seen = [];
        $bridges = [];
        foreach ($ids as $id) {
            if (isset($seen[$id])) {
                continue;
            }
            $stack = [$id];
            $comp = [];
            $seen[$id] = true;
            while ($stack) {
                $cur = array_pop($stack);
                $comp[] = $cur;
                foreach ($adj[$cur] as $n) {
                    if (! isset($seen[$n])) {
                        $seen[$n] = true;
                        $stack[] = $n;
                    }
                }
            }
            if (count($comp) < 2) {
                continue;
            }

            $phones = [];
            $emails = [];
            $stages = [];
            $serialized = [];
            foreach ($comp as $lid) {
                $lead = $nodes[$lid];
                $p = Lead::phoneDigits($lead->work_phone);
                if ($p !== '') {
                    $phones[$p] = true;
                }
                $e = $this->emailKey($lead->email);
                if ($e !== '') {
                    $emails[$e] = true;
                }
                $stages[$idToName[$lead->stage_id] ?? (string) $lead->stage_id] = true;
                $serialized[] = $this->serializeLead($lead, $idToName);
            }

            if (count($phones) > 1 && count($emails) >= 1 && count($stages) >= 2) {
                $bridges[] = [
                    'reason' => 'Multiple distinct phones linked through a shared email (or mix of phone/email edges). Not counted as one client group.',
                    'distinct_phones' => count($phones),
                    'distinct_emails' => count($emails),
                    'stages' => array_values(array_keys($stages)),
                    'leads' => $serialized,
                ];
            }
        }

        return $bridges;
    }

    private function classifyPair(Lead $a, Lead $b): string
    {
        $phoneMatch = false;
        $da = Lead::phoneDigits($a->work_phone);
        $db = Lead::phoneDigits($b->work_phone);
        if ($da !== '' && $db !== '' && $da === $db) {
            $phoneMatch = true;
        }

        $emailMatch = false;
        $ea = $this->emailKey($a->email);
        $eb = $this->emailKey($b->email);
        if ($ea !== '' && $eb !== '' && $ea === $eb) {
            $emailMatch = true;
        }

        if ($phoneMatch && $emailMatch) {
            return 'both';
        }
        if ($phoneMatch) {
            return 'phone';
        }
        if ($emailMatch) {
            return 'email';
        }

        return 'none';
    }

    private function emailKey(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    private function serializeLead(Lead $lead, array $idToName): array
    {
        return [
            'id' => (int) $lead->id,
            'name' => $lead->lead_name ?: $lead->first_name ?: '(unnamed)',
            'phone' => $lead->work_phone,
            'phone_digits' => Lead::phoneDigits($lead->work_phone),
            'email' => $lead->email,
            'stage_id' => (int) $lead->stage_id,
            'stage' => $idToName[$lead->stage_id] ?? (string) $lead->stage_id,
        ];
    }
}
