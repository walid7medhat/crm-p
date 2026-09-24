<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Two additive, idempotent fixes for active users:
 *
 *   1) Any active user with no role at all gets the 'sales' role.
 *   2) Any active user outside a listing-team manager's org (User::is_listing_team)
 *      gets the 'show-leads' permission — which also gates Deals, since Deals
 *      visibility mirrors Leads visibility exactly (see useDashboardPermissions.js).
 *
 * Never revokes a role/permission from anyone.
 *
 *   php artisan users:assign-default-role --dry-run
 *   php artisan users:assign-default-role
 */
class AssignDefaultRoleAndLeadsPermission extends Command
{
    protected $signature = 'users:assign-default-role
        {--dry-run : Show what would change without writing}';

    protected $description = "Give roleless active users the 'sales' role, and grant 'show-leads' to active users outside a listing-team manager's org";

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! Role::where('name', 'sales')->where('guard_name', 'api')->exists()) {
            $this->error("Role 'sales' (guard: api) does not exist. Aborting.");

            return self::FAILURE;
        }

        if (! Permission::where('name', 'show-leads')->where('guard_name', 'api')->exists()) {
            $this->error("Permission 'show-leads' (guard: api) does not exist. Aborting.");

            return self::FAILURE;
        }

        $users = User::where('status', 'active')->get();

        $rolesAssigned = 0;
        $rolesAlreadyHad = 0;
        $permsGranted = 0;
        $permsAlreadyHad = 0;
        $permsSkippedListingTeam = 0;

        foreach ($users as $user) {
            // ── 1) Default role for anyone with none ──
            if ($user->roles()->count() === 0) {
                $this->line("  + role sales -> #{$user->id} {$user->name}");
                if (! $dryRun) {
                    $user->assignRole('sales');
                }
                $rolesAssigned++;
            } else {
                $rolesAlreadyHad++;
            }

            // ── 2) show-leads permission for non-listing-team users ──
            if ($user->is_listing_team) {
                $permsSkippedListingTeam++;

                continue;
            }

            if ($user->hasPermissionTo('show-leads')) {
                $permsAlreadyHad++;

                continue;
            }

            $this->line("  + perm show-leads -> #{$user->id} {$user->name}");
            if (! $dryRun) {
                $user->givePermissionTo('show-leads');
            }
            $permsGranted++;
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would assign' : 'Assigned')." 'sales' role to {$rolesAssigned} user(s).");
        $this->line("Already had a role: {$rolesAlreadyHad}");
        $this->newLine();
        $this->info(($dryRun ? 'Would grant' : 'Granted')." 'show-leads' to {$permsGranted} user(s).");
        $this->line("Already had it: {$permsAlreadyHad}");
        $this->line("Skipped (listing team): {$permsSkippedListingTeam}");

        return self::SUCCESS;
    }
}
