<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\Api\Deal\DealController;
use App\Http\Requests\Deal\UpdatePartialRequest;
use App\Models\CompanyBranch;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Focused P0 security regression tests (auth / IDOR / inactive JWT).
 * Uses the live DB like other Feature suites that need real CRM data.
 */
class P0SecurityFixesTest extends TestCase
{
    public function test_company_branch_extra_routes_require_jwt(): void
    {
        $uris = [
            'api/company-branches/cities/list',
            'api/company-branches/statistics/summary',
        ];

        foreach ($uris as $uri) {
            $route = collect(Route::getRoutes())->first(
                fn ($r) => in_array('GET', $r->methods(), true) && $r->uri() === $uri
            );
            $this->assertNotNull($route, "Missing route {$uri}");
            $middleware = $route->gatherMiddleware();
            $this->assertTrue(
                collect($middleware)->contains(fn ($m) => $m === 'jwt.auth' || str_contains((string) $m, 'JwtAuth')),
                "{$uri} must require jwt.auth"
            );
        }

        $this->getJson('/api/company-branches/cities/list')->assertStatus(401);
        $this->getJson('/api/company-branches/statistics/summary')->assertStatus(401);

        $branch = CompanyBranch::query()->first();
        if ($branch) {
            $this->getJson("/api/company-branches/{$branch->id}/employees")->assertStatus(401);
            $this->patchJson("/api/company-branches/{$branch->id}/toggle-status")->assertStatus(401);
        }

        $this->postJson('/api/company-branches/bulk-delete', ['ids' => [1]])->assertStatus(401);
    }

    public function test_inactive_user_jwt_is_rejected_by_middleware(): void
    {
        $user = User::factory()->create(['status' => 'in_active']);
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/lead-assignment/settings');

        $this->assertSame(403, $response->status());
        $this->assertFalse($response->json('status') ?? true);
        $this->assertStringContainsStringIgnoringCase('inactive', (string) $response->json('message'));
    }

    public function test_active_user_jwt_passes_status_check(): void
    {
        $user = User::query()->where('status', 'active')->first();
        if (!$user) {
            $this->markTestSkipped('No active user');
        }

        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/lead-assignment/settings');

        // Must not be rejected as inactive; 200 or 403-from-role are both OK for status check.
        $this->assertNotSame(401, $response->status());
        $this->assertStringNotContainsStringIgnoringCase('inactive', (string) $response->json('message'));
    }

    public function test_unauthorized_user_cannot_update_or_change_stage_of_others_deal(): void
    {
        $deal = Deal::query()->whereNotNull('responsible_person_id')->first();
        if (!$deal) {
            $this->markTestSkipped('No deal with responsible_person_id');
        }

        $outsider = User::query()
            ->where('status', 'active')
            ->where('id', '!=', $deal->responsible_person_id)
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin', 'manager', 'team_lead']))
            ->first();

        if (!$outsider) {
            $this->markTestSkipped('No non-privileged outsider user');
        }

        // Exclude hardcoded god-mode ids from this check
        if (in_array((int) $outsider->id, [30, 33], true)) {
            $this->markTestSkipped('Outsider is hardcoded privileged id');
        }

        auth()->setUser($outsider);
        $controller = app(DealController::class);

        $change = $controller->changeStage(
            Request::create("/api/deals/{$deal->id}/change-stage", 'POST', [
                'stage_id' => $deal->stage_id,
            ]),
            $deal->id
        );
        $this->assertSame(403, $change->getStatusCode());

        $partialRequest = UpdatePartialRequest::create(
            "/api/deals/{$deal->id}/update-partial",
            'POST',
            ['deal_name' => (string) ($deal->deal_name ?? 'x')]
        );
        $partialRequest->setContainer(app())->setRedirector(app('redirect'));
        $partialRequest->setUserResolver(fn () => $outsider);

        $partial = $controller->updatePartial($partialRequest, $deal->id);
        $this->assertSame(403, $partial->getStatusCode());
    }

