<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * assign-lead-pool-to-others: user may assign Lead Pool leads to another agent
 * ("Assign To…"). Replaces the old hardcoded admin-role check; super_admin always can.
 * Given to the admin role here so current admins keep it — revoke / grant per role or
 * per user from the permissions screen.
 */
return new class extends Migration
{
    private const NAME = 'assign-lead-pool-to-others';

    public function up(): void
    {
        $guard = config('auth.defaults.guard', 'web');
        $permission = Permission::findOrCreate(self::NAME, $guard);

        $admin = Role::query()->where('name', 'admin')->where('guard_name', $guard)->first();
        if ($admin && ! $admin->hasPermissionTo($permission)) {
            $admin->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::NAME)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
