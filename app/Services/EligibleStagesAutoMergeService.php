<?php

namespace App\Services;

use App\Helpers\LeadHistoryHelper;
use App\Models\Lead;
use App\Models\Stage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Notifications\LeadMergedNotification;

/**
 * Automatic soft-merge rules:
 * - Main stages are Qualified, Contacted, Assigned, and New Lead, in that priority order.
 * - Eligible stages are Lead Pool, Unqualified, and Lost.
 * - Eligible leads merge into the highest-priority matching main-stage lead.
 * - Main-stage leads never merge into one another as part of this automation.
 * - If no main-stage primary exists, the newest eligible lead becomes primary and is moved to Lead Pool.
 * - A newly created New Lead keeps the existing special behavior and absorbs matching eligible leads.
 *
 * Leads are soft-merged only; no lead is physically deleted. Manual merge rules are unchanged.
 */
class EligibleStagesAutoMergeService
{
    /** Prevent recursive stage-change / create hooks while automation is writing. */
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

    /** @return array<int, int> Stage ID => priority (lower number wins). */
    private function mainStagePriorities(): array
    {
        $ids = [
            $this->resolveStageId('qualified', ['qualified']),
            $this->resolveStageId('contacted', ['contacted']),
            $this->resolveStageId('assigned', ['assigned']),
            $this->newLeadStageId(),
        ];

        $priorities = [];
        foreach ($ids as $priority => $id) {
            if ($id !== null && $id > 0 && ! array_key_exists($id, $priorities)) {
                $priorities[$id] = $priority;
            }
        }

        return $priorities;
    }

    private function resolveStageId(string $key, array $fallbackNames): ?int
    {
        $configured = config("lead_merge.{$key}_stage_id");
        if ($configured !== null && $configured !== '' && (int) $configured > 0) {
            return (int) $configured;
        }

        $query = Stage::query()->where('stage_type', 'lead');
        $query->where(function ($q) use ($fallbackNames) {
            foreach ($fallbackNames as $index => $name) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $q->{$method}('LOWER(name) = ?', [mb_strtolower($name)]);
            }
        });

        $id = $query->orderBy('order')->value('id');
        return $id ? (int) $id : null;
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

