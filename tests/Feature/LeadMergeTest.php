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

        config([
            'lead_merge.lead_pool_stage_id' => 10,
            'lead_merge.unqualified_stage_id' => 11,
            'lead_merge.lost_stage_id' => 2,
            'lead_merge.group_by_branch' => false,
            'lead_merge.auto_merge_branch_keys' => ['abu dhabi'],
        ]);

        foreach ([
            [1, 'New Lead', 1],
            [2, 'Lost', 8],
            [4, 'Qualified', 4],
            [5, 'Shared', 5],
            [10, 'Lead Pool', 9],
            [11, 'Unqualified', 10],
        ] as [$id, $name, $order]) {
            $this->makeStage($id, $name, $order);
        }

        config(['lead_merge.new_lead_stage_id' => 1]);

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
            'lead_branch_source' => 'Abu Dhabi',
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
        // Different phones so Unqualified / Qualified are not part of this eligible group.
        $c = $this->lead(11, ['work_phone' => '971500000099']);
        $d = $this->lead(4, ['work_phone' => '971500000088']);

        $this->artisan('leads:merge-pool-duplicates')->assertExitCode(0);

        $this->assertSame($b->id, $this->raw($a->id)->merged_into_lead_id);   // older -> newest
        $this->assertNull($this->raw($b->id)->merged_into_lead_id);           // newest = default
        $this->assertSame('eligible_auto', $this->raw($a->id)->merge_type);
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
        // Same phone in Unqualified is now eligible for Rule A — use a different phone.
        $this->lead(11, ['work_phone' => '971500000077']);
        $this->lead(4, ['work_phone' => '971500000066']);

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

    public function test_merged_list_exposes_related_lead_fields_for_view_lead(): void
    {
        $default = $this->lead(4);
        $unq = $this->lead(11, ['lead_name' => 'Old Duplicate']);
        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$unq->id],
        ])->assertOk();

        $res = $this->actingAs($this->admin, 'api')->getJson("/api/leads/{$default->id}/merged-leads")->assertOk();
        $row = $res->json('data.0');

        $this->assertTrue($res->json('meta.elevated_merged_view'));
        $this->assertSame($unq->id, $row['id']);
        $this->assertSame('Old Duplicate', $row['lead_name']);
        foreach (['id', 'lead_name', 'work_phone', 'email', 'stage_name', 'created_at', 'merged_at', 'responsible_person'] as $key) {
            $this->assertArrayHasKey($key, $row);
        }
        $this->assertEqualsCanonicalizing(['id', 'name', 'avatar'], array_keys($row['responsible_person']));
    }

    public function test_non_elevated_merged_list_exposes_name_only(): void
    {
        $default = $this->lead(4, ['responsible_person_id' => $this->sales->id]);
        $unq = $this->lead(11, ['lead_name' => 'Sales Visible Dup']);
        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$unq->id],
        ])->assertOk();

        $res = $this->actingAs($this->sales, 'api')->getJson("/api/leads/{$default->id}/merged-leads")->assertOk();
        $row = $res->json('data.0');

        $this->assertFalse($res->json('meta.elevated_merged_view'));
        $this->assertSame(['id', 'lead_name'], array_keys($row));
        $this->assertSame('Sales Visible Dup', $row['lead_name']);
    }

    public function test_restricted_view_returns_only_whitelisted_fields(): void
    {
        $default = $this->lead(4, ['responsible_person_id' => $this->sales->id]);
        $unq = $this->lead(11, ['first_name' => 'Sara', 'bedrooms' => 2, 'lead_type' => 'sale']);
        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$unq->id],
        ])->assertOk();

        $res = $this->actingAs($this->sales, 'api')
            ->getJson("/api/leads/{$default->id}/merged-leads/{$unq->id}")->assertOk();
        $data = $res->json('data');

        $allowed = array_merge(LeadMergeController::BASIC_KEYS, ['elevated_merged_view']);
        $this->assertSame([], array_diff(array_keys($data), $allowed));
        foreach ([
            'stage', 'stage_id', 'stage_name', 'original_stage_id', 'original_stage_name',
            'comments', 'activities', 'histories', 'responsible_person',
            'merged_at', 'merge_type', 'raw_meta_data', 'parent', 'added_by_user',
        ] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $data);
        }
        $this->assertSame('Sara', $data['first_name']);
        $this->assertFalse($data['can_edit']);
        $this->assertTrue($data['is_merged']);
        $this->assertFalse($data['elevated_merged_view']);
    }

    public function test_elevated_restricted_view_includes_stage_and_merge_meta(): void
    {
        $default = $this->lead(4);
        $unq = $this->lead(11, ['first_name' => 'AdminView']);
        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$unq->id],
        ])->assertOk();

        $data = $this->actingAs($this->admin, 'api')
            ->getJson("/api/leads/{$default->id}/merged-leads/{$unq->id}")
            ->assertOk()
            ->json('data');

        $this->assertTrue($data['elevated_merged_view']);
        foreach (['stage', 'stage_name', 'original_stage_id', 'merged_at', 'merge_type', 'comments', 'activities', 'histories'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
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

    // ---------- Automatic eligible-stage merge (Rule A / Rule B) ----------

    public function test_rule_a_unqualified_and_lost_merge_into_newest_in_lead_pool(): void
    {
        $lost = $this->lead(2, ['created_at' => now()->subDays(2)]);
        $unq = $this->lead(11, ['created_at' => now()->subDay()]);

        $this->artisan('leads:merge-pool-duplicates')->assertExitCode(0);

        $this->assertSame($unq->id, $this->raw($lost->id)->merged_into_lead_id);
        $this->assertNull($this->raw($unq->id)->merged_into_lead_id);
        $this->assertSame(10, $this->raw($unq->id)->stage_id);
        $this->assertSame('eligible_auto', $this->raw($lost->id)->merge_type);
        $this->assertNotNull($this->raw($lost->id)->first_name); // row preserved
    }

    public function test_rule_a_pool_and_unqualified_newest_becomes_pool_primary(): void
    {
        $pool = $this->lead(10, ['created_at' => now()->subDays(2)]);
        $unq = $this->lead(11, ['created_at' => now()->subDay()]);

        $this->artisan('leads:merge-pool-duplicates')->assertExitCode(0);

        $this->assertSame($unq->id, $this->raw($pool->id)->merged_into_lead_id);
        $this->assertNull($this->raw($unq->id)->merged_into_lead_id);
        $this->assertSame(10, $this->raw($unq->id)->stage_id);
    }

    public function test_rule_a_three_eligible_stages_newest_created_at_wins_not_updated_at(): void
    {
        $lost = $this->lead(2, ['created_at' => now()->subDays(3), 'updated_at' => now()]);
        $pool = $this->lead(10, ['created_at' => now()->subDays(2), 'updated_at' => now()->subDay()]);
        $unq = $this->lead(11, ['created_at' => now()->subDay(), 'updated_at' => now()->subDays(5)]);

        $this->artisan('leads:merge-pool-duplicates')->assertExitCode(0);

        $this->assertNull($this->raw($unq->id)->merged_into_lead_id);
        $this->assertSame($unq->id, $this->raw($lost->id)->merged_into_lead_id);
        $this->assertSame($unq->id, $this->raw($pool->id)->merged_into_lead_id);
        $this->assertSame(10, $this->raw($unq->id)->stage_id);
    }

    public function test_rule_a_is_idempotent(): void
    {
        $a = $this->lead(11, ['created_at' => now()->subDays(2)]);
        $b = $this->lead(2, ['created_at' => now()->subDay()]);

        $this->artisan('leads:merge-pool-duplicates');
        $mergedAt = $this->raw($a->id)->merged_at;
        $this->artisan('leads:merge-pool-duplicates');

        $this->assertSame($b->id, $this->raw($a->id)->merged_into_lead_id);
        $this->assertEquals($mergedAt, $this->raw($a->id)->merged_at);
        $this->assertSame(1, Lead::withoutGlobalScopes()->where('merged_into_lead_id', $b->id)->count());
    }

    public function test_rule_b_new_lead_stays_new_when_matching_pool(): void
    {
        $pool = $this->lead(10, ['created_at' => now()->subDay()]);
        $svc = app(\App\Services\EligibleStagesAutoMergeService::class);
        $neu = $this->lead(1);

        $merged = $svc->absorbEligibleIntoNewLead($neu->fresh());

        $this->assertSame([$pool->id], $merged);
        $this->assertSame(1, $this->raw($neu->id)->stage_id);
        $this->assertNull($this->raw($neu->id)->merged_into_lead_id);
        $this->assertSame($neu->id, $this->raw($pool->id)->merged_into_lead_id);
        $this->assertSame('new_lead_auto', $this->raw($pool->id)->merge_type);
        $this->assertNull(Lead::find($pool->id)); // hidden from active board
    }

    public function test_rule_b_new_lead_stays_new_when_matching_unqualified_and_lost(): void
    {
        $unq = $this->lead(11, ['created_at' => now()->subDays(2)]);
        $lost = $this->lead(2, ['created_at' => now()->subDay()]);
        $svc = app(\App\Services\EligibleStagesAutoMergeService::class);
        $neu = $this->lead(1);

        $merged = $svc->absorbEligibleIntoNewLead($neu->fresh());

        $this->assertEqualsCanonicalizing([$unq->id, $lost->id], $merged);
        $this->assertSame(1, $this->raw($neu->id)->stage_id);
        $this->assertSame($neu->id, $this->raw($unq->id)->merged_into_lead_id);
        $this->assertSame($neu->id, $this->raw($lost->id)->merged_into_lead_id);

        $res = $this->actingAs($this->admin, 'api')->getJson("/api/leads/{$neu->id}/merged-leads")->assertOk();
        $this->assertCount(2, $res->json('data'));
    }

    public function test_rule_b_does_not_merge_qualified_or_other_new_leads(): void
    {
        $qualified = $this->lead(4, ['created_at' => now()->subDay()]);
        $otherNew = $this->lead(1, ['work_phone' => '971500000055', 'created_at' => now()->subHour()]);
        $svc = app(\App\Services\EligibleStagesAutoMergeService::class);
        $neu = $this->lead(1);

        $this->assertSame([], $svc->absorbEligibleIntoNewLead($neu->fresh()));
        $this->assertNull($this->raw($qualified->id)->merged_into_lead_id);
        $this->assertNull($this->raw($otherNew->id)->merged_into_lead_id);
        $this->assertSame(1, $this->raw($neu->id)->stage_id);
    }

    public function test_manual_merge_still_rejects_pool_into_non_pool_default(): void
    {
        $default = $this->lead(4);
        $pool = $this->lead(10);

        $this->actingAs($this->admin, 'api')->postJson('/api/leads/merge/confirm', [
            'default_lead_id' => $default->id, 'lead_ids' => [$pool->id],
        ])->assertStatus(422);

        $this->assertNull($this->raw($pool->id)->merged_into_lead_id);
    }

    public function test_soft_merged_leads_are_hidden_from_active_board_queries(): void
    {
        $pool = $this->lead(10, ['created_at' => now()->subDay()]);
        $svc = app(\App\Services\EligibleStagesAutoMergeService::class);
        $neu = $this->lead(1);

        $svc->absorbEligibleIntoNewLead($neu->fresh());

        $this->assertNull(Lead::find($pool->id));
        $this->assertFalse(Lead::query()->where('stage_id', 10)->whereKey($pool->id)->exists());
        $this->assertTrue(Lead::query()->whereKey($neu->id)->exists());
        $this->assertSame(1, $this->raw($neu->id)->stage_id);
    }

    public function test_rule_a_abu_dhabi_only_skips_empty_and_dubai_branches(): void
    {
        config(['lead_merge.group_by_branch' => true]);

        $abuPool = $this->lead(10, [
            'created_at' => now()->subDays(3),
            'lead_branch_source' => 'Abu Dhabi',
        ]);
        $abuLost = $this->lead(2, [
            'created_at' => now()->subDay(),
            'lead_branch_source' => 'Abu Dhabi',
        ]);
        $dubaiPool = $this->lead(10, [
            'created_at' => now()->subDays(2),
            'lead_branch_source' => 'Dubai',
        ]);
        $emptyPool = $this->lead(10, [
            'created_at' => now()->subDays(4),
            'lead_branch_source' => null,
        ]);

        $this->artisan('leads:merge-pool-duplicates')->assertExitCode(0);

        $this->assertSame($abuLost->id, $this->raw($abuPool->id)->merged_into_lead_id);
        $this->assertNull($this->raw($abuLost->id)->merged_into_lead_id);
        $this->assertSame(10, $this->raw($abuLost->id)->stage_id);
        $this->assertNull($this->raw($dubaiPool->id)->merged_into_lead_id);
        $this->assertNull($this->raw($emptyPool->id)->merged_into_lead_id);
        $this->assertNull(Lead::find($abuPool->id));
        $this->assertNotNull(Lead::find($dubaiPool->id));
        $this->assertNotNull(Lead::find($emptyPool->id));
    }

    public function test_rule_b_absorbs_abu_dhabi_and_empty_but_skips_dubai(): void
    {
        $abuPool = $this->lead(10, [
            'created_at' => now()->subDay(),
            'lead_branch_source' => 'Abu Dhabi',
        ]);
        $dubaiPool = $this->lead(10, [
            'created_at' => now()->subHours(18),
            'lead_branch_source' => 'Dubai',
        ]);
        $emptyPool = $this->lead(10, [
            'created_at' => now()->subHours(12),
            'lead_branch_source' => null,
        ]);
        $svc = app(\App\Services\EligibleStagesAutoMergeService::class);
        $neu = $this->lead(1, ['lead_branch_source' => 'Abu Dhabi']);

        $merged = $svc->absorbEligibleIntoNewLead($neu->fresh());

        $this->assertEqualsCanonicalizing([$abuPool->id, $emptyPool->id], $merged);
        $this->assertSame($neu->id, $this->raw($abuPool->id)->merged_into_lead_id);
        $this->assertSame($neu->id, $this->raw($emptyPool->id)->merged_into_lead_id);
        $this->assertNull($this->raw($dubaiPool->id)->merged_into_lead_id);
    }

    public function test_empty_branch_new_lead_absorbs_abu_dhabi_unqualified_live(): void
    {
        $unq = $this->lead(11, [
            'created_at' => now()->subDay(),
            'lead_branch_source' => 'Abu Dhabi',
        ]);
        $lost = $this->lead(2, [
            'created_at' => now()->subHours(6),
            'lead_branch_source' => 'Abu Dhabi',
        ]);
        $svc = app(\App\Services\EligibleStagesAutoMergeService::class);
        // Super Admin create often has null lead_branch_source.
        $neu = $this->lead(1, ['lead_branch_source' => null]);

        $merged = $svc->afterLeadCreated($neu->fresh());

        $this->assertEqualsCanonicalizing([$unq->id, $lost->id], $merged);
        $this->assertSame(1, $this->raw($neu->id)->stage_id);
        $this->assertNull(Lead::find($unq->id));
        $this->assertNull(Lead::find($lost->id));
    }

    public function test_dubai_new_lead_never_auto_merges(): void
    {
        $abuPool = $this->lead(10, [
            'created_at' => now()->subDay(),
            'lead_branch_source' => 'Abu Dhabi',
        ]);
        $dubaiPool = $this->lead(10, [
            'created_at' => now()->subHours(6),
            'lead_branch_source' => 'Dubai',
        ]);
        $svc = app(\App\Services\EligibleStagesAutoMergeService::class);
        $dubaiNew = $this->lead(1, ['lead_branch_source' => 'Dubai']);

        $this->assertSame([], $svc->afterLeadCreated($dubaiNew->fresh()));
        $this->assertNull($this->raw($abuPool->id)->merged_into_lead_id);
        $this->assertNull($this->raw($dubaiPool->id)->merged_into_lead_id);
        $this->assertNull($this->raw($dubaiNew->id)->merged_into_lead_id);
    }

    public function test_after_lead_created_returns_merged_ids_for_kanban_meta(): void
    {
        $pool = $this->lead(10, ['created_at' => now()->subDay()]);
        $unq = $this->lead(11, ['created_at' => now()->subHours(12)]);
        $svc = app(\App\Services\EligibleStagesAutoMergeService::class);
        $neu = $this->lead(1);

        $merged = $svc->afterLeadCreated($neu->fresh());

        $this->assertEqualsCanonicalizing([$pool->id, $unq->id], $merged);
        $this->assertNull(Lead::find($pool->id));
        $this->assertNull(Lead::find($unq->id));
    }

    public function test_after_stage_changed_summary_includes_merged_and_primary_pool(): void
    {
        $older = $this->lead(10, ['created_at' => now()->subDays(2)]);
        $svc = app(\App\Services\EligibleStagesAutoMergeService::class);
        $newer = $this->lead(11, ['created_at' => now()->subDay()]);

        $summary = $svc->afterStageChanged($newer->fresh(), 11);

        $this->assertSame($newer->id, $summary['primary_id']);
        $this->assertSame([$older->id], $summary['merged_ids']);
        $this->assertSame(10, $summary['primary_stage_id']);
        $this->assertNull(Lead::find($older->id));
        $this->assertSame(10, $this->raw($newer->id)->stage_id);
    }

    public function test_merged_leads_endpoint_keeps_original_stage_label(): void
    {
        $neu = $this->lead(1);
        $unq = $this->lead(11, ['created_at' => now()->subDay()]);
        app(\App\Services\EligibleStagesAutoMergeService::class)->absorbEligibleIntoNewLead($neu->fresh());

        $res = $this->actingAs($this->admin, 'api')
            ->getJson("/api/leads/{$neu->id}/merged-leads")
            ->assertOk();

        $row = collect($res->json('data'))->firstWhere('id', $unq->id);
        $this->assertNotNull($row);
        $this->assertSame(11, $row['original_stage_id']);
        $this->assertSame('Unqualified', $row['stage_name']);
    }

    public function test_create_unqualified_then_lost_runs_rule_a_without_command(): void
    {
        $svc = app(\App\Services\EligibleStagesAutoMergeService::class);
        $unq = $this->lead(11, ['created_at' => now()->subDay()]);
        $this->assertSame([], $svc->afterLeadCreated($unq->fresh()));

        $lost = $this->lead(2, ['created_at' => now()]);
        $merged = $svc->afterLeadCreated($lost->fresh());

        $this->assertSame([$unq->id], $merged);
        $this->assertSame($lost->id, $this->raw($unq->id)->merged_into_lead_id);
        $this->assertNull($this->raw($lost->id)->merged_into_lead_id);
        $this->assertSame(10, $this->raw($lost->id)->stage_id);
        $this->assertNull(Lead::find($unq->id));
    }

    public function test_phone_digit_change_on_new_lead_runs_rule_b(): void
    {
        $pool = $this->lead(10, ['work_phone' => '971500000099', 'created_at' => now()->subDay()]);
        $neu = $this->lead(1, ['work_phone' => '971500000088']);
        $svc = app(\App\Services\EligibleStagesAutoMergeService::class);

        $this->assertSame([], $svc->afterLeadCreated($neu->fresh()));

        $neu->update(['work_phone' => '971500000099']);
        $merged = $svc->absorbEligibleIntoNewLead($neu->fresh());

        $this->assertSame([$pool->id], $merged);
        $this->assertSame($neu->id, $this->raw($pool->id)->merged_into_lead_id);
        $this->assertSame(1, $this->raw($neu->id)->stage_id);
        $this->assertNull(Lead::find($pool->id));
    }

    public function test_formatting_only_phone_change_does_not_need_merge(): void
    {
        $pool = $this->lead(10, ['work_phone' => '971500000001', 'created_at' => now()->subDay()]);
        $neu = $this->lead(1, ['work_phone' => '971500000001']);
        $svc = app(\App\Services\EligibleStagesAutoMergeService::class);
        $svc->absorbEligibleIntoNewLead($neu->fresh());

        $before = $this->raw($pool->id)->merged_at;
        $oldDigits = Lead::phoneDigits('971500000001');
        $newDigits = Lead::phoneDigits('+971 50 000 0001');
        $this->assertSame($oldDigits, $newDigits);

        $neu->update(['work_phone' => '+971 50 000 0001']);
        $this->assertSame([], $svc->absorbEligibleIntoNewLead($neu->fresh()));
        $this->assertEquals($before, $this->raw($pool->id)->merged_at);
    }

    public function test_converted_leads_remain_excluded_from_rule_b_on_create(): void
    {
        $converted = $this->lead(10, [
            'created_at' => now()->subDay(),
            'converted_to_deal_id' => 999,
        ]);
        $svc = app(\App\Services\EligibleStagesAutoMergeService::class);
        $neu = $this->lead(1);

        $this->assertSame([], $svc->afterLeadCreated($neu->fresh()));
        $this->assertNull($this->raw($converted->id)->merged_into_lead_id);
    }
}