<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * manage-lead-pool-settings: user may open the Lead Pool Settings page and control the
 * agents of their own branch (show Lead Pool, per-day / per-hour limits).
 * super_admin and user #30 always manage every agent without it.
 * Not given to any role here — assign it to a role or directly to a user from the
 * permissions screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'manage-lead-pool-settings', 'guard_name' => config('auth.defaults.guard', 'web')]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'manage-lead-pool-settings')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
