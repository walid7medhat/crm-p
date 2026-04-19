<?php

namespace App\Services;

use App\Models\AssignmentPattern;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadAssignmentLog;
use App\Models\LeadAssignmentSetting;
use App\Models\SalesTemporalStat;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Support\Collection;

class LeadAssignmentLearningService
{
    protected float $alpha = 0.1;

    /**
     * @return array{source: string, budget_range: string, property_type: string, nationality: string, intent: string}
     */
    public function contextAttributes(Lead $lead): array
    {
        $lead->loadMissing('propertyType:id,name');

        $budget = (float) ($lead->budget ?? 0);
        if ($budget <= 0 && ($lead->budget_from || $lead->budget_to)) {
            $budget = (float) ($lead->budget_to ?? $lead->budget_from ?? 0);
        }
        $budgetRange = $this->budgetRangeLabel($budget);

        $prop = strtolower(trim((string) ($lead->propertyType->name ?? ''))) ?: 'unknown';

        return [
            'source' => strtolower(trim((string) ($lead->lead_source ?? ''))) ?: 'unknown',
            'budget_range' => $budgetRange,
            'property_type' => substr($prop, 0, 120),
            'nationality' => strtolower(trim((string) ($lead->nationality ?? ''))) ?: 'unknown',
            'intent' => strtolower(trim((string) ($lead->intent ?? $lead->priority ?? ''))) ?: 'unknown',
        ];
    }

    public function contextFingerprint(Lead $lead): string
    {
        $c = $this->contextAttributes($lead);

        return sha1(implode('|', [
            $c['source'],
            $c['budget_range'],
            $c['property_type'],
            $c['nationality'],
            $c['intent'],
        ]));
    }

    /** @deprecated use contextFingerprint */
    public function leadTypeKey(Lead $lead): string
    {
        return $this->contextFingerprint($lead);
    }

    protected function budgetRangeLabel(float $budget): string
    {
        if ($budget <= 0) {
            return 'unknown';
        }
        if ($budget < 500_000) {
            return '0-500k';
        }
        if ($budget < 1_500_000) {
            return '500k-1.5m';
        }
        if ($budget < 5_000_000) {
            return '1.5m-5m';
        }

        return '5m+';
    }

    public function globalAverageSuccessForContext(string $fingerprint): float
    {
        $v = AssignmentPattern::query()
            ->where('context_fingerprint', $fingerprint)
            ->avg('success_rate');

        return $v !== null ? (float) $v : 0.5;
    }

    /**
     * Cold-start blended success then mapped to score bump.
     */
    public function patternBoost(?AssignmentPattern $row, float $globalAvg, int $coldMaxSamples): float
    {
        $globalAvg = max(0.02, min(0.98, $globalAvg));
        $rate = $row ? (float) $row->success_rate : $globalAvg;
        $samples = $row ? (int) $row->samples : 0;
        $thresh = max(1, $coldMaxSamples);

        if ($samples < $thresh) {
            $rate = ($samples * $rate + ($thresh - $samples) * $globalAvg) / $thresh;
        }

        return max(-0.08, min(0.12, ($rate - 0.5) * 0.28));
    }

    public function estimateCloseProbability(
        ?AssignmentPattern $pattern,
        float $perfRaw,
        float $skillBoost,
        float $patternBoost,
        float $globalAvg,
        int $coldMaxSamples,
        float $temporalBoost = 0.0,
    ): float {
        $rate = $pattern ? (float) $pattern->success_rate : $globalAvg;
        $samples = $pattern ? (int) $pattern->samples : 0;
        $thresh = max(1, $coldMaxSamples);
        if ($samples < $thresh) {
            $rate = ($samples * $rate + ($thresh - $samples) * $globalAvg) / $thresh;
        }

        $p = 0.22 + 0.48 * max(0.0, min(1.0, $rate)) + 0.22 * max(0.0, min(1.0, $perfRaw));
        $p += min(0.06, max(0.0, $skillBoost) * 0.35);
        $p += min(0.04, max(0.0, $patternBoost));
        $p += min(0.04, $temporalBoost);

        return round(max(0.05, min(0.94, $p)), 4);
    }

