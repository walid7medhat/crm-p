<?php

namespace App\Observers\AiSalesIntelligence;

use App\Jobs\AiSalesIntelligence\RecalculateAiAgentIntelligenceJob;
use App\Models\AiSalesIntelligence\AiSalesIntelligenceSetting;

class AiSalesIntelligenceDispatcher
{
    public static function dispatchForUser(?int $userId, int $delaySeconds = 25): void
    {
        if (!$userId || !self::automationEnabled()) {
            return;
        }

        RecalculateAiAgentIntelligenceJob::dispatch($userId)->delay(now()->addSeconds($delaySeconds));
    }

    public static function automationEnabled(): bool
    {
        try {
            $flags = AiSalesIntelligenceSetting::current()->automation_flags ?? [];

            return ($flags['recalculate_on_lead_change'] ?? true) === true;
        } catch (\Throwable) {
            return true;
        }
    }

    public static function activityEnabled(): bool
    {
        try {
            $flags = AiSalesIntelligenceSetting::current()->automation_flags ?? [];

            return ($flags['recalculate_on_activity'] ?? true) === true;
        } catch (\Throwable) {
            return true;
        }
    }

    public static function dealEnabled(): bool
    {
        try {
            $flags = AiSalesIntelligenceSetting::current()->automation_flags ?? [];

            return ($flags['recalculate_on_deal_close'] ?? true) === true;
        } catch (\Throwable) {
            return true;
        }
    }

    public static function commentEnabled(): bool
    {
        try {
            $flags = AiSalesIntelligenceSetting::current()->automation_flags ?? [];

            return ($flags['recalculate_on_comment'] ?? true) === true;
        } catch (\Throwable) {
            return true;
        }
    }
}
