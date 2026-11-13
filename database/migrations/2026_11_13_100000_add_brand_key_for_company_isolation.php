<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive company isolation columns only.
 *
 * IMPORTANT:
 * - Does NOT backfill brand_key (backfill requires separate production
 *   classification approval — never blind OIA assignment).
 * - Safe to add on deploy/luxvant-release; do not run on staging/production
 *   until explicitly authorized after the classification package.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'listings',
        'gallery_images',
        'listing_additional_documents',
        'listing_comments',
        'listing_access_requests',
        'property_offers',
        'projects',
        'project_images',
        'project_features',
        'floor_plan_images',
        'marketing_tasks',
        'marketing_stages',
        'marketing_task_comments',
        'marketing_task_histories',
        'marketing_task_links',
        'marketing_bookings',
        'marketing_calendar_posts',
        'suggestions',
        'suggestion_replies',
        'announcements',
        'leads',
        'deals',
        'users',
        'system_campaigns',
        'ai_sales_intelligence_settings',
        'attendance_settings',
        'city_investment_settings',
        'deal_cost_settings',
        'document_expiry_settings',
        'evaluation_settings',
        'kanban_settings',
        'lead_assignment_settings',
        'lead_scoring_settings',
        'live_watch_settings',
        'motivation_settings',
        'sales_intelligence_settings',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, 'brand_key')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->string('brand_key', 32)->nullable()->after('id');
                $blueprint->index('brand_key', $this->indexName($table));
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'brand_key')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex($this->indexName($table));
                $blueprint->dropColumn('brand_key');
            });
        }
    }

    private function indexName(string $table): string
    {
        return $table.'_brand_key_index';
    }
};