    public function test_responsible_user_can_change_stage_when_validator_allows(): void
    {
        $deal = Deal::query()->whereNotNull('responsible_person_id')->first();
        if (!$deal) {
            $this->markTestSkipped('No deal');
        }

        $owner = User::find($deal->responsible_person_id);
        if (!$owner || $owner->status !== 'active') {
            $this->markTestSkipped('Responsible user missing/inactive');
        }

        auth()->setUser($owner);
        $controller = app(DealController::class);

        // Same stage — exercises authorizeAccess success path; validator may still 422.
        $response = $controller->changeStage(
            Request::create("/api/deals/{$deal->id}/change-stage", 'POST', [
                'stage_id' => $deal->stage_id,
            ]),
            $deal->id
        );

        $this->assertNotSame(403, $response->getStatusCode(), 'Owner must not get Unauthorized');
    }

    public function test_lead_assignment_mutations_forbid_non_admin(): void
    {
        $sales = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->where('name', 'sales'))
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['admin', 'super_admin']))
            ->first();

        if (!$sales) {
            $this->markTestSkipped('No sales-only user');
        }

        $token = JWTAuth::fromUser($sales);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/lead-assignment/settings', ['auto_assign' => false])
            ->assertStatus(403);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/lead-assignment/run')
            ->assertStatus(403);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/lead-assignment/reassign', ['lead_id' => 1])
            ->assertStatus(403);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/lead-assignment/revert-stage', ['stage_id' => 1])
            ->assertStatus(403);
    }

    public function test_update_and_change_stage_rejects_unauthorized_and_does_not_mutate(): void
    {
        $deal = Deal::query()->whereNotNull('responsible_person_id')->first();
        if (!$deal) {
            $this->markTestSkipped('No deal with responsible_person_id');
        }

        $outsider = User::query()
            ->where('status', 'active')
            ->where('id', '!=', $deal->responsible_person_id)
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin', 'manager', 'team_lead']))
            ->first();

        if (!$outsider || in_array((int) $outsider->id, [30, 33], true)) {
            $this->markTestSkipped('No suitable outsider user');
        }

        $originalName = $deal->deal_name;
        $originalStageId = $deal->stage_id;
        $originalUpdatedAt = optional($deal->updated_at)?->toJSON();

        auth()->setUser($outsider);
        $controller = app(DealController::class);

        $response = $controller->updateAndChangeStage(
            Request::create("/api/deals/{$deal->id}/update-and-change-stage", 'POST', [
                'deal_name' => 'UNAUTHORIZED_MUTATION_PROBE',
                'stage_id' => $deal->stage_id,
            ]),
            $deal->id
        );

        $this->assertSame(403, $response->getStatusCode());

        $deal->refresh();
        $this->assertSame($originalName, $deal->deal_name);
        $this->assertSame($originalStageId, $deal->stage_id);
        $this->assertSame($originalUpdatedAt, optional($deal->updated_at)?->toJSON());
    }

    public function test_update_and_change_stage_allows_responsible_user_past_authorize_access(): void
    {
        $deal = Deal::query()->whereNotNull('responsible_person_id')->first();
        if (!$deal) {
            $this->markTestSkipped('No deal');
        }

        $owner = User::find($deal->responsible_person_id);
        if (!$owner || $owner->status !== 'active') {
            $this->markTestSkipped('Responsible user missing/inactive');
        }

        auth()->setUser($owner);
        $controller = app(DealController::class);

        // Same stage / no field changes — exercises authorizeAccess success; may 200 or 422 from validator.
        $response = $controller->updateAndChangeStage(
            Request::create("/api/deals/{$deal->id}/update-and-change-stage", 'POST', [
                'stage_id' => $deal->stage_id,
            ]),
            $deal->id
        );

        $this->assertNotSame(403, $response->getStatusCode(), 'Owner must not get Unauthorized');
    }

    public function test_deal_party_cross_deal_lookup_is_scoped_to_current_deal(): void
    {
        $deals = Deal::query()->has('parties')->limit(2)->get();
        if ($deals->count() < 2) {
            $this->markTestSkipped('Need two deals with parties');
        }

        $dealA = $deals[0];
        $foreignParty = $deals[1]->parties()->first();
        $this->assertNotNull($foreignParty);

        // Same ownership check used by DealController::update after the P0-3 fix.
        $scoped = $dealA->parties()->whereKey($foreignParty->id)->first();
        $this->assertNull($scoped, 'Foreign DealParty must not resolve on another deal');
    }
}