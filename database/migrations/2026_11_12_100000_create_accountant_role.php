<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * accountant: branch-scoped finance user (see User::isAccountant()).
 *  - Deals: sees and edits every deal in their whole branch (Deal::scopeVisibleFor,
 *    User::canViewDeal). Cannot create, reassign, delete or change deal type.
 *  - Leads: read-only, converted leads of their branch only (User::applyLeadResponsibleScope,
 *    User::canViewLead). No leads-edit / leads-create, so lead edits are blocked by middleware.
 *
 * show-leads opens the Leads + Deals pages in the nav; leads-list lets the lead API list/open.
 * Place the accountant user inside their branch in the user tree (parent_id) — the branch
 * is resolved from that position, same as branch_admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'api']);

        $permissionNames = ['show-leads', 'leads-list'];
        foreach ($permissionNames as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'api']);
        }

        $role->syncPermissions($permissionNames);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'accountant')->where('guard_name', 'api')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
