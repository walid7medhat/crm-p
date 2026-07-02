<?php

namespace Tests\Feature\AiSalesIntelligence;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiSalesIntelligenceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function createSuperAdmin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        Role::findOrCreate('super_admin', 'api');
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_dashboard_requires_super_admin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $this->getJson('/api/ai-sales-intelligence/dashboard')->assertStatus(403);
    }

    public function test_dashboard_returns_structure_for_super_admin(): void
    {
        if (!\Schema::hasTable('ai_agent_metrics')) {
            $this->markTestSkipped('AI Sales Intelligence tables not migrated.');
        }

        $admin = $this->createSuperAdmin();
        $token = auth('api')->login($admin);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/ai-sales-intelligence/dashboard');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['agents_tracked', 'avg_ai_score'],
                    'agents',
                    'rankings',
                    'alerts',
                ],
            ]);
    }
}
