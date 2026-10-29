<?php
namespace App\Services;

/**
 * Backward-compatible entry for pool duplicate merging.
 * Cross-stage Rule A (Pool + Unqualified + Lost) lives in EligibleStagesAutoMergeService.
 */
class LeadPoolMergeService
{
    public function __construct(private EligibleStagesAutoMergeService $auto) {}

    /** @return array<int,int[]> default_id => merged ids */
    public function mergeDigits(string $digits): array
    {
        return $this->auto->mergeEligibleContact($digits);
    }
}
