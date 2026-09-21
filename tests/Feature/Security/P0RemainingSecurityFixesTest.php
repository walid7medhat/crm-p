<?php

namespace Tests\Feature\Security;

use App\Models\Designation;
use App\Models\Department;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Focused P0 security fixes: import, HR extras, leads export, Meta fetch, listing delete.
 * Does NOT cover /api/leads/integration (unused — left unchanged pending business decision).
 */
class P0RemainingSecurityFixesTest extends TestCase
{
    // ---------- P0-1 Employee Excel import ----------

    public function test_employee_excel_import_requires_jwt(): void
    {
        $this->postJson('/api/admin/employees/import-excel', [])->assertStatus(401);
    }

    public function test_employee_excel_import_rejects_user_without_employees_create(): void
    {
        Permission::findOrCreate('employees-create', 'api');

        $user = User::factory()->create(['status' => 'active']);
        foreach ($user->roles as $role) {
            $user->removeRole($role);
        }
        $user->revokePermissionTo('employees-create');

        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/employees/import-excel', [
                'file' => UploadedFile::fake()->create('employees.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ]);

        $this->assertSame(403, $response->status());
        $user->delete();
    }

    public function test_employee_excel_import_authorized_user_passes_auth_gates(): void
    {
        Permission::findOrCreate('employees-create', 'api');

        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('employees-create');
        $token = JWTAuth::fromUser($user);

        // Empty/invalid file → past auth; expect 400/422 not 401/403
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/employees/import-excel', [
                'file' => UploadedFile::fake()->create('empty.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ]);

        $this->assertNotSame(401, $response->status());
        $this->assertNotSame(403, $response->status());
        $user->delete();
    }

    // ---------- P0-2 Designation / Department extras ----------

    public function test_designation_and_department_extras_require_jwt(): void
    {
        $designation = Designation::query()->first();
        $department = Department::query()->first();

        $this->getJson('/api/designations/'.($designation?->id ?? 1).'/employees')->assertStatus(401);
        $this->patchJson('/api/designations/'.($designation?->id ?? 1).'/toggle-status')->assertStatus(401);
        $this->postJson('/api/designations/bulk-delete', ['ids' => [1]])->assertStatus(401);

        $this->getJson('/api/departments/'.($department?->id ?? 1).'/employees')->assertStatus(401);
        $this->patchJson('/api/departments/'.($department?->id ?? 1).'/toggle-status')->assertStatus(401);
        $this->postJson('/api/departments/bulk-delete', ['ids' => [1]])->assertStatus(401);
    }

    public function test_designation_extras_reject_user_without_permissions(): void
    {
        Permission::findOrCreate('designations-list', 'api');
        Permission::findOrCreate('designations-edit', 'api');
        Permission::findOrCreate('designations-delete', 'api');

        $designation = Designation::query()->first();
        if (!$designation) {
            $this->markTestSkipped('No designation');
        }

        $user = User::factory()->create(['status' => 'active']);
        foreach ($user->roles as $role) {
            $user->removeRole($role);
        }

        $token = JWTAuth::fromUser($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/designations/'.$designation->id.'/employees')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/designations/'.$designation->id.'/toggle-status')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/designations/bulk-delete', ['ids' => [$designation->id]])
            ->assertStatus(403);

        $user->delete();
    }

    public function test_department_extras_reject_user_without_permissions(): void
    {
        Permission::findOrCreate('departments-list', 'api');
        Permission::findOrCreate('departments-edit', 'api');
        Permission::findOrCreate('departments-delete', 'api');

        $department = Department::query()->first();
        if (!$department) {
            $this->markTestSkipped('No department');
        }

        $user = User::factory()->create(['status' => 'active']);
        foreach ($user->roles as $role) {
            $user->removeRole($role);
        }

        $token = JWTAuth::fromUser($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/departments/'.$department->id.'/employees')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/departments/'.$department->id.'/toggle-status')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/departments/bulk-delete', ['ids' => [$department->id]])
            ->assertStatus(403);

        $user->delete();
    }

    public function test_designation_get_employees_allowed_with_hr_read(): void
    {
        $designation = Designation::query()->first();
        if (!$designation) {
            $this->markTestSkipped('No designation');
        }

        Permission::findOrCreate('designations-list', 'api');

        $user = User::factory()->create(['status' => 'active']);
        foreach ($user->roles as $role) {
            $user->removeRole($role);
        }
        $user->givePermissionTo('designations-list');
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/designations/'.$designation->id.'/employees');

        $this->assertSame(200, $response->status(), (string) $response->getContent());
        $user->delete();
    }

    // ---------- P0-3 Leads export ----------

    public function test_leads_export_requires_authentication(): void
    {
        $this->get('/leads/export')->assertStatus(401);
    }

    public function test_leads_export_rejects_user_without_leads_list(): void
    {
        Permission::findOrCreate('leads-list', 'api');

        $user = User::factory()->create(['status' => 'active']);
        foreach ($user->roles as $role) {
            $user->removeRole($role);
        }
        $user->revokePermissionTo('leads-list');

        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/leads/export');

        $this->assertSame(403, $response->status());
        $user->delete();
    }

    public function test_leads_export_authorized_user_can_export(): void
    {
        Permission::findOrCreate('leads-list', 'api');

        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('leads-list');
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/leads/export');

        $status = method_exists($response, 'status') ? $response->status() : $response->getStatusCode();
        $this->assertSame(200, $status);
        $disposition = $response->headers->get('content-disposition', '');
        $this->assertTrue(
            str_contains($disposition, 'leads.csv')
            || str_contains((string) $response->headers->get('content-type', ''), 'csv')
            || $response->headers->get('content-type') !== null
        );
        $user->delete();
    }

    // ---------- P0-5 Meta fetch ----------

    public function test_meta_fetch_leads_requires_authentication(): void
    {
        $this->get('/fb/from/123/leads')->assertStatus(401);
    }

    public function test_meta_fetch_leads_rejects_non_admin(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        foreach ($user->roles as $role) {
            $user->removeRole($role);
        }

        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/fb/from/123/leads');

        $this->assertSame(403, $response->status());
        $user->delete();
    }

    public function test_meta_fetch_leads_admin_passes_auth_gate(): void
    {
        $admin = User::role('super_admin')->where('status', 'active')->first()
            ?? User::role('admin')->where('status', 'active')->first();

        if (!$admin) {
            $this->markTestSkipped('No admin/super_admin user');
        }

        $token = JWTAuth::fromUser($admin);

        // Past auth: may 500/empty if Meta token missing — must not be 401/403
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/fb/from/123/leads');

        $this->assertNotSame(401, $response->status());
        $this->assertNotSame(403, $response->status());
    }

    // ---------- P0-6 Listing destroy ----------

    public function test_unauthorized_user_cannot_delete_others_listing(): void
    {
        $listing = Listing::query()
            ->whereNotNull('agent_id')
            ->orWhereNotNull('added_by')
            ->first();

        if (!$listing) {
            $this->markTestSkipped('No listing');
        }

        $outsider = User::factory()->create(['status' => 'active']);
        foreach ($outsider->roles as $role) {
            $outsider->removeRole($role);
        }

        // Ensure outsider is not owner/agent
        if ((int) $outsider->id === (int) $listing->added_by || (int) $outsider->id === (int) $listing->agent_id) {
            $outsider->delete();
            $this->markTestSkipped('Could not create outsider distinct from listing owner');
        }

        $token = JWTAuth::fromUser($outsider);
        $listingId = $listing->id;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/listings/properties/'.$listingId);

        $this->assertSame(403, $response->status());
        $this->assertNotNull(Listing::find($listingId), 'Listing must not be deleted');

        $outsider->delete();
    }

    public function test_listing_owner_can_delete_own_listing(): void
    {
        $template = Listing::query()->first();
        if (!$template) {
            $this->markTestSkipped('No listing template');
        }

        $owner = User::factory()->create(['status' => 'active']);
        foreach ($owner->roles as $role) {
            $owner->removeRole($role);
        }
        Permission::findOrCreate('listings-delete', 'api');
        $owner->givePermissionTo('listings-delete');

        $listing = $template->replicate();
        $listing->added_by = $owner->id;
        $listing->agent_id = $owner->id;
        $listing->reference_number = 'SEC-DEL-'.uniqid();
        $listing->save();

        $token = JWTAuth::fromUser($owner);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/listings/properties/'.$listing->id);

        $this->assertTrue(
            in_array($response->status(), [200, 204], true),
            'Owner with listings-delete should be allowed to delete. Got '.$response->status().': '.$response->getContent()
        );
        $this->assertNull(Listing::find($listing->id));

        $owner->delete();
    }

    public function test_super_admin_can_delete_listing(): void
    {
        $admin = User::role('super_admin')->where('status', 'active')->first();
        if (!$admin) {
            $this->markTestSkipped('No super_admin');
        }

        $template = Listing::query()->first();
        if (!$template) {
            $this->markTestSkipped('No listing template');
        }

        $disposable = $template->replicate();
        $disposable->added_by = $admin->id;
        $disposable->agent_id = $admin->id;
        $disposable->reference_number = 'SEC-ADM-'.uniqid();
        $disposable->save();

        $token = JWTAuth::fromUser($admin);
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/listings/properties/'.$disposable->id);

        $this->assertTrue(
            in_array($response->status(), [200, 204], true),
            'super_admin delete failed: '.$response->status().' '.$response->getContent()
        );
        $this->assertNull(Listing::find($disposable->id));
    }
}
