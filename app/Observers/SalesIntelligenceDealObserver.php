<?php

namespace App\Observers;

use App\Jobs\RecalculateSalesAgentScoreJob;
use App\Models\Deal;
use App\Models\SalesIntelligenceSetting;

class SalesIntelligenceDealObserver
{
    public function updated(Deal $deal): void
    {
        if (!$deal->wasChanged('status')) {
            return;
        }

        if (!in_array($deal->status, ['completed', 'cancelled'], true)) {
            return;
        }

        if (!$this->automationEnabled('recalculate_on_deal_close')) {
            return;
        }

        if ($deal->responsible_person_id) {
            RecalculateSalesAgentScoreJob::dispatch((int) $deal->responsible_person_id);
        }
    }

    protected function automationEnabled(string $key): bool
    {
        try {
            $flags = SalesIntelligenceSetting::current()->automation_flags ?? [];

            return ($flags[$key] ?? true) === true;
        } catch (\Throwable) {
            return true;
        }
    }
}
