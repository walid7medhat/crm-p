<?php
namespace App\Services;

use App\Helpers\LeadHistoryHelper;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

class LeadPoolMergeService
{
    /** @return array<int,int[]> default_id => merged ids */
    public function mergeDigits(string $digits): array
    {
        if ($digits === '') return [];
        $pool = (int) config('lead_merge.lead_pool_stage_id');
        $result = [];

        DB::transaction(function () use ($digits, $pool, &$result) {
            // Only CURRENT stage-10 rows (locked). Newest first.
            // Global scope already excludes leads that are merged.
            $leads = Lead::query()
                ->where('work_phone_digits', $digits)
                ->where('stage_id', $pool)
                ->whereNull('converted_to_deal_id')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            if ($leads->count() < 2) return;


            $byBranch = (bool) config('lead_merge.group_by_branch');

            $groups = $byBranch
                ? $leads->load(['responsiblePerson', 'addedBy'])->groupBy(fn ($l) => $l->branchKey())
                : collect([$leads]);


            foreach ($groups as $group) {
                if ($group->count() < 2) continue;

                $default = $group->first();                      // newest
                $ids = $group->skip(1)->pluck('id')->all();      // older pool leads

                // Flatten: leads merged into an older default now point to the new default.
                Lead::withoutGlobalScope('notMerged')
                    ->whereIn('merged_into_lead_id', $ids)
                    ->update(['merged_into_lead_id' => $default->id]);

                $affected = Lead::query()
                    ->whereIn('id', $ids)
                    ->where('stage_id', $pool)                   // re-check on write
                    ->whereNull('merged_into_lead_id')
                    ->update([
                        'merged_into_lead_id'      => $default->id,
                        'merged_at'                => now(),
                        'merged_by'                => null,
                        'merged_original_stage_id' => $pool,
                        'merge_type'               => 'pool_auto',
                    ]);

                if ($affected) {
                    LeadHistoryHelper::log($default->id, [
                        'action' => 'merged', 'merge_type' => 'pool_auto', 'merged_ids' => $ids,
                    ]);
                    $result[$default->id] = $ids;
                }
            }
        });

        if ($result) LeadKanbanHeatCache::bump();
        return $result;
    }

    private function branchKey(Lead $l): string
    {
        $branch = $l->lead_branch_source
            ?: ($l->responsiblePerson ?? $l->addedBy)?->admin_parent?->name;
        return mb_strtolower((string) $branch);
    }
}