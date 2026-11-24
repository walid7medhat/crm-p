<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * view-daily-leads-report: may open the Daily Leads Report for their own branch's agents
 * (DailyLeadsReport::canView / scopeUserIds), besides super_admin and branch_admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'view-daily-leads-report', 'guard_name' => config('auth.defaults.guard', 'web')]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'view-daily-leads-report')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
