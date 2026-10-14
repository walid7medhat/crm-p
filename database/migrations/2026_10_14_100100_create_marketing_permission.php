<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * marketing: access to the Marketing workspace (boards, calendar, analytics) and makes the
 * user selectable as an assignee there. Stage management and card deletion stay admin-only.
 * Not given to any role here — assign it from the permissions screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'marketing', 'guard_name' => config('auth.defaults.guard', 'web')]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'marketing')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
