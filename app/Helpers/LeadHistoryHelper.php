<?php

namespace App\Helpers;

use App\Models\LeadHistory;

class LeadHistoryHelper
{
    
    public static function log(
        int $leadId,
        array $changes = [],
        ?int $userId = null
    ): void {
        if (empty($changes)) {
            return;
        }

        LeadHistory::create([
            'lead_id' => $leadId,
            'user_id' => $userId ?? auth()->id(),
            'changes' => $changes,
        ]);
    }

    /**
     * Merge history on both sides: "merged" on the primary, "merged_into" on each merged lead.
     *
     * @param int[] $mergedIds
     */
    public static function logMerge(int $primaryId, array $mergedIds, string $mergeType, ?int $userId = null): void
    {
        $mergedIds = array_values(array_unique(array_map('intval', $mergedIds)));
        if ($mergedIds === []) {
            return;
        }

        $extra = $userId ? ['merged_by' => $userId] : [];

        self::log($primaryId, [
            'action' => 'merged',
            'merge_type' => $mergeType,
            'merged_ids' => $mergedIds,
        ] + $extra, $userId);

        foreach ($mergedIds as $id) {
            self::log($id, [
                'action' => 'merged_into',
                'merge_type' => $mergeType,
                'primary_id' => $primaryId,
            ] + $extra, $userId);
        }
    }
}
