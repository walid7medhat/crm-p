<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Dubai branch_admin may edit Dubai listings — ListingController::update requires
 * `listings-edit`. Which listings they can edit is still decided per listing
 * (ListingApprovalPolicy::canEditAsDubaiApprover); unit number and owner stay hidden
 * and unchanged (ListingRequest keeps the saved values).
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::query()->where('name', 'branch_admin')->first();
        if (! $role) {
            return;
        }

        $permission = Permission::findOrCreate('listings-edit', $role->guard_name);
        if (! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = Role::query()->where('name', 'branch_admin')->first();
        if ($role && $role->hasPermissionTo('listings-edit')) {
            $role->revokePermissionTo('listings-edit');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
