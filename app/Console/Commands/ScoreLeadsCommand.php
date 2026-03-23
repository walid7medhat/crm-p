<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadScoringSetting;
use App\Services\LeadIntelligenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class ScoreLeadsCommand extends Command
{
    protected $signature = 'leads:score {--chunk=100} {--force}';

    protected $description = 'Recalculate intelligence score for all leads';

    public function handle(LeadIntelligenceService $service): int
    {
        $requiredColumns = ['score', 'priority', 'intent', 'next_action', 'last_scored_at', 'score_breakdown'];
        $missingColumns = [];
        foreach ($requiredColumns as $column) {
            if (!Schema::hasColumn('leads', $column)) {
                $missingColumns[] = $column;
            }
        }

        if (!empty($missingColumns)) {
            $this->error('Lead intelligence columns are missing: ' . implode(', ', $missingColumns));
            $this->warn('Run php artisan migrate after fixing earlier migration errors, then run leads:score again.');
            return self::FAILURE;
        }

        $settings = LeadScoringSetting::resolved();
        $automation = $settings['automation_flags'] ?? [];
        if (($automation['scheduled_enabled'] ?? true) === false && !$this->option('force')) {
            $this->info('Lead scoring is disabled by automation settings. Use --force to run manually.');
            return self::SUCCESS;
        }

        $chunkSize = (int) $this->option('chunk');
        $processed = 0;

        Lead::query()
            ->select(['id'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($leads) use ($service, &$processed) {
                foreach ($leads as $leadRow) {
                    $lead = Lead::find($leadRow->id);
                    if (!$lead) {
                        continue;
                    }

                    $result = $service->generateRecommendation($lead);
                    $lead->forceFill([
                        'score' => $result['score'],
                        'priority' => $result['priority'],
                        'intent' => $result['intent'],
                        'next_action' => $result['next_action'],
                        'last_scored_at' => now(),
                        'score_breakdown' => $result['score_breakdown'] ?? null,
                    ])->saveQuietly();

                    if (config('lead_intelligence.debug', false)) {
                        Log::info('lead_intelligence.scored_command', [
                            'lead_id' => $lead->id,
                            'score' => $result['score'] ?? null,
                            'priority' => $result['priority'] ?? null,
                            'intent' => $result['intent'] ?? null,
                            'reason' => $result['reason'] ?? null,
                            'next_action' => $result['next_action'] ?? null,
                            'risk' => $result['risk'] ?? null,
                            'score_breakdown' => $result['score_breakdown'] ?? null,
                        ]);
                    }

                    $processed++;
                }

                $this->info("Processed: {$processed}");
            });

        $this->info("Lead scoring completed. Total processed: {$processed}");

        return self::SUCCESS;
    }
}
