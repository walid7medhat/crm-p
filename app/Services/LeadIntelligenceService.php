<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadComment;
use App\Models\LeadScoringSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LeadIntelligenceService
{
    public function calculateScore(Lead $lead, ?array $settings = null): int
    {
        return $this->calculateScoreDetails($lead, $settings)['score'];
    }

    public function detectIntent(Lead $lead, ?array $settings = null): string
    {
        $resolved = $this->resolveSettings($settings);
        $aiMode = $resolved['ai_mode'] ?? 'fallback';
        $aiResult = $this->detectIntentWithAi($lead, $aiMode);
        if ($aiResult) {
            return $aiResult;
        }

        $haystack = Str::lower($this->buildLeadTextContext($lead));

        $highKeywords = ['urgent', 'ready', 'now'];
        $lowKeywords = ['just checking', 'info'];

        foreach ($highKeywords as $keyword) {
            if (Str::contains($haystack, $keyword)) {
                return 'high';
            }
        }

        foreach ($lowKeywords as $keyword) {
            if (Str::contains($haystack, $keyword)) {
                return 'low';
            }
        }

        return 'medium';
    }

    public function generateRecommendation(Lead $lead, ?array $settings = null): array
    {
        $resolved = $this->resolveSettings($settings);
        $scoreDetails = $this->calculateScoreDetails($lead, $resolved);
        $score = $scoreDetails['score'];
        $intent = $this->detectIntent($lead, $resolved);
        $priority = $this->classifyPriority($score, $resolved);

        $missingFields = [];
        foreach (['email', 'whatsapp_number', 'budget', 'lead_source'] as $field) {
            if (empty($lead->{$field})) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            $nextAction = 'collect missing info';
            $reason = 'Missing key data: ' . implode(', ', $missingFields);
        } elseif ($lead->created_at && $lead->created_at->gt(now()->subHours(48)) && $score >= 80) {
            $nextAction = 'call immediately';
            $reason = 'New lead with high score and strong buying signals';
        } elseif ($intent === 'high') {
            $nextAction = 'contact within 1 hour';
            $reason = 'Lead intent appears high from messages and metadata';
        } elseif ($priority === 'warm') {
            $nextAction = 'schedule follow-up today';
            $reason = 'Lead has medium potential and should be nurtured';
        } else {
            $nextAction = 'nurture with relevant info';
            $reason = 'Lead is lower priority and needs progressive engagement';
        }

        $risk = '';
        if ($priority === 'hot' && $lead->updated_at && $lead->updated_at->lt(now()->subDays(2))) {
            $risk = 'cooling down';
        }

        return [
            'score' => $score,
            'priority' => $priority,
            'intent' => $intent,
            'next_action' => $nextAction,
            'reason' => $reason,
            'risk' => $risk,
            'score_breakdown' => [
                'factors' => $scoreDetails['factors'],
                'total' => $score,
                'priority' => $priority,
                'intent' => $intent,
                'next_action' => $nextAction,
                'reason' => $reason,
                'risk' => $risk,
                'scored_at' => now()->toISOString(),
            ],
        ];
    }

    private function calculateScoreDetails(Lead $lead, ?array $settings = null): array
    {
        $resolved = $this->resolveSettings($settings);
        $weights = $resolved['weights'] ?? [];
        $rules = $resolved['rules'] ?? [];

        $budgetWeight = (int) ($weights['budget'] ?? 30);
        $whatsappWeight = (int) ($weights['whatsapp'] ?? 15);
        $emailWeight = (int) ($weights['email'] ?? 10);
        $sourceWeight = (int) ($weights['source'] ?? 10);
        $recencyWeight = (int) ($weights['recency'] ?? 20);
        $stageWeight = (int) ($weights['stage'] ?? 5);

        $highBudgetValue = (float) ($rules['budget_high_value'] ?? 1000000);
        $midBudgetValue = (float) ($rules['budget_mid_value'] ?? 300000);
        $recencyHours = (int) ($rules['recency_hours'] ?? 48);

        $score = 0;
        $factors = [];

        $budgetPoints = 0;
        if (!is_null($lead->budget) && (float) $lead->budget >= $highBudgetValue) {
            $budgetPoints = $budgetWeight;
        } elseif (!is_null($lead->budget) && (float) $lead->budget >= $midBudgetValue) {
            $budgetPoints = (int) round($budgetWeight * 0.66);
        } elseif (!is_null($lead->budget) && (float) $lead->budget > 0) {
            $budgetPoints = (int) round($budgetWeight * 0.33);
        }
        if ($budgetPoints > 0) {
            $score += $budgetPoints;
            $factors[] = ['rule' => 'budget', 'points' => $budgetPoints, 'value' => $lead->budget];
        }

        if (!empty($lead->whatsapp_number)) {
            $score += $whatsappWeight;
            $factors[] = ['rule' => 'has_whatsapp', 'points' => $whatsappWeight, 'value' => true];
        }

        if (!empty($lead->email)) {
            $score += $emailWeight;
            $factors[] = ['rule' => 'has_email', 'points' => $emailWeight, 'value' => true];
        }

        if ($lead->created_at && $lead->created_at->gt(now()->subHours($recencyHours))) {
            $score += $recencyWeight;
            $factors[] = ['rule' => 'recent_lead_48h', 'points' => $recencyWeight, 'value' => $lead->created_at->toISOString()];
        }

        if (Str::contains(Str::lower((string) $lead->lead_source), 'facebook')) {
            $score += $sourceWeight;
            $factors[] = ['rule' => 'facebook_source', 'points' => $sourceWeight, 'value' => $lead->lead_source];
        }

        if (!is_null($lead->stage_id)) {
            $score += $stageWeight;
            $factors[] = ['rule' => 'has_stage', 'points' => $stageWeight, 'value' => $lead->stage_id];
        }

        return [
            'score' => max(0, min(100, $score)),
            'factors' => $factors,
        ];
    }

    private function classifyPriority(int $score, ?array $settings = null): string
    {
        $resolved = $this->resolveSettings($settings);
        $thresholds = $resolved['thresholds'] ?? [];
        $hotThreshold = (int) ($thresholds['hot'] ?? 80);
        $warmThreshold = (int) ($thresholds['warm'] ?? 50);

        if ($score >= $hotThreshold) {
            return 'hot';
        }

        if ($score >= $warmThreshold) {
            return 'warm';
        }

        return 'cold';
    }

    private function detectIntentWithAi(Lead $lead, string $aiMode = 'fallback'): ?string
    {
        if ($aiMode === 'off') {
            return null;
        }

        $apiKey = config('services.openai.api_key');
        if (empty($apiKey)) {
            if ($aiMode === 'strict') {
                return 'medium';
            }
            return null;
        }

        try {
            $context = $this->buildLeadTextContext($lead);
            $response = Http::withToken($apiKey)
                ->timeout(8)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Analyze CRM lead intent. Reply with one word only: high, medium, or low.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $context,
                        ],
                    ],
                    'temperature' => 0,
                    'max_tokens' => 3,
                ]);

            $intent = Str::of((string) data_get($response->json(), 'choices.0.message.content'))
                ->lower()
                ->trim()
                ->value();

            if (in_array($intent, ['high', 'medium', 'low'], true)) {
                return $intent;
            }
        } catch (\Throwable $e) {
            if ($aiMode === 'strict') {
                return 'medium';
            }
            return null;
        }

        return null;
    }

    private function resolveSettings(?array $override = null): array
    {
        $defaults = [
            'weights' => config('lead_scoring.weights', []),
            'thresholds' => config('lead_scoring.thresholds', []),
            'rules' => config('lead_scoring.rules', []),
            'automation_flags' => config('lead_scoring.automation', []),
            'ai_mode' => config('lead_scoring.ai_mode', 'fallback'),
        ];

        $resolved = array_replace_recursive($defaults, LeadScoringSetting::resolved());
        if (is_array($override)) {
            $resolved = array_replace_recursive($resolved, $override);
        }

        return $resolved;
    }

    private function buildLeadTextContext(Lead $lead): string
    {
        $latestComment = LeadComment::where('lead_id', $lead->id)
            ->latest()
            ->value('comment');

        $rawMeta = is_array($lead->raw_meta_data)
            ? json_encode($lead->raw_meta_data)
            : (string) $lead->raw_meta_data;

        return implode("\n", [
            'Lead name: ' . (string) $lead->lead_name,
            'Lead source: ' . (string) $lead->lead_source,
            'Lead comment: ' . (string) $lead->comment,
            'Latest activity comment: ' . (string) $latestComment,
            'Raw metadata: ' . $rawMeta,
        ]);
    }
}
