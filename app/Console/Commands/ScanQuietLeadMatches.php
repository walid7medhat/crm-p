<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\User;
use App\Services\Matching\LeadMatcher;
use App\Services\Matching\MatchNotifier;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Morning pass for Match Studio: re-match every working lead nobody has touched for
 * LeadMatcher::QUIET_DAYS against current stock, then send each agent one digest.
 */
class ScanQuietLeadMatches extends Command
{
    protected $signature = 'matches:scan-quiet {--no-notify : Store matches without notifying agents}';

    protected $description = 'Find new listing matches for quiet leads and send each agent a morning digest';

    public function handle(LeadMatcher $matcher, MatchNotifier $notifier): int
    {
        $cutoff = now()->subDays(LeadMatcher::QUIET_DAYS);
        $byAgent = [];
        $scanned = 0;

        Lead::query()
            ->whereNull('archived_at')
            ->whereIn('stage_id', $matcher->workingStageIds())
            ->where('responsible_person_id', '!=', 1)
            ->where(function (Builder $q) use ($cutoff) {
                $q->where('last_engagement_at', '<', $cutoff)
                    ->orWhere(fn (Builder $missing) => $missing->whereNull('last_engagement_at')
                        ->whereRaw('COALESCE(last_stage_change_at, created_at) < ?', [$cutoff]));
            })
            ->chunkById(200, function ($leads) use ($matcher, &$byAgent, &$scanned) {
                foreach ($leads as $lead) {
                    $scanned++;
                    $rows = $matcher->refreshLead($lead);
                    if ($rows->isNotEmpty()) {
                        $agentId = (int) $lead->responsible_person_id;
                        $byAgent[$agentId] = ($byAgent[$agentId] ?? collect())->merge($rows);
                    }
                }
            });

        if (! $this->option('no-notify')) {
            foreach ($byAgent as $agentId => $rows) {
                if ($agent = User::query()->find($agentId)) {
                    $notifier->quietDigest($agent, $rows);
                }
            }
        }

        $this->info("Scanned {$scanned} quiet leads; ".count($byAgent).' agents have new matches.');

        return self::SUCCESS;
    }
}
