<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Dubai branch_admin approves Dubai listings, so they must be able to open a listing
 * to review it — ListingController::show requires `listings-list`. Unit number and owner
 * data stay hidden by ListingResource; this only lets them open the listing page.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::query()->where('name', 'branch_admin')->first();
        if (! $role) {
            return;
        }

        $permission = Permission::findOrCreate('listings-list', $role->guard_name);
        if (! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = Role::query()->where('name', 'branch_admin')->first();
        if ($role && $role->hasPermissionTo('listings-list')) {
            $role->revokePermissionTo('listings-list');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
