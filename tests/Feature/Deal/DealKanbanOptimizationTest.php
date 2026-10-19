<?php

namespace Tests\Feature\Deal;

use App\Http\Controllers\Api\Deal\DealController;
use App\Http\Resources\Deal\DealKanbanCardResource;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Deal Kanban card payload must stay the same while assignment history
 * and user hierarchy loading stay scoped to the deals on the page.
 */
class DealKanbanOptimizationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_assignment_date_uses_the_newest_matching_row_and_ignores_other_null_leads(): void
    {
        $template = Deal::query()->first();
        $lead = Lead::query()->first();
        if (! $template || ! $lead) {
            $this->markTestSkipped('Need a deal and a lead');
        }

        $linked = $this->makeDeal($template, $lead->id);
        $unlinked = $this->makeDeal($template, null);
        $other = $this->makeDeal($template, null);
        $olderUser = $this->makeUser('sales');
        $newerUser = $this->makeUser('sales');

        $this->history($unlinked->id, null, $olderUser->id, '2026-02-01 09:00:00');
        $trap = $this->history($other->id, null, $newerUser->id, '2026-02-01 12:00:00');
        $this->history($linked->id, $lead->id, $olderUser->id, '2026-02-01 08:00:00');
        $leadAssignment = $this->history(null, $lead->id, $newerUser->id, '2026-02-01 11:00:00');

        $cards = $this->cards([$linked, $unlinked]);

        $this->assertSame($newerUser->id, $cards[$linked->id]['parent']['id']);
        $this->assertSame(
            $leadAssignment->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            Carbon::parse($cards[$linked->id]['assigned_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i:s')
        );
        $this->assertSame($olderUser->id, $cards[$unlinked->id]['parent']['id']);
        $this->assertSame(
            '2026-02-01 09:00:00',
            Carbon::parse($cards[$unlinked->id]['assigned_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i:s')
        );
        $this->assertNotSame($trap->id, $leadAssignment->id);
    }

    public function test_assignment_tie_breaks_on_history_id_when_timestamps_match(): void
    {
        $template = Deal::query()->first();
        $lead = Lead::query()->first();
        if (! $template || ! $lead) {
            $this->markTestSkipped('Need a deal and a lead');
        }

        $deal = $this->makeDeal($template, $lead->id);
        $lower = $this->makeUser('sales');
        $higher = $this->makeUser('sales');
        $stamp = '2026-03-01 10:00:00';

        $this->history($deal->id, $lead->id, $lower->id, $stamp);
        $this->history(null, $lead->id, $higher->id, $stamp);

        $cards = $this->cards([$deal]);

        $this->assertSame($higher->id, $cards[$deal->id]['parent']['id']);
        $this->assertTrue(Carbon::parse($cards[$deal->id]['assigned_at'])->equalTo(Carbon::parse($stamp)));
    }

    public function test_visible_for_keeps_role_scopes_and_the_user_30_and_33_exception(): void
    {
        $template = Deal::query()->first();
        if (! $template) {
            $this->markTestSkipped('Need a deal');
        }

        $super = $this->makeUser('super_admin', ['parent_id' => null]);
        $branch = $this->makeUser('admin', ['parent_id' => $super->id]);
        $office = $this->makeUser('manager', ['parent_id' => $branch->id]);
        $branchAdmin = $this->makeUser('branch_admin', ['parent_id' => $office->id]);
        $manager = $this->makeUser('manager', ['parent_id' => $office->id]);
        $teamLead = $this->makeUser('team_lead', ['parent_id' => $manager->id]);
        $agent = $this->makeUser('sales', ['parent_id' => $teamLead->id]);
        $sameBranchAgent = $this->makeUser('sales', ['parent_id' => $office->id]);

        $otherSuper = $this->makeUser('super_admin', ['parent_id' => null]);
        $otherBranch = $this->makeUser('admin', ['parent_id' => $otherSuper->id]);
        $outsider = $this->makeUser('sales', ['parent_id' => $otherBranch->id]);

        $agentDeal = $this->makeDeal($template, null, $agent->id);
        $sameBranchDeal = $this->makeDeal($template, null, $sameBranchAgent->id);
        $outsiderDeal = $this->makeDeal($template, null, $outsider->id);
        $ids = [$agentDeal->id, $sameBranchDeal->id, $outsiderDeal->id];

        $this->assertVisible($super, $ids, $ids);
        $this->assertVisible($agent, $ids, [$agentDeal->id]);
        $this->assertVisible($teamLead, $ids, [$agentDeal->id]);
        $this->assertVisible($manager, $ids, [$agentDeal->id]);
        $this->assertVisible($branchAdmin, $ids, [$agentDeal->id, $sameBranchDeal->id]);
        $this->assertVisible($branch, $ids, [$agentDeal->id, $sameBranchDeal->id]);

        $this->assertTrue($super->canViewDeal($outsiderDeal));
        $this->assertTrue($agent->canViewDeal($agentDeal));
        $this->assertFalse($agent->canViewDeal($outsiderDeal));
        $this->assertTrue($teamLead->canViewDeal($agentDeal));
        $this->assertFalse($teamLead->canViewDeal($outsiderDeal));
        $this->assertTrue($manager->canViewDeal($agentDeal));
        $this->assertFalse($manager->canViewDeal($sameBranchDeal));
        $this->assertTrue($branchAdmin->canViewDeal($sameBranchDeal));
        $this->assertFalse($branchAdmin->canViewDeal($outsiderDeal));

        foreach ([30, 33] as $userId) {
            $privileged = User::query()->find($userId);
            $this->assertNotNull($privileged, "User {$userId} is part of the deal access rule");
            $this->assertFalse($privileged->hasRole('super_admin'));
            $this->assertTrue($privileged->canViewDeal($outsiderDeal));
            $this->assertVisible($privileged, $ids, $ids);
            $this->assertStringNotContainsString(
                'responsible_person_id',
                Deal::query()->visibleFor($privileged)->toSql()
            );
        }
    }

    public function test_grouped_board_does_not_load_users_one_at_a_time(): void
    {
        $admin = User::role('super_admin')->first();
        if (! $admin) {
            $this->markTestSkipped('Need a super admin');
        }

        auth()->setUser($admin);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = app(DealController::class)->getDealsGroupedByStage(
            Request::create('/api/deals/grouped-by-stage', 'GET', [
                'deal_type' => 'primary',
                'per_page' => 10,
            ])
        );

        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(200, $response->getStatusCode());
        $singleUsers = $queries->filter(
            fn ($query) => preg_match('/from `users` where `users`\.`id` = \?/i', $query['query'])
        )->count();
        $this->assertSame(0, $singleUsers);

        $historySql = $queries->pluck('query')->filter(fn ($sql) => str_contains($sql, 'lead_histories'))->implode("\n");
        $this->assertStringContainsString('row_number()', strtolower($historySql));
        $this->assertStringContainsString('`lead_id` is not null', strtolower($historySql));
    }

    /**
     * @param  list<Deal>  $deals
     * @return array<int, array<string, mixed>>
     */
    private function cards(array $deals): array
    {
        $loaded = Deal::query()
            ->whereIn('id', array_map(fn (Deal $deal) => $deal->id, $deals))
            ->with(['stage:id,name,color,order', 'responsiblePerson', 'addedBy'])
            ->get();

        DealKanbanCardResource::primeForCollection($loaded);
        DealKanbanCardResource::warmCardUsers($loaded);
        try {
            $cards = [];
            foreach ($loaded as $deal) {
                $cards[$deal->id] = (new DealKanbanCardResource($deal))->resolve();
            }
        } finally {
            DealKanbanCardResource::clearCollectionPrime();
        }

        return $cards;
    }

    private function history(?int $dealId, ?int $leadId, int $userId, string $createdAt): LeadHistory
    {
        $history = LeadHistory::query()->create([
            'deal_id' => $dealId,
            'lead_id' => $leadId,
            'user_id' => $userId,
            'changes' => ['action' => 'assigned'],
        ]);
        $history->created_at = $createdAt;
        $history->save();

        return $history->fresh();
    }

    private function makeDeal(Deal $template, ?int $leadId, ?int $responsibleId = null): Deal
    {
        return Deal::query()->create([
            'added_by' => $template->added_by,
            'lead_id' => $leadId,
            'deal_number' => 'KANBAN-'.uniqid(),
            'deal_type' => $template->deal_type,
            'stage_id' => $template->stage_id,
            'deal_name' => 'Kanban optimization',
            'status' => $template->status,
            'responsible_person_id' => $responsibleId ?: $template->responsible_person_id,
            'created_by' => $template->created_by ?: $template->added_by,
        ]);
    }

    private function makeUser(string $role, array $extra = []): User
    {
        $user = User::factory()->create(array_merge([
            'status' => 'active',
            'email' => uniqid($role, true).'@example.test',
            'name' => $role.' '.uniqid(),
        ], $extra));
        Role::findOrCreate($role, 'api');
        $user->assignRole($role);

        return $user->fresh();
    }

    /**
     * @param  list<int>  $candidateIds
     * @param  list<int>  $expectedIds
     */
    private function assertVisible(User $user, array $candidateIds, array $expectedIds): void
    {
        $visible = Deal::query()
            ->visibleFor($user)
            ->whereIn('id', $candidateIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        sort($visible);
        sort($expectedIds);
        $this->assertSame($expectedIds, $visible, $user->roles->pluck('name')->implode(',').' deal visibility');
    }
}