        // Retains the legacy fallback for installations whose New stage is not named conventionally.
        $id = Stage::query()->where('stage_type', 'lead')->orderBy('order')->value('id');
        return $id ? (int) $id : null;
    }

    /**
     * After create (or a real phone-digit change): New Lead uses its special absorption rule;
     * eligible or main-stage leads can trigger the general eligible-stage merge.
     *
     * @return int[] IDs of soft-merged leads
     */
    public function afterLeadCreated(Lead $lead): array
    {
        if (self::$running) {
            return [];
        }

        $lead->loadMissing(['responsiblePerson', 'addedBy']);

        $newId = $this->newLeadStageId();
        if ($newId && (int) $lead->stage_id === $newId) {
            return $this->absorbEligibleIntoNewLead($lead);
        }

        $mainIds = array_keys($this->mainStagePriorities());
        $canTrigger = in_array((int) $lead->stage_id, $this->eligibleStageIds(), true)
            || in_array((int) $lead->stage_id, $mainIds, true);

        if (! $canTrigger || ! $this->isAutoMergeAllowedBranch($lead)) {
            return [];
        }

        return $this->summarizeMergeMap($this->mergeEligibleContact(
            Lead::phoneDigits($lead->work_phone),
            Lead::normalizeEmail($lead->email)
        ))['merged_ids'];
    }

    /**
     * Hook after a lead changes stage.
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
        $stageId = $newStageId ?? (int) $lead->stage_id;
        $newId = $this->newLeadStageId();

        if ($newId && $stageId === $newId) {
            $ids = $this->absorbEligibleIntoNewLead($lead);
            return [
                'merged_ids' => $ids,
                'primary_id' => $ids ? (int) $lead->id : null,
                'primary_stage_id' => $ids ? $stageId : null,
            ];
        }

        $canTrigger = in_array($stageId, $this->eligibleStageIds(), true)
            || array_key_exists($stageId, $this->mainStagePriorities());

        if (! $canTrigger || ! $this->isAutoMergeAllowedBranch($lead)) {
            return $empty;
        }

        return $this->summarizeMergeMap($this->mergeEligibleContact(
            Lead::phoneDigits($lead->work_phone),
            Lead::normalizeEmail($lead->email)
        ));
    }

    /**
     * @param array<int, int[]> $map Primary ID => merged IDs
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
        foreach ($map as $pid => $ids) {
            $primaryId = (int) $pid;
            $mergedIds = array_merge($mergedIds, array_map('intval', $ids));
            $primaryStageId = (int) (Lead::query()->where('id', $primaryId)->value('stage_id') ?? 0);
        }

        return [
            'merged_ids' => array_values(array_unique($mergedIds)),
            'primary_id' => $primaryId,
            'primary_stage_id' => $primaryStageId ?: null,
        ];
    }

    /**
     * General rule: merge only eligible-stage leads (Lead Pool / Unqualified / Lost).
     * If a matching main-stage lead exists, choose by stage priority, then newest created_at/id.
     * Main-stage leads are never merged into each other. If no main-stage primary exists,
     * the newest eligible lead becomes primary and is moved to Lead Pool, but only when there
     * are at least two eligible leads to merge together.
     *
     * Leads match on the same primary phone digits OR the same email.
     *
     * @return array<int, int[]> Primary ID => merged eligible lead IDs
     */
    public function mergeEligibleContact(string $digits, string $email = ''): array
    {
        if (($digits === '' && $email === '') || self::$running) {
            return [];
        }

        $eligibleIds = $this->eligibleStageIds();
        if ($eligibleIds === []) {
            return [];
        }

        $pool = (int) config('lead_merge.lead_pool_stage_id');
        $mainPriorities = $this->mainStagePriorities();
        $result = [];
        $mergeEvents = [];
        self::$running = true;
        try {
            DB::transaction(function () use ($digits, $email, $eligibleIds, $pool, $mainPriorities, &$result, &$mergeEvents) {
                $contactIds = $this->safeContactIds($digits, $email);
                if ($contactIds === []) {
                    return;
                }

                $eligibleLeads = Lead::query()
                    ->whereIn('id', $contactIds)
                    ->whereIn('stage_id', $eligibleIds)
                    ->whereNull('converted_to_deal_id')
                    ->whereNull('merged_into_lead_id')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->get()
                    ->load(['responsiblePerson', 'addedBy'])
                    ->filter(fn (Lead $lead) => $this->isAutoMergeAllowedBranch($lead))
                    ->values();

                if ($eligibleLeads->isEmpty()) {
                    return;
                }

                $groups = $this->clusterEligibleLeads($eligibleLeads);
                foreach ($groups as $group) {
                    if ($group->isEmpty()) {
                        continue;
                    }

                    $groupIds = $group->pluck('id')->map(fn ($id) => (int) $id)->all();
                    $mainCandidates = collect();

                    if ($mainPriorities !== []) {
                        $mainCandidates = Lead::query()
                            ->whereIn('id', $contactIds)
                            ->whereIn('stage_id', array_keys($mainPriorities))
                            ->whereNull('converted_to_deal_id')
                            ->whereNull('merged_into_lead_id')
                            ->lockForUpdate()
                            ->get()
                            ->load(['responsiblePerson', 'addedBy'])
                            ->filter(function (Lead $candidate) use ($mainPriorities, $group) {
                                if (! $this->canBeMainStagePrimary($candidate, $mainPriorities)) {
                                    return false;
                                }

                                if (! config('lead_merge.group_by_branch')) {
                                    return true;
                                }

                                return $group->contains(fn (Lead $eligible) => $candidate->sameBranchAs($eligible));
                            })
                            ->sort(function (Lead $a, Lead $b) use ($mainPriorities) {
                                $priorityA = $mainPriorities[(int) $a->stage_id] ?? PHP_INT_MAX;
                                $priorityB = $mainPriorities[(int) $b->stage_id] ?? PHP_INT_MAX;
                                if ($priorityA !== $priorityB) {
                                    return $priorityA <=> $priorityB;
                                }

                                $dateCompare = ($b->created_at <=> $a->created_at);
                                return $dateCompare !== 0 ? $dateCompare : ((int) $b->id <=> (int) $a->id);
                            })
                            ->values();
                    }

                    if ($mainCandidates->isNotEmpty()) {
                            /** @var Lead $primary */
                            $primary = $mainCandidates->first();

                            // Only eligible-stage leads merge into the main primary. Other main leads stay untouched.
                            $idsToMerge = array_values(array_filter(
                                $groupIds,
                                fn (int $id) => $id !== (int) $primary->id
                            ));

                            if ($idsToMerge !== []) {
                                $affected = $this->softMergeInto($primary, $idsToMerge, 'eligible_auto');
                                $result[(int) $primary->id] = $idsToMerge;

                                if ($affected > 0) {
                                    $mergeEvents[] = [
                                        'primary_id' => (int) $primary->id,
                                        'merged_ids' => $idsToMerge,
                                    ];
                                }
                            }

                            continue;
                        }

                    // Without a main-stage primary, only merge if there are at least two eligible leads.
                    if ($group->count() < 2) {
                        continue;
                    }

                    /** @var Lead $primary */
                    $primary = $group->first(); // newest created_at, then highest id
                    $idsToMerge = $group->skip(1)->pluck('id')->map(fn ($id) => (int) $id)->all();
                    $this->softMergeInto($primary, $idsToMerge, 'eligible_auto');

                    if ((int) $primary->stage_id !== $pool && $pool > 0) {
                        $fromStageId = (int) $primary->stage_id;
                        Lead::query()
                            ->where('id', $primary->id)
                            ->whereNull('merged_into_lead_id')
                            ->update([
                                'stage_id' => $pool,
                                'last_stage_change_at' => now(),
                            ]);

                        $primary->stage_id = $pool;
                        $this->mergeLog('Primary moved to Lead Pool', [
                            'primary_id' => (int) $primary->id,
                            'from_stage_id' => $fromStageId,
                        ]);
                        LeadHistoryHelper::log($primary->id, [
                            'action' => 'stage_changed',
                            'old_stage_id' => $fromStageId,
                            'new_stage_id' => $pool,
                            'old_stage' => Stage::query()->whereKey($fromStageId)->value('name'),
                            'new_stage' => Stage::query()->whereKey($pool)->value('name'),
                            'merge_type' => 'eligible_auto',
                            'note' => 'Primary moved to Lead Pool after automatic eligible-stage merge',
                        ], null);
                    }

                    $result[(int) $primary->id] = $idsToMerge;
                }
            });
        } finally {
            self::$running = false;
        }
        $this->dispatchMergeNotifications($mergeEvents);
        if ($result) {
            LeadKanbanHeatCache::bump();
        }

        return $result;
    }

    /**
     * Rule for a newly created New Lead: it stays in New Lead and absorbs matching eligible leads.
     *
     * @return int[] merged lead IDs
     */
    public function absorbEligibleIntoNewLead(Lead $newLead): array
    {
        $mergeEvents = [];
        if (self::$running) {
            return [];
        }

        $newStageId = $this->newLeadStageId();
        if (! $newStageId || (int) $newLead->stage_id !== $newStageId) {
            return [];
        }

        $newLead->loadMissing(['responsiblePerson', 'addedBy']);
        // Only Abu Dhabi New Leads absorb; Dubai and branch-less New Leads never do.
        if ($this->isDubaiBranch($newLead) || ! $this->canBeAutoMergePrimary($newLead)) {
            return [];
        }

        $digits = Lead::phoneDigits($newLead->work_phone);
        $email = Lead::normalizeEmail($newLead->email);
        if ($digits === '' && $email === '') {
            return [];
        }

        $eligibleIds = $this->eligibleStageIds();
        $mergedIds = [];

        self::$running = true;
        try {
            DB::transaction(function () use ($newLead, $digits, $email, $eligibleIds, &$mergedIds, &$mergeEvents) {
                $primary = Lead::query()
                    ->where('id', $newLead->id)
                    ->whereNull('merged_into_lead_id')
                    ->lockForUpdate()
                    ->first();

                if (! $primary) {
                    return;
                }

                $primary->loadMissing(['responsiblePerson', 'addedBy']);
                if ($this->isDubaiBranch($primary) || ! $this->canBeAutoMergePrimary($primary)) {
                    return;
                }

                $contactIds = $this->safeContactIds($digits, $email);
                if ($contactIds === []) {
                    return;
                }

                $candidates = Lead::query()
                    ->whereIn('id', $contactIds)
                    ->whereIn('stage_id', $eligibleIds)
                    ->where('id', '!=', $primary->id)
                    ->whereNull('converted_to_deal_id')
                    ->whereNull('merged_into_lead_id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->load(['responsiblePerson', 'addedBy'])
                    // Strict: a branch-less pool lead (e.g. added by super admin / from Meta with
                    // no branch) is never absorbed — only leads that are really Abu Dhabi.
                    ->filter(fn (Lead $candidate) => $this->canAbsorbCandidate($candidate))
                    ->values();

                if (config('lead_merge.group_by_branch')) {
                    $candidates = $candidates
                        ->filter(fn (Lead $candidate) => $primary->sameBranchAs($candidate))
                        ->values();
                }

                if ($candidates->isEmpty()) {
                    return;
                }

                $mergedIds = $candidates->pluck('id')->map(fn ($id) => (int) $id)->all();
                    $affected = $this->softMergeInto($primary, $mergedIds, 'new_lead_auto');

                    if ($affected > 0) {
                        $mergeEvents[] = [
                            'primary_id' => (int) $primary->id,
                            'merged_ids' => $mergedIds,
                        ];
                    }
            });
        } finally {
            self::$running = false;
        }
        $this->dispatchMergeNotifications($mergeEvents);
        if ($mergedIds) {
            LeadKanbanHeatCache::bump();
        }

        return $mergedIds;
    }

    /**
     * Daily/backfill processing for every phone number and email represented in eligible stages.
     * Also catches a single eligible lead that has a matching main-stage primary.
     */
    public function mergeAllEligibleDuplicates(): int
    {
        $merged = 0;
        $eligibleIds = $this->eligibleStageIds();

        if ($eligibleIds === []) {
            return 0;
        }

        $startedAt = microtime(true);
        $this->mergeLog('Scheduled merge started');

        $groups = 0;
        $errors = 0;
        $run = function (string $kind, string $column, callable $merge) use ($eligibleIds, &$merged, &$groups, &$errors) {
            $done = 0;
            foreach ($this->duplicatedContactValues($column, $eligibleIds) as $value) {
                try {
                    $map = $merge((string) $value);
                    $groups += count($map);
                    foreach ($map as $ids) {
                        $merged += count($ids);
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $this->mergeLog("Merge failed for {$kind}", ['value' => $value, 'error' => $e->getMessage()], 'error');
                }

                if (++$done % 500 === 0) {
                    $this->mergeLog("Progress {$kind}: {$done}", [
                        'merged_so_far' => $merged,
                        'memory_mb' => round(memory_get_usage(true) / 1048576),
                    ]);
                }
            }
            $this->mergeLog("Finished {$kind}: {$done} checked", ['merged_so_far' => $merged]);
        };

        $run('phones', 'work_phone_digits', fn (string $digits) => $this->mergeEligibleContact($digits));
        $run('emails', 'email', function (string $email) {
            $email = Lead::normalizeEmail($email);
            return $email === '' ? [] : $this->mergeEligibleContact('', $email);
        });

        $this->mergeLog('Scheduled merge finished', [
            'merged_leads' => $merged,
            'groups' => $groups,
            'errors' => $errors,
            'seconds' => round(microtime(true) - $startedAt, 1),
        ]);

        return $merged;
    }

    /**
     * Phone digits / emails held by 2+ unmerged leads, at least one of them in an eligible stage.
     * Grouped in MySQL and read 1000 at a time (keyset), so memory stays flat on large tables.
     *
     * @param int[] $eligibleIds
     * @return \Generator<int, string>
     */
    private function duplicatedContactValues(string $column, array $eligibleIds): \Generator
    {
        $placeholders = implode(',', array_fill(0, count($eligibleIds), '?'));
        $last = null;

        do {
            $batch = Lead::query()
                ->where($column, '!=', '')
                ->whereNotNull($column)
                ->when($last !== null, fn ($q) => $q->where($column, '>', $last))
                ->groupBy($column)
                ->havingRaw('COUNT(*) > 1')
                ->havingRaw(
                    "SUM(CASE WHEN stage_id IN ({$placeholders}) AND converted_to_deal_id IS NULL THEN 1 ELSE 0 END) > 0",
                    $eligibleIds
                )
                ->orderBy($column)
                ->limit(1000)
                ->pluck($column);

            foreach ($batch as $value) {
                yield (string) $value;
            }
            $last = $batch->last();
        } while ($batch->count() === 1000);
    }

    /**
     * Matching ids for auto-merge, or [] when the phone is too short or the phone/email is shared
     * by so many leads that it must be a placeholder (also keeps memory bounded).
     *
     * @return int[]
     */
    private function safeContactIds(string $digits, string $email): array
    {
        if ($digits !== '' && strlen($digits) < (int) config('lead_merge.min_phone_digits', 7)) {
            $digits = '';
        }
        if ($digits === '' && $email === '') {
            return [];
        }

        $max = max(2, (int) config('lead_merge.max_contact_matches', 50));
        $ids = Lead::matchingContactIds($digits, $email, $max + 1);
        if (count($ids) > $max) {
            $this->mergeLog('Skipped placeholder contact (too many matching leads)', [
                'phone_digits' => $digits,
                'email' => $email,
                'limit' => $max,
            ], 'warning');

            return [];
        }

        return $ids;
    }

    /** Writes to storage/logs/lead-merge-YYYY-MM-DD.log; logging never breaks a merge. */
    private function mergeLog(string $message, array $context = [], string $level = 'info'): void
    {
        try {
            Log::channel('lead_merge')->log($level, $message, $context);
        } catch (\Throwable) {
        }
    }

    /** @return string[] lowercased non-empty branch labels for a lead */
    private function branchHaystacks(Lead $lead): array
    {
        $lead->loadMissing(['responsiblePerson', 'addedBy']);

        return array_values(array_filter([
            mb_strtolower(trim((string) $lead->lead_branch_source)),
            mb_strtolower(trim((string) ($lead->branch ?? ''))),
            mb_strtolower(trim($lead->branchKey())),
        ], static fn ($value) => $value !== ''));
    }

    public function hasEmptyBranch(Lead $lead): bool
    {
        return $this->branchHaystacks($lead) === [];
    }

    public function isDubaiBranch(Lead $lead): bool
    {
        foreach ($this->branchHaystacks($lead) as $haystack) {
            if (str_contains($haystack, 'dubai')) {
                return true;
            }
        }

        return false;
    }

    /** Automatic merge preferred branches (default: Abu Dhabi). Dubai never matches. */
    public function isAutoMergeAllowedBranch(Lead $lead): bool
    {
        if ($this->isDubaiBranch($lead)) {
            return false;
        }

        $allowed = config('lead_merge.auto_merge_branch_keys', ['abu dhabi']);
        if (! is_array($allowed) || $allowed === []) {
            return false;
        }

        $allowed = array_values(array_filter(array_map(
            static fn ($value) => mb_strtolower(trim((string) $value)),
            $allowed
        )));
        if ($allowed === []) {
            return false;
        }

        foreach ($this->branchHaystacks($lead) as $haystack) {
            $compactHaystack = str_replace([' ', '-', '_'], '', $haystack);
            foreach ($allowed as $needle) {
                if ($needle === '') {
                    continue;
                }
                if (str_contains($haystack, $needle)) {
                    return true;
                }
                $compactNeedle = str_replace([' ', '-', '_'], '', $needle);
                if ($compactNeedle !== '' && str_contains($compactHaystack, $compactNeedle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** New Lead can be primary only when it is really Abu Dhabi — never Dubai or branch-less. */
    public function canBeAutoMergePrimary(Lead $lead): bool
    {
        if ($this->isDubaiBranch($lead)) {
            return false;
        }

        return $this->isAutoMergeAllowedBranch($lead);
    }

    /** Eligible Lost/Unqualified/Lead Pool candidate for absorption. */
    public function canAbsorbCandidate(Lead $lead, bool $allowEmpty = false): bool
    {
        if ($this->isDubaiBranch($lead)) {
            return false;
        }
        if ($this->isAutoMergeAllowedBranch($lead)) {
            return true;
        }

        return $allowEmpty && $this->hasEmptyBranch($lead);
    }

    private function canBeMainStagePrimary(Lead $lead, array $mainPriorities): bool
    {
        if (! array_key_exists((int) $lead->stage_id, $mainPriorities)) {
            return false;
        }

        if ((int) $lead->stage_id === (int) $this->newLeadStageId()) {
            return $this->canBeAutoMergePrimary($lead);
        }

        return $this->isAutoMergeAllowedBranch($lead);
    }

    /**
     * Group eligible leads for Rule A. With branch grouping enabled, use sameBranchAs()
     * so leads with compatible/empty branch keys follow the existing grouping behavior.
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
            $cluster = $remaining->filter(fn (Lead $lead) => $seed->sameBranchAs($lead))->values();
            $clusters->push($cluster);
            $taken = $cluster->pluck('id')->all();
            $remaining = $remaining
                ->reject(fn (Lead $lead) => in_array($lead->id, $taken, true))
                ->values();
        }

        return $clusters;
    }

    /**
     * Soft-merge $ids into $primary. Flattens prior merge chains and never changes primary stage.
     *
     * @param int[] $ids
     */
    private function softMergeInto(Lead $primary, array $ids, string $mergeType): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, fn ($id) => $id !== (int) $primary->id));
        if ($ids === []) {
            return 0;
        }

        Lead::withoutGlobalScope('notMerged')
            ->whereIn('merged_into_lead_id', $ids)
            ->update(['merged_into_lead_id' => $primary->id]);

        $affected = Lead::query()
            ->whereIn('id', $ids)
            ->whereNull('merged_into_lead_id')
            ->whereNull('converted_to_deal_id')
            ->update([
                'merged_into_lead_id' => $primary->id,
                'merged_at' => now(),
                'merged_by' => null,
                'merged_original_stage_id' => DB::raw('stage_id'),
                'merge_type' => $mergeType,
            ]);

        if ($affected) {
            LeadHistoryHelper::logMerge((int) $primary->id, $ids, $mergeType);
            $this->mergeLog('Merged', [
                'primary_id' => (int) $primary->id,
                'primary_stage_id' => (int) $primary->stage_id,
                'merged_ids' => $ids,
                'type' => $mergeType,
            ]);
        }
            return (int) $affected;

    }
    /**
 * Notify primary lead owners after a successful merge.
 * Runs after commit; a notification failure never affects the merge.
 *
 * @param array<int, array{primary_id: int, merged_ids: int[]}> $events
 */
private function dispatchMergeNotifications(array $events): void
{
    if ($events === []) {
        return;
    }

    DB::afterCommit(function () use ($events) {
        $poolId = (int) config('lead_merge.lead_pool_stage_id');

        foreach ($events as $event) {
            try {
                $primary = Lead::query()->with('responsiblePerson')->find($event['primary_id']);
                if (! $primary) {
                    continue;
                }

                // Never notify when the primary is in Lead Pool (re-read from DB, so the
                // "primary moved to Lead Pool" case is covered).
                if ($poolId > 0 && (int) $primary->stage_id === $poolId) {
                    continue;
                }

                $owner = $primary->responsiblePerson;
                if (! $owner) {
                    continue;
                }

                $ids = $event['merged_ids'];
                sort($ids);
                $key = 'lead_merge:' . $primary->id . ':' . md5(implode(',', $ids));

                $alreadySent = $owner->notifications()
                    ->where('type', LeadMergedNotification::class)
                    ->where('data->dedupe_key', $key)
                    ->exists();
                if ($alreadySent) {
                    continue;
                }

                $owner->notify(new LeadMergedNotification($primary, $key));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    });
}
}
