<?php

namespace App\Services\AiLeadIntelligence;

/**
 * API contract shapes for AI Lead Intelligence.
 * Phase 3 fills these with deterministic CRM aggregation (no LLM).
 */
class AiLeadIntelligenceContract
{
    public const PHASE = 'phase_3';

    public const STATUS_NOT_AVAILABLE = 'not_available';
    public const STATUS_NOT_STARTED = 'not_started';
    public const STATUS_LOADING = 'loading';
    public const STATUS_READY = 'ready';
    public const STATUS_EMPTY = 'empty';
    public const STATUS_ERROR = 'error';
    public const STATUS_REFRESHING = 'refreshing';
    public const STATUS_PREVIEW = 'preview';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';

    public static function emptyNotStarted(?string $message = null): array
    {
        $message ??= 'No CRM intelligence snapshot yet. Click Refresh Analysis to queue aggregation.';

        return [
            'phase' => self::PHASE,
            'analysis_status' => self::STATUS_NOT_STARTED,
            'run_status' => self::STATUS_NOT_STARTED,
            'last_updated_at' => null,
            'message' => $message,
            'brief' => self::briefSection(),
            'priority_leads' => self::listSection(),
            'at_risk' => self::listSection(),
            'property_opportunities' => self::propertyOpportunitiesSection(),
            'neglected' => self::neglectedSection(),
            'actions_today' => self::listSection(),
            'insight_preview' => [
                'status' => self::STATUS_PREVIEW,
                'text' => null,
                'disclaimer' => 'Phase 3 CRM aggregation has not run yet. Phase 4 will add structured AI insights.',
            ],
        ];
    }

    /** @deprecated Use emptyNotStarted — kept for reference during migration */
    public static function overviewPrototype(?string $message = null): array
    {
        return self::emptyNotStarted($message);
    }

    public static function briefSection(): array
    {
        return [
            'summary' => null,
            'summary_status' => self::STATUS_NOT_AVAILABLE,
            'cards' => [
                self::metricCard('high_priority', 'High Priority', 'priority'),
                self::metricCard('at_risk', 'At Risk', 'risk'),
                self::metricCard('property_opportunities', 'Property Opportunities', 'property'),
                self::metricCard('neglected', 'Neglected', 'neglected'),
            ],
        ];
    }

    public static function metricCard(string $key, string $label, string $icon): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'count' => null,
            'status' => self::STATUS_NOT_AVAILABLE,
        ];
    }

    public static function metricCardReady(string $key, string $label, string $icon, int $count): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'count' => $count,
            'status' => self::STATUS_READY,
        ];
    }

    public static function listSection(): array
    {
        return [
            'status' => self::STATUS_NOT_AVAILABLE,
            'items' => [],
            'meta' => ['total' => null],
        ];
    }

    public static function propertyOpportunitiesSection(): array
    {
        return [
            'status' => self::STATUS_NOT_AVAILABLE,
            'items' => [],
            'gaps' => [],
            'meta' => ['total' => null, 'gap_total' => null],
        ];
    }

    public static function neglectedSection(): array
    {
        return [
            'status' => self::STATUS_NOT_AVAILABLE,
            'groups' => [
                'critical' => self::neglectGroup(),
                'needs_attention' => self::neglectGroup(),
                'monitor' => self::neglectGroup(),
            ],
        ];
    }

    public static function neglectGroup(): array
    {
        return [
            'status' => self::STATUS_NOT_AVAILABLE,
            'count' => null,
            'items' => [],
        ];
    }
}
