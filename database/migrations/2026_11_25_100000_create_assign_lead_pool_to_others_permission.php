<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * assign-lead-pool-to-others: user may assign Lead Pool leads to another agent
 * ("Assign To…"). Replaces the old hardcoded admin-role check; super_admin always can.
 * Not given to any role here — grant it per user from the permissions screen.
 * branch_admin holders pick from their whole branch; others from their own team.
 */
return new class extends Migration
{
    private const NAME = 'assign-lead-pool-to-others';

    public function up(): void
    {
        $guard = config('auth.defaults.guard', 'web');
        Permission::findOrCreate(self::NAME, $guard);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::NAME)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
