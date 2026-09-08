<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

/**
 * Grant the 'show-leads' permission — which also gates Deals, since Deals visibility
 * mirrors Leads visibility exactly (see useDashboardPermissions.js) — to every active
 * user who is not part of a listing_team=1 manager's org (User::is_listing_team).
 *
 * Additive only: never revokes the permission from anyone, including listing-team users
 * who might already have it.
 *
 *   php artisan permissions:grant-show-leads --dry-run
 *   php artisan permissions:grant-show-leads
 */
class GrantShowLeadsPermission extends Command
{
    protected $signature = 'permissions:grant-show-leads
        {--dry-run : Show what would change without writing}';

    protected $description = "Grant 'show-leads' (Leads + Deals access) to active users outside a listing-team manager's org";

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! Permission::where('name', 'show-leads')->where('guard_name', 'api')->exists()) {
            $this->error("Permission 'show-leads' (guard: api) does not exist. Aborting.");

            return self::FAILURE;
        }

        $users = User::where('status', 'active')->get();

        $granted = 0;
        $alreadyHad = 0;
        $skippedListingTeam = 0;

        foreach ($users as $user) {
            if ($user->is_listing_team) {
                $skippedListingTeam++;

                continue;
            }

            if ($user->hasPermissionTo('show-leads')) {
                $alreadyHad++;

                continue;
            }

            $this->line("  + #{$user->id} {$user->name}");
            if (! $dryRun) {
                $user->givePermissionTo('show-leads');
            }
            $granted++;
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would grant' : 'Granted')." 'show-leads' to {$granted} user(s).");
        $this->line("Already had it: {$alreadyHad}");
        $this->line("Skipped (listing team): {$skippedListingTeam}");

        return self::SUCCESS;
    }
}
