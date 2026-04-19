<?php

namespace App\Observers;

use App\Models\Deal;
use App\Services\LeadAssignmentLearningService;
use App\Services\LeadAssignmentService;

class DealAssignmentLearningObserver
{
    public function created(Deal $deal): void
    {
        if (!in_array($deal->status, ['completed', 'cancelled'], true)) {
            return;
        }

        $this->syncLearning($deal);
    }

    public function updated(Deal $deal): void
    {
        if (!$deal->wasChanged('status')) {
            return;
        }

        if (!in_array($deal->status, ['completed', 'cancelled'], true)) {
            return;
        }

        $this->syncLearning($deal);
    }

    protected function syncLearning(Deal $deal): void
    {
        try {
            app(LeadAssignmentLearningService::class)->recordDealOutcome($deal);
        } catch (\Throwable) {
            // non-fatal
        }

        try {
            app(LeadAssignmentLearningService::class)->applyAdaptiveFactorNudge($deal);
        } catch (\Throwable) {
            // non-fatal
        }

        try {
            app(LeadAssignmentService::class)->refreshSalesPerformanceSnapshot();
        } catch (\Throwable) {
            // non-fatal
        }
    }
}
