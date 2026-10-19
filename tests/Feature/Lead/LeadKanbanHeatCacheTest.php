<?php

namespace Tests\Feature\Lead;

use App\Http\Controllers\Api\StageController;
use App\Http\Resources\Lead\KanbanLeadCardResource;
use App\Models\Lead;
use App\Models\Stage;
use App\Models\User;
use App\Services\LeadKanbanHeatCache;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadKanbanHeatCacheTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['lead_scoring.kanban_heat.ttl_seconds' => 60]);
        Cache::flush();
    }

    public function test_cached_totals_match_a_fresh_count_and_are_not_shared(): void
    {
        $agent = $this->makeUser('sales');
        $other = $this->makeUser('sales');
        $stageId = $this->leadStageId();

        $this->makeLead($agent, $stageId, ['status_lead' => 'hot']);
        $this->makeLead($other, $stageId, ['status_lead' => 'cold']);
        LeadKanbanHeatCache::bump();

        $agentTotals = $this->analytics($agent);
        $otherTotals = $this->analytics($other);

        $this->assertSame(1, $agentTotals['tempHot']);
        $this->assertSame(0, $agentTotals['tempCold']);
        $this->assertSame(0, $otherTotals['tempHot']);
        $this->assertSame(1, $otherTotals['tempCold']);
        $this->assertNotSame(
            LeadKanbanHeatCache::key($agent, Request::create('/x', 'GET')),
            LeadKanbanHeatCache::key($other, Request::create('/x', 'GET'))
        );

        DB::flushQueryLog();
        DB::enableQueryLog();
        $again = $this->analytics($agent);
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertSame($agentTotals, $again);
        $this->assertFalse(
            $queries->contains(fn ($sql) => str_contains($sql, 'temp_cold')),
            'A repeat load for the same user must reuse the heat totals'
        );
    }

    public function test_heat_stage_and_owner_changes_refresh_the_totals(): void
    {
        $agent = $this->makeUser('sales');
        $nextOwner = $this->makeUser('sales');
        $stageId = $this->leadStageId();
        $lead = $this->makeLead($agent, $stageId, ['status_lead' => 'cold']);
        LeadKanbanHeatCache::bump();

        $this->assertSame(1, $this->analytics($agent)['tempCold']);

        $lead->update(['status_lead' => 'hot']);
        $afterHeat = $this->analytics($agent);
        $this->assertSame(0, $afterHeat['tempCold']);
        $this->assertSame(1, $afterHeat['tempHot']);
        $this->assertSame(0, $this->analytics($nextOwner)['tempHot']);

        $lead->update([
            'stage_id' => 5,
            'interaction_result' => 'answered',
        ]);
        $this->assertSame(1, $this->analytics($agent)['callAnswered']);

        $lead->update(['responsible_person_id' => $nextOwner->id]);
        $this->assertSame(0, $this->analytics($agent)['tempHot']);
        $this->assertSame(1, $this->analytics($nextOwner)['tempHot']);
        $this->assertSame(1, $this->analytics($nextOwner)['callAnswered']);
    }

    public function test_role_scopes_and_user_30_and_33_see_their_own_totals(): void
    {
        $root = $this->makeUser('super_admin', ['parent_id' => null]);
        $branch = $this->makeUser('admin', ['parent_id' => $root->id]);
        $office = $this->makeUser('manager', ['parent_id' => $branch->id]);
        $branchAdmin = $this->makeUser('branch_admin', ['parent_id' => $office->id]);
        $manager = $this->makeUser('manager', ['parent_id' => $office->id]);
        $teamLead = $this->makeUser('team_lead', ['parent_id' => $manager->id]);
        $agent = $this->makeUser('sales', ['parent_id' => $teamLead->id]);
        $outsider = $this->makeUser('sales');
        $stageId = $this->leadStageId();

        $this->makeLead($agent, $stageId, ['status_lead' => 'warm']);
        $this->makeLead($agent, $stageId, [
            'status_lead' => 'hot',
            'revert' => now(),
        ]);
        $this->makeLead($outsider, $stageId, ['status_lead' => 'hot']);
        LeadKanbanHeatCache::bump();

        $this->assertSame(1, $this->analytics($agent)['tempWarm']);
        $this->assertSame(0, $this->analytics($agent)['tempHot'], 'Sales agents do not see reverted leads');
        $this->assertSame(1, $this->analytics($teamLead)['tempWarm']);
        $this->assertSame(0, $this->analytics($teamLead)['tempHot']);
        $this->assertSame(1, $this->analytics($manager)['tempWarm']);
        $this->assertSame(0, $this->analytics($manager)['tempHot']);
        $this->assertSame(0, $this->analytics($outsider)['tempWarm']);
        $this->assertSame(1, $this->analytics($outsider)['tempHot']);
        $this->assertSame(1, $this->analytics($branchAdmin)['tempHot'], 'Branch admins still see reverted leads in their branch');

        $filtered = $this->analytics($outsider, ['status_lead' => 'hot']);
        $this->assertSame(1, $filtered['tempHot']);
        $this->assertSame(0, $filtered['tempWarm']);
        $this->assertSame(0, $this->analytics($agent, ['status_lead' => 'hot'])['tempHot']);

        $super = $this->makeUser('super_admin');
        LeadKanbanHeatCache::bump();
        $unscoped = $this->analytics($super);
        foreach ([30, 33] as $userId) {
            $privileged = User::query()->find($userId);
            $this->assertNotNull($privileged);
            $this->assertFalse($privileged->hasRole('super_admin'));
            $this->assertSame($unscoped, $this->analytics($privileged));
            $this->assertNotSame(
                LeadKanbanHeatCache::key($privileged, Request::create('/x', 'GET')),
                LeadKanbanHeatCache::key($agent, Request::create('/x', 'GET'))
            );
        }
        $this->assertGreaterThan($this->analytics($agent)['tempHot'], $unscoped['tempHot']);
    }

    public function test_card_column_select_keeps_the_response_fields(): void
    {
        $lead = Lead::query()->first();
        if (! $lead) {
            $this->markTestSkipped('Need a lead');
        }

        $with = [
            'addedBy:id,name,display_name,avatar',
            'responsiblePerson:id,name,display_name,avatar,parent_id',
            'propertyType:id,name',
            'area:id,name',
        ];
        $full = (new KanbanLeadCardResource(Lead::query()->with($with)->find($lead->id)))->resolve();
        $slimModel = Lead::query()->select(KanbanLeadCardResource::cardSelect())->with($with)->find($lead->id);
        $slim = (new KanbanLeadCardResource($slimModel))->resolve();

        $this->assertEquals($full, $slim);
        $this->assertArrayNotHasKey('raw_meta_data', $slim);
        $this->assertArrayHasKey('api_first_question', $slim);
    }

    private function analytics(User $user, array $query = []): array
    {
        auth()->setUser($user);
        $response = app(StageController::class)->getLeadAnalytics(
            Request::create('/api/stages/kanban/lead-analytics', 'GET', $query)
        );
        $json = json_decode($response->getContent(), true);

        return $json['data'];
    }

    private function leadStageId(): int
    {
        $stageId = Stage::query()->where('stage_type', 'lead')->where('id', '!=', 10)->where('id', '!=', 5)->value('id');
        if (! $stageId) {
            $this->markTestSkipped('Need a lead stage');
        }

        return (int) $stageId;
    }

    private function makeLead(User $owner, int $stageId, array $extra = []): Lead
    {
        return Lead::withoutEvents(function () use ($owner, $stageId, $extra) {
            return Lead::query()->create(array_merge([
                'lead_name' => 'Heat '.uniqid(),
                'first_name' => 'Heat',
                'stage_id' => $stageId,
                'responsible_person_id' => $owner->id,
                'added_by' => $owner->id,
                'assignment_hold' => 0,
                'available_to_everyone' => 0,
                'no_answer_count' => 0,
                'lead_pool_visits' => 0,
                'notified_revert' => 0,
                'status_lead' => 'cold',
            ], $extra));
        });
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
}
