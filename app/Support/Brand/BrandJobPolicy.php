<?php

namespace App\Support\Brand;

/**
 * Explicit execution policy for company-touching jobs and scheduled commands.
 *
 * Classifications:
 * - oia_only: integrations / data that only exist for OIA (force BrandContext=oia)
 * - luxvant_only: Luxvant-specific (rare)
 * - per_brand: must run independently once per configured brand (never rely on default OIA)
 * - global_shared: truly cross-brand with evidence (infrastructure only)
 *
 * Unknown jobs must NOT be classified as global_shared without tracing data access.
 */
class BrandJobPolicy
{
    /**
     * @var array<string, string>
     */
    public const JOBS = [
        // CRM assignment / matching — brand-scoped records
        \App\Jobs\ProcessLeadAutoAssignmentJob::class => 'per_brand',
        \App\Jobs\ProcessLeadIntelligenceJob::class => 'per_brand',
        \App\Jobs\RecoverStuckLeadsJob::class => 'per_brand',
        \App\Jobs\MatchListingToLeadsJob::class => 'per_brand',
        \App\Jobs\RefreshLeadMatchesJob::class => 'per_brand',
        \App\Jobs\SendLeadAssignmentWebPush::class => 'per_brand',
        \App\Jobs\CheckSearchAlerts::class => 'per_brand',
        \App\Jobs\RecalculateSalesAgentScoreJob::class => 'per_brand',
        \App\Jobs\SendInvitationEmailJob::class => 'per_brand',
        \App\Jobs\AiSalesIntelligence\RecalculateAllAiSalesIntelligenceJob::class => 'per_brand',
        \App\Jobs\AiSalesIntelligence\RecalculateAiAgentIntelligenceJob::class => 'per_brand',
        \App\Jobs\AiSalesIntelligence\FinalizeAiSalesIntelligenceRankingsJob::class => 'per_brand',

        // OIA integrations — Bitrix / PropertyFinder are OIA production systems
        \App\Jobs\ProcessBitrixLeadJob::class => 'oia_only',
        \App\Jobs\SyncBitrix24LeadsJob::class => 'oia_only',
        \App\Jobs\SyncBitrix24ShardJob::class => 'oia_only',
        \App\Jobs\ProcessPropertyFinderEventJob::class => 'oia_only',
    ];

    /**
     * Artisan command names → policy.
     *
     * @var array<string, string>
     */
    public const COMMANDS = [
        'live-watch:cleanup' => 'global_shared', // TTL cleanup of ephemeral watch rows
        'activities:send-reminders' => 'per_brand',
        'marketing:send-reminders' => 'per_brand',
        'birthdays:celebrate' => 'per_brand',
        'matches:scan-quiet' => 'per_brand',
        'evaluations:check-due' => 'per_brand',
        'leads:score' => 'per_brand',
        'meta:refresh-app-token' => 'oia_only',
        'attendance:sync' => 'per_brand',
        'attendance:notify-absent' => 'per_brand',
        'leads:auto-assign' => 'per_brand',
        'leads:realtime-assign' => 'per_brand',
        'leads:merge-pool-duplicates' => 'per_brand',
        'propertyfinder:sync-leads' => 'oia_only',
        'leads:recover-stuck' => 'per_brand',
        'leads:sla-escalation' => 'per_brand',
        'lead-assignment:refresh-performance' => 'per_brand',
        'sales-intelligence:recalculate-scores' => 'per_brand',
        'ai-sales-intelligence:recalculate' => 'per_brand',
        'announcements:send-notifications' => 'per_brand',
        'passport:check-expiry' => 'per_brand',
        'interviews:send-reminders' => 'per_brand',
        'motivation:send' => 'per_brand',
        'leave:accrue-annual' => 'per_brand',
        'employees:check-document-expiry' => 'per_brand',
        'bitrix:compare-leads' => 'oia_only',
        'bitrix:sync-leads' => 'oia_only',
        'bitrix24:sync-leads-fast' => 'oia_only',
    ];

    public const ALLOWED_POLICIES = ['oia_only', 'luxvant_only', 'per_brand', 'global_shared'];

    public static function forJob(string $class): string
    {
        return self::JOBS[$class] ?? 'unknown';
    }

    public static function forCommand(string $name): string
    {
        // Strip options/args: "leads:auto-assign --scheduled-tick" → leads:auto-assign
        $base = explode(' ', trim($name), 2)[0];

        return self::COMMANDS[$base] ?? 'unknown';
    }

    /**
     * Fail closed: unclassified or invalid policies throw.
     */
    public static function requireCommand(string $name): string
    {
        $policy = self::forCommand($name);
        if ($policy === 'unknown' || ! in_array($policy, self::ALLOWED_POLICIES, true)) {
            \Illuminate\Support\Facades\Log::error('brand_job_policy.unclassified_command', [
                'command' => $name,
                'policy' => $policy,
            ]);
            throw new \InvalidArgumentException(
                "Unclassified brand command [{$name}]. Add an explicit BrandJobPolicy::COMMANDS entry."
            );
        }

        return $policy;
    }

    /**
     * Fail closed for queued job classes.
     */
    public static function requireJob(string $class): string
    {
        $policy = self::forJob($class);
        if ($policy === 'unknown' || ! in_array($policy, self::ALLOWED_POLICIES, true)) {
            \Illuminate\Support\Facades\Log::error('brand_job_policy.unclassified_job', [
                'job' => $class,
                'policy' => $policy,
            ]);
            throw new \InvalidArgumentException(
                "Unclassified brand job [{$class}]. Add an explicit BrandJobPolicy::JOBS entry."
            );
        }

        return $policy;
    }

    /**
     * @return list<string>
     */
    public static function configuredBrandKeys(): array
    {
        $brands = config('brands.brands', []);
        $keys = [];
        foreach ($brands as $key => $brand) {
            if (is_array($brand) && isset($brand['key'])) {
                $keys[] = (string) $brand['key'];
            } elseif (is_string($key)) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }
}
