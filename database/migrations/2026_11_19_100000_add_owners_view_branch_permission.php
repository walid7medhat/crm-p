<?php

use App\Models\User;
use App\Support\Branch\BranchView;
use App\Support\Listing\OwnerVisibility;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * `owners-view-branch`: see every owner and unit number of your own branch.
 *
 * Granted now to:
 *  - managers with listing_team = 1 (they used to see ALL branches; now their branch only),
 *  - the Dubai branch head and the admins / branch admins under it.
 * Anyone else can be given it from the user's permissions screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $guard = config('auth.defaults.guard', 'api');
        $permission = Permission::findOrCreate(OwnerVisibility::PERMISSION, $guard);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $ids = User::role('manager')->where('listing_team', 1)->pluck('id')->all();

        $dubai = BranchView::rootUser('dubai');
        if ($dubai) {
            $ids[] = $dubai->id;
            $members = $dubai->getAllSubordinatesIds();
            $ids = array_merge($ids, User::whereIn('id', $members)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'branch_admin']))
                ->pluck('id')->all());
        }

        // They also need the Owners page itself (sidebar is gated by `owners-list`).
        $ownersList = Permission::where('name', 'owners-list')->where('guard_name', $guard)->first();

        User::whereIn('id', array_unique($ids))->get()->each(function (User $u) use ($permission, $ownersList) {
            if (! $u->hasDirectPermission($permission)) {
                $u->givePermissionTo($permission);
            }
            if ($ownersList && ! $u->hasPermissionTo($ownersList)) {
                $u->givePermissionTo($ownersList);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', OwnerVisibility::PERMISSION)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
