<?php
namespace App\Services;

use App\Helpers\LeadHistoryHelper;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadMergeService
{
    /** The lead + its visible duplicates (merged leads are excluded by the global scope). */
    public function group(Lead $lead, User $user): Collection
        {
            $digits = Lead::phoneDigits($lead->work_phone);
            if ($digits === '') return collect([$lead]);

            $byBranch = (bool) config('lead_merge.group_by_branch');
            $key = '';
            if ($byBranch) {
                $lead->loadMissing(['responsiblePerson', 'addedBy']);
                $key = $lead->branchKey();
            }

            return Lead::query()
                ->with(['stage:id,name', 'responsiblePerson:id,name,display_name,avatar,parent_id', 'addedBy', 'propertyType:id,name', 'area:id,name'])
                ->where('work_phone_digits', $digits)
                ->orderBy('id')->get()
                ->filter(function ($l) use ($lead, $user, $byBranch, $key) {
                    if ($l->id === $lead->id) return true;
                    if ($byBranch && ! $lead->sameBranchAs($l)) return false;
                    return $user->canViewLead($l);
                })
                ->values();
        }

    /** @return array{eligible:Collection, ineligible:Collection} ineligible = [['lead'=>..,'reason'=>..]] */
   public function classify(Lead $default, Collection $others): array
    {
        $pool     = (int) config('lead_merge.lead_pool_stage_id');
        $allowed  = [(int) config('lead_merge.unqualified_stage_id'), (int) config('lead_merge.lost_stage_id')];
        $digits   = Lead::phoneDigits($default->work_phone);
        $byBranch = (bool) config('lead_merge.group_by_branch');

        $defKey = '';
        if ($byBranch) {
            $default->loadMissing(['responsiblePerson', 'addedBy']);
            $defKey = $default->branchKey();
        }

        $eligible = collect();
        $ineligible = collect();

        foreach ($others as $l) {
            if ($l->id === $default->id) continue;
            if ($byBranch) $l->loadMissing(['responsiblePerson', 'addedBy']);

            $reason = match (true) {
                $digits === '' || Lead::phoneDigits($l->work_phone) !== $digits
                    => 'Not a duplicate of the default lead.',
                $byBranch && ! $default->sameBranchAs($l)
                     => 'Lead belongs to a different branch.',
                (int) $default->stage_id === $pool
                    => 'Lead Pool leads are merged automatically (daily) and only with other Lead Pool leads.',
                (int) $l->stage_id === $pool
                    => 'Lead Pool leads cannot be merged into a lead from another stage.',
                (bool) $l->converted_to_deal_id
                    => 'Converted leads cannot be merged.',
                ! in_array((int) $l->stage_id, $allowed, true)
                    => 'Only Unqualified or Lost leads can be merged into the default lead.',
                default => null,
            };

            $reason
                ? $ineligible->push(['lead' => $l, 'reason' => $reason])
                : $eligible->push($l);
        }

        return compact('eligible', 'ineligible');
    }

    /** @return array{default:Lead, merged_ids:int[], skipped:array} */
    public function merge(int $defaultId, array $leadIds, User $user): array
    {
        $otherIds = collect($leadIds)->map(fn ($i) => (int) $i)->unique()
            ->reject(fn ($i) => $i === $defaultId)->sort()->values();

        if ($otherIds->isEmpty()) {
            throw ValidationException::withMessages(['lead_ids' => 'Select at least one lead to merge.']);
        }

        $result = DB::transaction(function () use ($defaultId, $otherIds, $user) {
            $lockIds = $otherIds->push($defaultId)->sort()->values();   // sorted = no deadlocks
            $locked = Lead::query()->whereIn('id', $lockIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            // A lead missing here is already merged (global scope) or deleted.
            if ($locked->count() !== $lockIds->count()) {
                throw ValidationException::withMessages(['lead_ids' => 'One of the leads was already merged or no longer exists.']);
            }

            $default = $locked->get($defaultId);
            abort_unless($user->canViewLead($default), 403, 'You are not authorized to merge into this lead.');

            $others = $otherIds->filter(fn ($i) => $i !== $defaultId)->map(fn ($i) => $locked->get($i));
            foreach ($others as $o) {
                abort_unless($user->canViewLead($o), 403, 'You are not authorized to merge one of the leads.');
            }

            ['eligible' => $eligible, 'ineligible' => $ineligible] = $this->classify($default, $others);
            if ($eligible->isEmpty()) {
                throw ValidationException::withMessages(['lead_ids' => 'None of the selected leads can be merged.']);
            }
            $ids = $eligible->pluck('id')->all();

            // Flatten chains, then merge. Query-builder update = no model events fired.
            Lead::withoutGlobalScope('notMerged')->whereIn('merged_into_lead_id', $ids)
                ->update(['merged_into_lead_id' => $default->id]);

            Lead::query()->whereIn('id', $ids)->whereNull('merged_into_lead_id')->update([
                'merged_into_lead_id'      => $default->id,
                'merged_at'                => now(),
                'merged_by'                => $user->id,
                'merged_original_stage_id' => DB::raw('stage_id'),
                'merge_type'               => 'manual',
            ]);

            LeadHistoryHelper::log($default->id, [
                'action' => 'merged', 'merge_type' => 'manual', 'merged_ids' => $ids, 'merged_by' => $user->id,
            ]);

            return [
                'default' => $default, 'merged_ids' => $ids,
                'skipped' => $ineligible->map(fn ($r) => ['id' => $r['lead']->id, 'reason' => $r['reason']])->all(),
            ];
        });

        LeadKanbanHeatCache::bump();
        return $result;
    }
}