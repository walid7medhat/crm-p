<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * daily-report-assigner: leads this user assigns are counted in the Daily Leads Report,
 * the same as assignments made by a branch_admin (DailyLeadsReport::assignerIds()).
 * Not given to any role here — assign it to a user (or role) from the permissions screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'daily-report-assigner', 'guard_name' => config('auth.defaults.guard', 'web')]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'daily-report-assigner')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
