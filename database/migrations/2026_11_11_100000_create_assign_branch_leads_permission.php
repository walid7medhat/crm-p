<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * assign-branch-leads: user may create / reassign a lead to ANY user in their branch
 * (User::canAssignInWholeBranch()), but still SEES only their own leads (their
 * hierarchy) — unlike show-branch-leads. Not given to any role here — assign it to a
 * role or directly to a user from the permissions screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'assign-branch-leads', 'guard_name' => config('auth.defaults.guard', 'web')]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'assign-branch-leads')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
