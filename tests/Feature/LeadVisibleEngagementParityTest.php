<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadComment;
use App\Models\LeadHistory;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Guards visibleEngagement SQL rewrites: same comment IDs for sales as the
 * legacy correlated subquery (owner-at-comment-time semantics unchanged).
 */
class LeadVisibleEngagementParityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sales_visible_comment_ids_match_legacy_sql(): void
    {
        foreach (['super_admin', 'admin', 'sales'] as $role) {
            Role::findOrCreate($role);
        }

        $admin = User::factory()->create(['parent_id' => null]);
        $admin->assignRole('admin');

        $sales = User::factory()->create(['parent_id' => $admin->id]);
        $sales->assignRole('sales');

        $other = User::factory()->create(['parent_id' => $admin->id]);
        $other->assignRole('sales');

        $stage = Stage::query()->where('stage_type', 'lead')->orderBy('order')->first()
            ?? Stage::create(['name' => 'New Lead', 'order' => 1, 'stage_type' => 'lead']);

        $lead = Lead::create([
            'lead_name' => 'Engagement Parity Lead',
            'first_name' => 'Parity',
            'stage_id' => $stage->id,
            'responsible_person_id' => $sales->id,
            'added_by' => $admin->id,
            'work_phone' => '971500000099',
        ]);

        // Timeline: other owns → comment by admin → assign to sales → comment by sales → comment by admin
        $t0 = now()->subMinutes(30);
        $t1 = now()->subMinutes(20);
        $t2 = now()->subMinutes(10);
        $t3 = now()->subMinutes(5);
        $t4 = now()->subMinute();

        LeadHistory::create([
            'lead_id' => $lead->id,
            'user_id' => $admin->id,
            'changes' => ['action' => 'created'],
            'created_at' => $t0,
            'updated_at' => $t0,
        ]);

        $adminEarly = LeadComment::create([
            'lead_id' => $lead->id,
            'user_id' => $admin->id,
            'comment' => 'while-other-owned',
            'created_at' => $t1,
            'updated_at' => $t1,
        ]);

        LeadHistory::create([
            'lead_id' => $lead->id,
            'user_id' => $admin->id,
            'changes' => [
                'action' => 'assigned',
                'old_person_id' => $other->id,
                'new_person_id' => $sales->id,
            ],
            'created_at' => $t2,
            'updated_at' => $t2,
        ]);

        $salesComment = LeadComment::create([
            'lead_id' => $lead->id,
            'user_id' => $sales->id,
            'comment' => 'sales-own',
            'created_at' => $t3,
            'updated_at' => $t3,
        ]);

        $adminAfter = LeadComment::create([
            'lead_id' => $lead->id,
            'user_id' => $admin->id,
            'comment' => 'while-sales-owned',
            'created_at' => $t4,
            'updated_at' => $t4,
        ]);

        $lead->forceFill(['responsible_person_id' => $sales->id])->save();

        $this->actingAs($sales, 'api');

        $legacyIds = $this->legacyVisibleCommentIds($lead, [(int) $sales->id]);
        $optimizedIds = $lead->comments()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame($legacyIds, $optimizedIds);

        // Sales wrote one; admin comment while sales owned is visible; early admin comment
        // (owner-at-time = other) is not.
        $this->assertContains((int) $salesComment->id, $optimizedIds);
        $this->assertContains((int) $adminAfter->id, $optimizedIds);
        $this->assertNotContains((int) $adminEarly->id, $optimizedIds);
    }

    /**
     * Exact pre-optimization correlated SQL (for parity only).
     *
     * @param  array<int>  $allowed
     * @return array<int>
     */
    private function legacyVisibleCommentIds(Lead $lead, array $allowed): array
    {
        $ids = implode(',', array_map('intval', $allowed));
        $sql = "
            select lead_comments.id
              from lead_comments
             where lead_comments.lead_id = ?
               and lead_comments.deleted_at is null
               and (
                    lead_comments.user_id in ({$ids})
                 or coalesce(
                        nullif(
                            json_unquote(json_extract((
                                select h.changes
                                  from lead_histories h
                                 where h.lead_id = lead_comments.lead_id
                                   and json_unquote(json_extract(h.changes, '\$.action')) = 'assigned'
                                   and h.created_at > lead_comments.created_at
                                 order by h.created_at asc, h.id asc
                                 limit 1
                            ), '\$.old_person_id')),
                            'null'
                        ),
                        (select l.responsible_person_id from leads l where l.id = lead_comments.lead_id)
                    ) in ({$ids})
               )
             order by lead_comments.created_at desc, lead_comments.id desc
        ";

        return collect(DB::select($sql, [$lead->id]))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
