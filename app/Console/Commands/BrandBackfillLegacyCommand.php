<?php

namespace App\Console\Commands;

use App\Support\Brand\BrandCrmScope;
use App\Support\Brand\BrandIsolation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time (re-runnable) brand classification of legacy rows.
 *
 *  1. Admin Luxvant tree leads/deals/users → luxvant (brand:classify-luxvant-crm --users).
 *  2. Remaining NULL brand_key rows → oia.
 *  3. Brand-owned settings with no Luxvant row → copied from OIA as Luxvant defaults
 *     (fixes empty kanban card fields etc. on the Luxvant host).
 *  4. lead_listing_matches / search_alerts brand_key from their lead / user.
 *
 * Always run with --dry-run first on production.
 */
class BrandBackfillLegacyCommand extends Command
{
    protected $signature = 'brand:backfill-legacy
                            {--dry-run : Show counts without writing}
                            {--without-luxvant-tree : Continue even if the Admin Luxvant tree is not found}';

    protected $description = 'Classify legacy NULL brand_key rows (Luxvant tree → luxvant, rest → oia) and seed Luxvant settings';

    /** Tables that carry brand_key (from the company isolation migration). */
    private const BRAND_TABLES = [
        'listings', 'gallery_images', 'listing_additional_documents', 'listing_comments',
        'listing_access_requests', 'property_offers', 'projects', 'project_images',
        'project_features', 'floor_plan_images', 'marketing_tasks', 'marketing_stages',
        'marketing_task_comments', 'marketing_task_histories', 'marketing_task_links',
        'marketing_bookings', 'marketing_calendar_posts', 'suggestions', 'suggestion_replies',
        'announcements', 'leads', 'deals', 'users', 'system_campaigns',
        'ai_sales_intelligence_settings', 'attendance_settings', 'city_investment_settings',
        'deal_cost_settings', 'document_expiry_settings', 'evaluation_settings',
        'kanban_settings', 'lead_assignment_settings', 'lead_scoring_settings',
        'live_watch_settings', 'motivation_settings', 'sales_intelligence_settings',
    ];

    /**
     * BrandOwned config tables: Luxvant gets a copy of OIA rows when it has none.
     * value = natural key column (null = copy all rows when Luxvant has zero).
     */
    private const COPY_TO_LUXVANT = [
        'kanban_settings' => 'key',
        'city_investment_settings' => 'city',
        'deal_cost_settings' => 'key',
        'lead_scoring_settings' => null,
        'lead_assignment_settings' => null,
        'sales_intelligence_settings' => null,
        'live_watch_settings' => null,
        'motivation_settings' => null,
        'evaluation_settings' => null,
        'ai_sales_intelligence_settings' => null,
        'marketing_stages' => null,
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // 1) Luxvant tree first (it only touches NULL / oia rows inside the tree).
        $this->info('Step 1 — classify Admin Luxvant tree');
        $classified = $this->call('brand:classify-luxvant-crm', array_filter([
            '--users' => true,
            '--dry-run' => $dry ?: null,
        ]));

        if ($classified !== self::SUCCESS && ! $this->option('without-luxvant-tree')) {
            $this->error('Luxvant tree classification failed — refusing to mark the rest as oia (Luxvant rows would be lost to OIA).');
            $this->line('Fix LUXVANT_CRM_BRANCH_USER_ID / "Admin Luxvant", or pass --without-luxvant-tree if this DB has no Luxvant team.');

            return self::FAILURE;
        }

        // 2) Everything still unclassified belongs to OIA.
        $this->info('Step 2 — remaining NULL brand_key → oia');
        $rows = [];
        foreach (self::BRAND_TABLES as $table) {
            if (! $this->ready($table)) {
                continue;
            }
            $q = DB::table($table)->whereNull('brand_key');
            $count = (clone $q)->count();
            if ($count > 0 && ! $dry) {
                $q->update(['brand_key' => 'oia']);
            }
            $rows[] = [$table, $count];
        }
        $this->table(['Table', 'NULL → oia'], $rows);

        // 3) Luxvant settings defaults.
        $this->info('Step 3 — seed Luxvant settings from OIA');
        $rows = [];
        foreach (self::COPY_TO_LUXVANT as $table => $naturalKey) {
            if (! $this->ready($table)) {
                continue;
            }
            $rows[] = [$table, $this->copyToLuxvant($table, $naturalKey, $dry)];
        }
        $this->table(['Table', 'Rows copied to luxvant'], $rows);

        // 4) Matching tables follow their owner.
        $this->info('Step 4 — matches / search alerts brand');
        $rows = [];
        if ($this->ready('lead_listing_matches')) {
            $rows[] = ['lead_listing_matches', $this->stampFrom('lead_listing_matches', 'lead_id', 'leads', $dry)];
        }
        if ($this->ready('search_alerts')) {
            $rows[] = ['search_alerts', $this->stampFrom('search_alerts', 'user_id', 'users', $dry)];
        }
        $this->table(['Table', 'Rows stamped'], $rows);

        // 5) Company-owned records follow the record / user they belong to.
        $this->info('Step 5 — owners, hot deals, investments, recruitment');
        $this->table(['Table', 'Rows stamped'], $this->stampOwnedRecords($dry));

        BrandCrmScope::reset();
        BrandIsolation::clearColumnCache();

        $dry ? $this->warn('Dry run — no changes written.') : $this->info('Done.');

        return self::SUCCESS;
    }

