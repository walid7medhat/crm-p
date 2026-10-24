<?php

namespace Tests\Feature;

use App\Http\Controllers\LeadMergeController;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadMergeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $sales;
    private string $phone = '971500000001';

    protected function setUp(): void
    {
        parent::setUp();

        config(['lead_merge.lead_pool_stage_id' => 10, 'lead_merge.unqualified_stage_id' => 11, 'lead_merge.lost_stage_id' => 2]);

        foreach ([[2, 'Lost', 8], [4, 'Qualified', 4], [5, 'Shared', 5], [10, 'Lead Pool', 9], [11, 'Unqualified', 10]] as [$id, $name, $order]) {
            $this->makeStage($id, $name, $order);
        }

        foreach (['super_admin', 'sales'] as $r) {
            Role::findOrCreate($r, 'api');
        }
        $this->admin = User::factory()->create(); $this->admin->assignRole('super_admin');
        $this->sales = User::factory()->create(); $this->sales->assignRole('sales');
    }

    private function makeStage(int $id, string $name, int $order): void
    {
        DB::table('stages')->insert([
            'id' => $id, 'name' => $name, 'order' => $order, 'stage_type' => 'lead',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function lead(int $stage, array $extra = []): Lead
    {
        return Lead::factory()->create(array_merge([
            'stage_id' => $stage, 'work_phone' => $this->phone,
            'responsible_person_id' => $this->admin->id,
        ], $extra));
    }

    private function raw(int $id): ?Lead
    {
        return Lead::withoutGlobalScopes()->find($id);
    }

    // ---------- Lead Pool automatic merge ----------

    public function test_pool_merge_newest_is_default_and_other_stages_untouched(): void
    {
        $a = $this->lead(10, ['created_at' => now()->subDays(3)]);
        $b = $this->lead(10, ['created_at' => now()->subDay()]);
        $c = $this->lead(11);
        $d = $this->lead(4);

        $this->artisan('leads:merge-pool-duplicates')->assertExitCode(0);

        $this->assertSame($b->id, $this->raw($a->id)->merged_into_lead_id);   // older -> newest
        $this->assertNull($this->raw($b->id)->merged_into_lead_id);           // newest = default
        $this->assertSame('pool_auto', $this->raw($a->id)->merge_type);
        $this->assertNull($this->raw($c->id)->merged_into_lead_id);
        $this->assertNull($this->raw($d->id)->merged_into_lead_id);
        $this->assertSame(11, $this->raw($c->id)->stage_id);
        $this->assertSame(4, $this->raw($d->id)->stage_id);
        $this->assertSame($this->admin->id, $this->raw($d->id)->responsible_person_id);
    }

    public function test_pool_merge_is_idempotent(): void
    {
        $a = $this->lead(10, ['created_at' => now()->subDays(2)]);
        $b = $this->lead(10, ['created_at' => now()->subDay()]);

        $this->artisan('leads:merge-pool-duplicates');
        $first = $this->raw($a->id)->merged_at;
        $this->artisan('leads:merge-pool-duplicates');

        $this->assertSame($b->id, $this->raw($a->id)->merged_into_lead_id);
        $this->assertEquals($first, $this->raw($a->id)->merged_at);
        $this->assertSame(1, Lead::withoutGlobalScopes()->where('merged_into_lead_id', $b->id)->count());
    }

    public function test_newer_pool_lead_flattens_chain(): void
    {
        $a = $this->lead(10, ['created_at' => now()->subDays(3)]);
        $b = $this->lead(10, ['created_at' => now()->subDays(2)]);
        $this->artisan('leads:merge-pool-duplicates');

        $e = $this->lead(10, ['created_at' => now()]);
        $this->artisan('leads:merge-pool-duplicates');

        $this->assertSame($e->id, $this->raw($a->id)->merged_into_lead_id);
        $this->assertSame($e->id, $this->raw($b->id)->merged_into_lead_id);
        $this->assertNull($this->raw($e->id)->merged_into_lead_id);
    }

    public function test_single_pool_lead_and_pool_lead_with_only_other_stages_are_not_merged(): void
    {
        $pool = $this->lead(10);
        $this->lead(11); $this->lead(4);

        $this->artisan('leads:merge-pool-duplicates');

        $this->assertNull($this->raw($pool->id)->merged_into_lead_id);
    }

    public function test_pool_merge_skips_leads_that_left_the_pool(): void
    {
        $a = $this->lead(10, ['created_at' => now()->subDay()]);
        $b = $this->lead(10, ['created_at' => now()]);
        DB::table('leads')->where('id', $a->id)->update(['stage_id' => 4]);   // moved out before the run

        $this->artisan('leads:merge-pool-duplicates');

        $this->assertNull($this->raw($a->id)->merged_into_lead_id);
        $this->assertNull($this->raw($b->id)->merged_into_lead_id);
    }

    // ---------- Manual merge ----------

    public function test_manual_merge_unqualified_and_lost_into_default_of_any_stage(): void
    {
        $default = $this->lead(4);
        $unq = $this->lead(11);
        $lost = $this->lead(2);

        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$unq->id, $lost->id],
        ])->assertOk();

        $this->assertSame($default->id, $this->raw($unq->id)->merged_into_lead_id);
        $this->assertSame($default->id, $this->raw($lost->id)->merged_into_lead_id);
        $this->assertSame('manual', $this->raw($unq->id)->merge_type);
        $this->assertSame(11, $this->raw($unq->id)->merged_original_stage_id);
        $this->assertSame(4, $this->raw($default->id)->stage_id);             // default unchanged
        $this->assertSame($default->id, Lead::find($default->id)->id);
    }

    public function test_default_can_be_an_unqualified_lead(): void
    {
        $default = $this->lead(11);
        $lost = $this->lead(2);

        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$lost->id],
        ])->assertOk();

        $this->assertSame($default->id, $this->raw($lost->id)->merged_into_lead_id);
        $this->assertSame(11, $this->raw($default->id)->stage_id);
    }

    public function test_ineligible_stage_stays_independent_and_is_reported_in_preview(): void
    {
        $default = $this->lead(4);
        $unq = $this->lead(11);
        $other = $this->lead(5);

        $res = $this->actingAs($this->admin, 'api')
            ->postJson('/api/leads/merge/preview', ['default_lead_id' => $default->id])->assertOk();

        $this->assertSame([$unq->id], collect($res->json('data.eligible'))->pluck('id')->all());
        $this->assertSame([$other->id], collect($res->json('data.ineligible'))->pluck('id')->all());
        $this->assertNotEmpty($res->json('data.ineligible.0.reason'));

        $this->assertNull($this->raw($unq->id)->merged_into_lead_id);          // preview wrote nothing

        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$unq->id, $other->id],
        ])->assertOk();

        $this->assertNull($this->raw($other->id)->merged_into_lead_id);
        $this->assertSame(5, $this->raw($other->id)->stage_id);
    }

    public function test_pool_lead_is_never_merged_manually(): void
    {
        $default = $this->lead(4);
        $pool = $this->lead(10);

        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$pool->id],
        ])->assertStatus(422);

        $poolDefault = $this->lead(10, ['work_phone' => '971500000099']);
        $unq = $this->lead(11, ['work_phone' => '971500000099']);
        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $poolDefault->id, 'lead_ids' => [$unq->id],
        ])->assertStatus(422);

        $this->assertNull($this->raw($pool->id)->merged_into_lead_id);
        $this->assertNull($this->raw($unq->id)->merged_into_lead_id);
    }

    public function test_not_a_duplicate_and_self_merge_are_rejected(): void
    {
        $default = $this->lead(4);
        $stranger = $this->lead(11, ['work_phone' => '971599999999']);

        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$stranger->id],
        ])->assertStatus(422);

        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$default->id],
        ])->assertStatus(422);

        $this->assertNull($this->raw($stranger->id)->merged_into_lead_id);
        $this->assertNull($this->raw($default->id)->merged_into_lead_id);
    }

    public function test_repeated_confirm_does_not_duplicate_or_remerge(): void
    {
        $default = $this->lead(4);
        $unq = $this->lead(11);
        $payload = ['default_lead_id' => $default->id, 'lead_ids' => [$unq->id]];

        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', $payload)->assertOk();
        $mergedAt = $this->raw($unq->id)->merged_at;

        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', $payload)->assertStatus(422);

        $this->assertEquals($mergedAt, $this->raw($unq->id)->merged_at);
        $this->assertSame($default->id, $this->raw($unq->id)->merged_into_lead_id);
    }

    public function test_cannot_merge_into_an_already_merged_lead(): void
    {
        $default = $this->lead(4);
        $unq = $this->lead(11);
        $lost = $this->lead(2);
        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$unq->id],
        ])->assertOk();

        // $unq is merged (hidden) -> using it as a default must fail
        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $unq->id, 'lead_ids' => [$lost->id],
        ])->assertNotFound();

        $this->assertNull($this->raw($lost->id)->merged_into_lead_id);
    }

    public function test_sales_role_cannot_merge(): void
    {
        $default = $this->lead(4, ['responsible_person_id' => $this->sales->id]);
        $unq = $this->lead(11, ['responsible_person_id' => $this->sales->id]);

        $this->actingAs($this->sales, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$unq->id],
        ])->assertForbidden();

        $this->assertNull($this->raw($unq->id)->merged_into_lead_id);
    }

    // ---------- Visibility / access control ----------

    public function test_merged_lead_disappears_from_normal_queries_and_direct_access(): void
    {
        $default = $this->lead(4);
        $unq = $this->lead(11);
        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$unq->id],
        ])->assertOk();

        $this->assertNull(Lead::find($unq->id));
        $this->assertSame(0, Lead::where('id', $unq->id)->count());
        $this->assertSame(0, $default->fresh()->duplicate_leads->count());    // duplicates exclude merged
        $this->getJson("/api/leads/{$unq->id}")->assertNotFound();
        $this->actingAs($this->admin, 'api')->getJson("/api/leads/{$unq->id}")->assertNotFound();
    }

    public function test_merged_list_exposes_only_name_and_responsible_person(): void
    {
        $default = $this->lead(4);
        $unq = $this->lead(11, ['lead_name' => 'Old Duplicate']);
        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$unq->id],
        ])->assertOk();

        $res = $this->actingAs($this->admin, 'api')->getJson("/api/leads/{$default->id}/merged-leads")->assertOk();
        $row = $res->json('data.0');

        $this->assertSame($unq->id, $row['id']);
        $this->assertSame('Old Duplicate', $row['lead_name']);
        $this->assertEqualsCanonicalizing(['id', 'lead_name', 'responsible_person'], array_keys($row));
        $this->assertEqualsCanonicalizing(['id', 'name', 'avatar'], array_keys($row['responsible_person']));
    }

    public function test_restricted_view_returns_only_whitelisted_fields(): void
    {
        $default = $this->lead(4);
        $unq = $this->lead(11, ['first_name' => 'Sara', 'bedrooms' => 2, 'lead_type' => 'sale']);
        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$unq->id],
        ])->assertOk();

        $res = $this->actingAs($this->admin, 'api')
            ->getJson("/api/leads/{$default->id}/merged-leads/{$unq->id}")->assertOk();
        $data = $res->json('data');

        $this->assertSame([], array_diff(array_keys($data), LeadMergeController::RESTRICTED_KEYS));
        foreach (['stage', 'comments', 'activities', 'histories', 'responsible_person', 'raw_meta_data', 'parent', 'added_by_user'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $data);
        }
        $this->assertSame('Sara', $data['first_name']);
        $this->assertFalse($data['can_edit']);
        $this->assertTrue($data['is_merged']);
    }

    public function test_restricted_view_rejects_a_lead_merged_into_another_default(): void
    {
        $defaultA = $this->lead(4);
        $defaultB = $this->lead(4, ['work_phone' => '971500000050']);
        $unq = $this->lead(11);
        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $defaultA->id, 'lead_ids' => [$unq->id],
        ])->assertOk();

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/leads/{$defaultB->id}/merged-leads/{$unq->id}")->assertNotFound();
    }

    public function test_user_without_access_to_default_cannot_read_merged_data(): void
    {
        $default = $this->lead(4);                                   // owned by admin, not by $sales
        $unq = $this->lead(11);
        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$unq->id],
        ])->assertOk();

        $this->actingAs($this->sales, 'api')->getJson("/api/leads/{$default->id}/merged-leads")->assertForbidden();
        $this->actingAs($this->sales, 'api')
            ->getJson("/api/leads/{$default->id}/merged-leads/{$unq->id}")->assertForbidden();
        $this->actingAs($this->sales, 'api')->getJson("/api/leads/{$unq->id}")->assertNotFound();
    }

    public function test_owner_of_default_can_view_merged_lead_even_if_not_owner_of_merged(): void
    {
        $default = $this->lead(4, ['responsible_person_id' => $this->sales->id]);
        $unq = $this->lead(11, ['responsible_person_id' => $this->admin->id]);   // different owner
        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$unq->id],
        ])->assertOk();

        $this->actingAs($this->sales, 'api')
            ->getJson("/api/leads/{$default->id}/merged-leads/{$unq->id}")->assertOk();
    }

    // ---------- Data integrity ----------

    public function test_merge_keeps_related_data_and_does_not_delete_rows(): void
    {
        $default = $this->lead(4, ['first_name' => 'Keep']);
        $unq = $this->lead(11, ['first_name' => 'Dup']);
        $before = Lead::withoutGlobalScopes()->count();

        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$unq->id],
        ])->assertOk();

        $this->assertSame($before, Lead::withoutGlobalScopes()->count());
        $this->assertSame('Keep', $this->raw($default->id)->first_name);       // default data not overwritten
        $this->assertSame('Dup', $this->raw($unq->id)->first_name);            // duplicate data preserved
        $this->assertSame($this->admin->id, $this->raw($unq->id)->responsible_person_id);
        $this->assertNull($this->raw($unq->id)->deleted_at);
    }

    public function test_unrelated_stage_changes_still_work_after_merge(): void
    {
        $default = $this->lead(4);
        $unq = $this->lead(11);
        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$unq->id],
        ])->assertOk();

        $default->refresh()->update(['stage_id' => 5]);

        $this->assertSame(5, Lead::find($default->id)->stage_id);
        $this->assertSame($default->id, $this->raw($unq->id)->merged_into_lead_id);
    }
}