<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * show-branch-leads: user sees every lead in their branch (User::seesBranchLeads()),
 * not just their own / their hierarchy's. Not given to any role here — assign it to a
 * role or directly to a user from the permissions screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'show-branch-leads', 'guard_name' => config('auth.defaults.guard', 'web')]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'show-branch-leads')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