    private function ready(string $table): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, 'brand_key');
    }

    private function copyToLuxvant(string $table, ?string $naturalKey, bool $dry): int
    {
        $source = DB::table($table)->where(function ($q) {
            $q->where('brand_key', 'oia')->orWhereNull('brand_key');
        })->get();

        if ($naturalKey === null) {
            if (DB::table($table)->where('brand_key', 'luxvant')->exists()) {
                return 0;
            }
            $toCopy = $source;
        } else {
            $existing = DB::table($table)->where('brand_key', 'luxvant')->pluck($naturalKey)->all();
            $toCopy = $source->reject(fn ($r) => in_array($r->{$naturalKey}, $existing, true));
        }

        if ($dry) {
            return $toCopy->count();
        }

        foreach ($toCopy as $row) {
            $data = (array) $row;
            unset($data['id']);
            $data['brand_key'] = 'luxvant';
            if (array_key_exists('created_at', $data)) {
                $data['created_at'] = now();
            }
            if (array_key_exists('updated_at', $data)) {
                $data['updated_at'] = now();
            }
            DB::table($table)->insert($data);
        }

        return $toCopy->count();
    }

    /**
     * Ordered so children read their parent's already-stamped brand.
     * Each rule: [table, SQL expression giving the brand (NULL → oia)].
     *
     * @return list<array{0: string, 1: int}>
     */
    private function stampOwnedRecords(bool $dry): array
    {
        $rules = [
            // Owner: a Luxvant listing of this owner wins, then who added the owner.
            'owners' => "COALESCE(
                (SELECT MAX(l.brand_key) FROM listings l WHERE l.owner_id = owners.id AND l.brand_key = 'luxvant'),
                (SELECT u.brand_key FROM users u WHERE u.id = owners.added_by))",
            'owner_additional_documents' => '(SELECT o.brand_key FROM owners o WHERE o.id = owner_additional_documents.owner_id)',
            'hot_deal_requests' => "COALESCE(
                (SELECT l.brand_key FROM listings l WHERE l.id = hot_deal_requests.listing_id),
                (SELECT u.brand_key FROM users u WHERE u.id = hot_deal_requests.requested_by))",
            'investments' => '(SELECT u.brand_key FROM users u WHERE u.id = investments.user_id)',
            'investment_overrides' => '(SELECT i.brand_key FROM investments i WHERE i.id = investment_overrides.investment_id)',
            'investment_scenario_versions' => '(SELECT i.brand_key FROM investments i WHERE i.id = investment_scenario_versions.investment_id)',
            'job_openings' => '(SELECT u.brand_key FROM users u WHERE u.id = job_openings.hiring_manager_id)',
            'applicants' => '(SELECT j.brand_key FROM job_openings j WHERE j.id = applicants.job_id)',
            'interviews' => "COALESCE(
                (SELECT a.brand_key FROM applicants a WHERE a.id = interviews.applicant_id),
                (SELECT j.brand_key FROM job_openings j WHERE j.id = interviews.job_id))",
        ];

        $rows = [];
        foreach ($rules as $table => $expr) {
            if (! $this->ready($table)) {
                continue;
            }

            $count = DB::table($table)->whereNull('brand_key')->count();
            if (! $dry && $count > 0) {
                DB::table($table)->whereNull('brand_key')->update([
                    'brand_key' => DB::raw("COALESCE({$expr}, 'oia')"),
                ]);
            }
            $rows[] = [$table, $count];
        }

        return $rows;
    }

    private function stampFrom(string $table, string $fk, string $parent, bool $dry): int
    {
        $q = DB::table($table)
            ->join($parent, "{$parent}.id", '=', "{$table}.{$fk}")
            ->whereNull("{$table}.brand_key");

        $count = (clone $q)->count();
        if ($dry || $count === 0) {
            return $count;
        }

        DB::table($table)
            ->whereNull('brand_key')
            ->update([
                'brand_key' => DB::raw("COALESCE((SELECT {$parent}.brand_key FROM {$parent} WHERE {$parent}.id = {$table}.{$fk}), 'oia')"),
            ]);

        return $count;
    }
}