    /**
     * @param  Collection<int, UserSkill>|null  $userSkills
     */
    public function skillBoostForUser(User $user, Lead $lead, ?Collection $userSkills): float
    {
        if (!$userSkills || $userSkills->isEmpty()) {
            return 0.0;
        }

        $blob = strtolower(implode(' ', array_filter([
            (string) ($lead->lead_source ?? ''),
            (string) ($lead->priority ?? ''),
            (string) ($lead->intent ?? ''),
            (string) ($lead->interested_in ?? ''),
            (string) ($lead->purpose_buying ?? ''),
            (string) ($lead->nationality ?? ''),
        ])));

        if ($blob === '') {
            return 0.0;
        }

        foreach ($userSkills as $row) {
            $s = strtolower(trim((string) ($row->skill ?? '')));
            if ($s === '') {
                continue;
            }
            if (str_contains($blob, $s)) {
                return 0.11;
            }
        }

        return 0.0;
    }

    public function recordDealOutcome(Deal $deal): void
    {
        if (!$deal->lead_id || !$deal->responsible_person_id) {
            return;
        }

        $lead = Lead::query()->find($deal->lead_id);
        if (!$lead) {
            return;
        }

        $fp = $this->contextFingerprint($lead);
        $ctx = $this->contextAttributes($lead);
        $salesId = (int) $deal->responsible_person_id;

        $pattern = AssignmentPattern::query()->firstOrNew([
            'sales_id' => $salesId,
            'context_fingerprint' => $fp,
        ]);
        $pattern->context_source = $ctx['source'];
        $pattern->context_budget_range = $ctx['budget_range'];
        $pattern->context_property_type = $ctx['property_type'];
        $pattern->context_nationality = $ctx['nationality'];
        $pattern->context_intent = $ctx['intent'];

        $oldRate = $pattern->exists ? (float) $pattern->success_rate : 0.5;
        $a = $this->alpha;

        if ($deal->status === 'completed') {
            $pattern->success_rate = $oldRate * (1 - $a) + 1.0 * $a;
            $hours = null;
            try {
                if ($lead->created_at && $deal->updated_at) {
                    $hours = max(0.01, $lead->created_at->diffInMinutes($deal->updated_at) / 60);
                }
            } catch (\Throwable) {
                $hours = null;
            }
            if ($hours !== null) {
                $pattern->avg_close_time_hours = $pattern->avg_close_time_hours
                    ? round((float) $pattern->avg_close_time_hours * 0.82 + $hours * 0.18, 4)
                    : round($hours, 4);
            }

            $log = LeadAssignmentLog::query()
                ->where('lead_id', $lead->id)
                ->where('assigned_to', $salesId)
                ->orderByDesc('id')
                ->first();
            if ($log?->created_at) {
                SalesTemporalStat::recordWin($salesId, $log->created_at);
            } else {
                SalesTemporalStat::recordWin($salesId, $deal->updated_at);
            }
        } elseif ($deal->status === 'cancelled') {
            $pattern->success_rate = $oldRate * (1 - $a) + 0.0 * $a;
        } else {
            return;
        }

        $pattern->samples = (int) ($pattern->samples ?? 0) + 1;
        $pattern->save();
    }

    public function applyAdaptiveFactorNudge(Deal $deal): void
    {
        if (!$deal->lead_id || !$deal->responsible_person_id) {
            return;
        }

        $settings = LeadAssignmentSetting::query()->orderBy('id')->first();
        if (!$settings || !$settings->adaptive_weights_enabled) {
            return;
        }

        $log = LeadAssignmentLog::query()
            ->where('lead_id', $deal->lead_id)
            ->where('assigned_to', $deal->responsible_person_id)
            ->orderByDesc('id')
            ->first();
        if (!$log || !$log->dominant_factor) {
            return;
        }

        $step = $deal->status === 'completed' ? 0.014 : -0.01;
        $f = strtolower((string) $log->dominant_factor);
        $fa = (float) ($settings->factor_weight_attendance ?? 0.3333);
        $fp = (float) ($settings->factor_weight_performance ?? 0.3333);
        $fk = (float) ($settings->factor_weight_skill ?? 0.3334);

        if (str_contains($f, 'att')) {
            $fa += $step;
        } elseif (str_contains($f, 'skill')) {
            $fk += $step;
        } else {
            $fp += $step;
        }

        $fa = max(0.12, min(0.55, $fa));
        $fp = max(0.12, min(0.55, $fp));
        $fk = max(0.12, min(0.55, $fk));
        $t = $fa + $fp + $fk;
        $settings->factor_weight_attendance = round($fa / $t, 4);
        $settings->factor_weight_performance = round($fp / $t, 4);
        $settings->factor_weight_skill = round(1 - $settings->factor_weight_attendance - $settings->factor_weight_performance, 4);
        $settings->save();
    }
}
