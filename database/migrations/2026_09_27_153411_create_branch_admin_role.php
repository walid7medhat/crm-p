<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * branch_admin: an admin scoped to a single branch/office. Unlike admin/manager/team_lead
 * (who sit above their team in the parent_id tree), a branch_admin is placed as a peer
 * user INSIDE an office — their lead/deal visibility is resolved via
 * User::getBranchAdminSubordinateIds() (walks up to the real office admin, then takes
 * that office's full subordinate list), not their own descendants.
 *
 * Deliberately no listings-* permission — that's what keeps Listings hidden from this
 * role, both in the frontend nav (permission-gated) and backend (role-gated) checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = Role::firstOrCreate(['name' => 'branch_admin', 'guard_name' => 'api']);

        $permissionNames = ['show-leads', 'leads-list', 'leads-edit','leads-create'];
        foreach ($permissionNames as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'api']);
        }

        $role->syncPermissions($permissionNames);
    }

    public function down(): void
    {
        Role::where('name', 'branch_admin')->delete();
    }
};
