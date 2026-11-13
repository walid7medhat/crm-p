<?php

namespace App\Console\Commands;

use App\Support\Brand\BrandCrmScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Classify Admin Luxvant CRM branch rows with brand_key = luxvant.
 *
 * Safe to re-run. Does not touch OIA-owned rows outside the branch tree.
 * Prefer --dry-run on production first.
 */
class ClassifyLuxvantCrmBrandCommand extends Command
{
    protected $signature = 'brand:classify-luxvant-crm
                            {--dry-run : Show counts without writing}
                            {--users : Also stamp users in the CRM branch tree}';

    protected $description = 'Set brand_key=luxvant on Admin Luxvant branch leads/deals (and optional users)';

    public function handle(): int
    {
        BrandCrmScope::reset();

        $root = BrandCrmScope::resolveConfiguredBranchRoot();
        if (! $root) {
            $this->error('Luxvant CRM branch root not found. Set LUXVANT_CRM_BRANCH_USER_ID or ensure a user named "Admin Luxvant" exists.');

            return self::FAILURE;
        }

        $ids = BrandCrmScope::resolveConfiguredBranchUserIds();
        if ($ids === []) {
            $this->error("Branch root #{$root->id} ({$root->name}) resolved but subordinate id list is empty.");

            return self::FAILURE;
        }

        $this->info("Branch root: #{$root->id} {$root->name} (".count($ids).' users in tree)');
        $dry = (bool) $this->option('dry-run');

        $leadQuery = DB::table('leads')->where(function ($q) use ($ids, $root) {
            $q->whereIn('responsible_person_id', $ids)
                ->orWhereIn('added_by', $ids)
                ->orWhere('lead_branch_source', $root->name);
        })->where(function ($q) {
            $q->whereNull('brand_key')->orWhere('brand_key', 'oia');
        });

        $dealQuery = Schema::hasColumn('deals', 'brand_key')
            ? DB::table('deals')->whereIn('responsible_person_id', $ids)
                ->where(function ($q) {
                    $q->whereNull('brand_key')->orWhere('brand_key', 'oia');
                })
            : null;

        $leadCount = (clone $leadQuery)->count();
        $dealCount = $dealQuery ? (clone $dealQuery)->count() : 0;
        $userCount = 0;

        if ($this->option('users') && Schema::hasColumn('users', 'brand_key')) {
            $userCount = DB::table('users')
                ->whereIn('id', $ids)
                ->where(function ($q) {
                    $q->whereNull('brand_key')->orWhere('brand_key', 'oia');
                })
                ->count();
        }

        $this->table(
            ['Entity', 'Rows to classify as luxvant'],
            [
                ['leads', $leadCount],
                ['deals', $dealCount],
                ['users', $this->option('users') ? $userCount : '(skipped)'],
            ]
        );

        if ($dry) {
            $this->warn('Dry run — no changes written.');

            return self::SUCCESS;
        }

        $now = now();
        $updatedLeads = $leadQuery->update(['brand_key' => 'luxvant', 'updated_at' => $now]);
        $updatedDeals = $dealQuery ? $dealQuery->update(['brand_key' => 'luxvant', 'updated_at' => $now]) : 0;
        $updatedUsers = 0;

        if ($this->option('users') && Schema::hasColumn('users', 'brand_key')) {
            $updatedUsers = DB::table('users')
                ->whereIn('id', $ids)
                ->where(function ($q) {
                    $q->whereNull('brand_key')->orWhere('brand_key', 'oia');
                })
                ->update(['brand_key' => 'luxvant', 'updated_at' => $now]);
        }

        $this->info("Updated leads={$updatedLeads} deals={$updatedDeals} users={$updatedUsers}");
        BrandCrmScope::reset();

        return self::SUCCESS;
    }
}
