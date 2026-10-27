<?php

namespace App\Services;

use App\Helpers\LeadHistoryHelper;
use App\Models\Lead;
use App\Models\Stage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Automatic soft-merge for eligible stages (Rule A) and New Lead absorption (Rule B).
 * Reuses the existing merged_into_lead_id relationship; never physically deletes leads.
 * Manual merge rules in LeadMergeService remain unchanged.
 */
class EligibleStagesAutoMergeService
{
    /** Prevents recursive stage-change / create hooks while automation is writing. */
    private static bool $running = false;

    public static function isRunning(): bool
    {
        return self::$running;
    }

    /** @return int[] */
    public function eligibleStageIds(): array
    {
        return array_values(array_unique(array_filter([
            (int) config('lead_merge.lead_pool_stage_id'),
            (int) config('lead_merge.unqualified_stage_id'),
            (int) config('lead_merge.lost_stage_id'),
        ])));
    }

    public function newLeadStageId(): ?int
    {
        $configured = config('lead_merge.new_lead_stage_id');
        if ($configured !== null && $configured !== '') {
            return (int) $configured;
        }

        $byName = Stage::query()
            ->where('stage_type', 'lead')
            ->where(function ($q) {
                $q->whereRaw('LOWER(name) = ?', ['new'])
                    ->orWhereRaw('LOWER(name) = ?', ['new lead']);
            })
            ->orderBy('order')
            ->value('id');

        if ($byName) {
            return (int) $byName;
        }

        $id = Stage::query()->where('stage_type', 'lead')->orderBy('order')->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * After create (or a real phone-digit change): Rule B for New Lead, Rule A for eligible stages.
     * Returns soft-merged lead ids (empty if none). Abu Dhabi only — Dubai is never auto-merged.
     *
     * @return int[]
     */
    public function afterLeadCreated(Lead $lead): array
    {
        if (self::$running) {
            return [];
        }

        $lead->loadMissing(['responsiblePerson', 'addedBy']);
        if (! $this->isAutoMergeAllowedBranch($lead)) {
            return [];
        }

        $newId = $this->newLeadStageId();
        if ($newId && (int) $lead->stage_id === $newId) {
            return $this->absorbEligibleIntoNewLead($lead);
        }

        if (in_array((int) $lead->stage_id, $this->eligibleStageIds(), true)) {
            $digits = Lead::phoneDigits($lead->work_phone);
            if ($digits === '') {
                return [];
            }

            return $this->summarizeMergeMap($this->mergeEligibleDigits($digits))['merged_ids'];
        }

        return [];
    }

    /**
     * Rule A hook after a move into an eligible stage.
     *
     * @return array{merged_ids: int[], primary_id: int|null, primary_stage_id: int|null}
     */
    public function afterStageChanged(Lead $lead, ?int $newStageId = null): array
    {
        $empty = ['merged_ids' => [], 'primary_id' => null, 'primary_stage_id' => null];

        if (self::$running) {
            return $empty;
        }

        $lead->loadMissing(['responsiblePerson', 'addedBy']);
        if (! $this->isAutoMergeAllowedBranch($lead)) {
            return $empty;
        }

        $stageId = $newStageId ?? (int) $lead->stage_id;
        if (! in_array($stageId, $this->eligibleStageIds(), true)) {
            return $empty;
        }

        $digits = Lead::phoneDigits($lead->work_phone);
        if ($digits === '') {
            return $empty;
        }

        return $this->summarizeMergeMap($this->mergeEligibleDigits($digits));
    }

    /**
     * @param  array<int, int[]>  $map  primary_id => merged ids
     * @return array{merged_ids: int[], primary_id: int|null, primary_stage_id: int|null}
     */
    private function summarizeMergeMap(array $map): array
    {
        if ($map === []) {
            return ['merged_ids' => [], 'primary_id' => null, 'primary_stage_id' => null];
        }

        $mergedIds = [];
        $primaryId = null;
        $primaryStageId = null;
        $pool = (int) config('lead_merge.lead_pool_stage_id');

        foreach ($map as $pid => $ids) {
            $primaryId = (int) $pid;
            $mergedIds = array_merge($mergedIds, array_map('intval', $ids));
            $primaryStageId = (int) (Lead::query()->where('id', $primaryId)->value('stage_id') ?? $pool);
        }

        return [
            'merged_ids' => array_values(array_unique($mergedIds)),
            'primary_id' => $primaryId,
            'primary_stage_id' => $primaryStageId,
        ];
    }

    /**
     * Rule A: among Unqualified / Lost / Lead Pool duplicates, newest created_at
     * becomes primary and ends in Lead Pool.
     *
     * @return array<int,int[]> primary_id => merged ids
     */
    public function mergeEligibleDigits(string $digits): array
    {
        if ($digits === '' || self::$running) {
            return [];
        }

        $eligible = $this->eligibleStageIds();
        $pool = (int) config('lead_merge.lead_pool_stage_id');
        $result = [];

        self::$running = true;
        try {
            DB::transaction(function () use ($digits, $eligible, $pool, &$result) {
                $leads = Lead::query()
                    ->where('work_phone_digits', $digits)
                    ->whereIn('stage_id', $eligible)
                    ->whereNull('converted_to_deal_id')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->get();

                $leads = $leads->load(['responsiblePerson', 'addedBy'])
                    ->filter(fn (Lead $l) => $this->isAutoMergeAllowedBranch($l))
                    ->values();

                if ($leads->count() < 2) {
                    return;
                }

                $groups = $this->clusterEligibleLeads($leads);

                foreach ($groups as $group) {
                    if ($group->count() < 2) {
                        continue;
                    }

                    /** @var Lead $default */
                    $default = $group->first(); // newest created_at, then highest id
                    $others = $group->skip(1)->values();
                    $ids = $others->pluck('id')->all();

                    $this->softMergeInto($default, $ids, 'eligible_auto');

                    if ((int) $default->stage_id !== $pool) {
                        $fromStageId = (int) $default->stage_id;
                        Lead::query()->where('id', $default->id)->whereNull('merged_into_lead_id')->update([
                            'stage_id' => $pool,
                            'last_stage_change_at' => now(),
                        ]);
                        $default->stage_id = $pool;
                        LeadHistoryHelper::log($default->id, [
                            'action' => 'stage_changed',
                            'old_stage_id' => $fromStageId,
                            'new_stage_id' => $pool,
                            'merge_type' => 'eligible_auto',
                            'note' => 'Primary moved to Lead Pool after automatic eligible-stage merge',
                        ], null);
                    }

                    $result[$default->id] = $ids;
                }
            });
        } finally {
            self::$running = false;
        }

        if ($result) {
            LeadKanbanHeatCache::bump();
        }

        return $result;
    }

    /**
     * Rule B: New Lead stays in New Lead; matching eligible-stage duplicates soft-merge into it.
     *
     * @return int[] merged lead ids
     */
    public function absorbEligibleIntoNewLead(Lead $newLead): array
    {
        if (self::$running) {
            return [];
        }

        $newStageId = $this->newLeadStageId();
        if (! $newStageId || (int) $newLead->stage_id !== $newStageId) {
            return [];
        }

        $newLead->loadMissing(['responsiblePerson', 'addedBy']);
        if (! $this->isAutoMergeAllowedBranch($newLead)) {
            return [];
        }

        $digits = Lead::phoneDigits($newLead->work_phone);
        if ($digits === '') {
            return [];
        }

        $eligible = $this->eligibleStageIds();
        $mergedIds = [];

        self::$running = true;
        try {
            DB::transaction(function () use ($newLead, $digits, $eligible, &$mergedIds) {
                $default = Lead::query()->where('id', $newLead->id)->lockForUpdate()->first();
                if (! $default || $default->merged_into_lead_id) {
                    return;
                }

                $default->loadMissing(['responsiblePerson', 'addedBy']);
                if (! $this->isAutoMergeAllowedBranch($default)) {
                    return;
                }

                $candidates = Lead::query()
                    ->where('work_phone_digits', $digits)
                    ->whereIn('stage_id', $eligible)
                    ->where('id', '!=', $default->id)
                    ->whereNull('converted_to_deal_id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($candidates->isEmpty()) {
                    return;
                }

                $candidates = $candidates->load(['responsiblePerson', 'addedBy'])
                    ->filter(fn (Lead $l) => $this->isAutoMergeAllowedBranch($l))
                    ->values();

                if (config('lead_merge.group_by_branch')) {
                    $candidates = $candidates
                        ->filter(fn (Lead $l) => $default->sameBranchAs($l))
                        ->values();
                }

                if ($candidates->isEmpty()) {
                    return;
                }

                $ids = $candidates->pluck('id')->all();
                $this->softMergeInto($default, $ids, 'new_lead_auto');
                $mergedIds = $ids;
            });
        } finally {
            self::$running = false;
        }

        if ($mergedIds) {
            LeadKanbanHeatCache::bump();
        }

        return $mergedIds;
    }

    /**
     * Daily / backfill: Rule A for every phone with 2+ eligible-stage duplicates.
     */
    public function mergeAllEligibleDuplicates(): int
    {
        $merged = 0;
        $eligible = $this->eligibleStageIds();

        Lead::query()
            ->whereIn('stage_id', $eligible)
            ->where('work_phone_digits', '!=', '')
            ->whereNull('converted_to_deal_id')
            ->groupBy('work_phone_digits')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('work_phone_digits')
            ->each(function ($digits) use (&$merged) {
                foreach ($this->mergeEligibleDigits((string) $digits) as $ids) {
                    $merged += count($ids);
                }
            });

        return $merged;
    }

    /**
     * Automatic merge only for configured branches (default: Abu Dhabi).
     * Dubai / empty / other sources are excluded so they never soft-merge automatically.
     */
    public function isAutoMergeAllowedBranch(Lead $lead): bool
    {
        $allowed = config('lead_merge.auto_merge_branch_keys', ['abu dhabi']);
        if (! is_array($allowed) || $allowed === []) {
            return false;
        }

        $allowed = array_values(array_filter(array_map(
            static fn ($v) => mb_strtolower(trim((string) $v)),
            $allowed
        )));
        if ($allowed === []) {
            return false;
        }

        $haystacks = array_filter([
            mb_strtolower(trim((string) $lead->lead_branch_source)),
            mb_strtolower(trim($lead->branchKey())),
        ], static fn ($v) => $v !== '');

        if ($haystacks === []) {
            return false;
        }

        foreach ($haystacks as $hay) {
            // Never auto-merge Dubai sources.
            if (str_contains($hay, 'dubai')) {
                return false;
            }
            foreach ($allowed as $needle) {
                if ($needle !== '' && str_contains($hay, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Group eligible duplicates for Rule A.
     * When group_by_branch is on, cluster with sameBranchAs() (empty branch key is compatible)
     * instead of strict branchKey equality — matches Rule B.
     *
     * @return Collection<int, Collection<int, Lead>>
     */
    private function clusterEligibleLeads(Collection $leads): Collection
    {
        if (! config('lead_merge.group_by_branch')) {
            return collect([$leads]);
        }

        $leads->loadMissing(['responsiblePerson', 'addedBy']);
        $clusters = collect();
        $remaining = $leads->values();

        while ($remaining->isNotEmpty()) {
            /** @var Lead $seed */
            $seed = $remaining->first();
            $cluster = $remaining->filter(fn (Lead $l) => $seed->sameBranchAs($l))->values();
            $clusters->push($cluster);
            $taken = $cluster->pluck('id')->all();
            $remaining = $remaining->reject(fn (Lead $l) => in_array($l->id, $taken, true))->values();
        }

        return $clusters;
    }

    /**
     * Soft-merge $ids into $default. Flattens prior chains. Does not change $default stage.
     *
     * @param  int[]  $ids
     */
    private function softMergeInto(Lead $default, array $ids, string $mergeType): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, fn ($id) => $id !== (int) $default->id));
        if ($ids === []) {
            return;
        }

        Lead::withoutGlobalScope('notMerged')
            ->whereIn('merged_into_lead_id', $ids)
            ->update(['merged_into_lead_id' => $default->id]);

        $affected = Lead::query()
            ->whereIn('id', $ids)
            ->whereNull('merged_into_lead_id')
            ->update([
                'merged_into_lead_id'      => $default->id,
                'merged_at'                => now(),
                'merged_by'                => null,
                'merged_original_stage_id' => DB::raw('stage_id'),
                'merge_type'               => $mergeType,
            ]);

        if ($affected) {
            LeadHistoryHelper::log($default->id, [
                'action' => 'merged',
                'merge_type' => $mergeType,
                'merged_ids' => $ids,
            ], null);
        }
    }
}
