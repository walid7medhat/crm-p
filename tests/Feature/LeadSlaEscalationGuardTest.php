<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadAssignmentSetting;
use App\Models\LeadHistory;
use App\Models\SalesPerformance;
use App\Models\User;
use App\Services\LeadAssignmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeadSlaEscalationGuardTest extends TestCase
{
    use DatabaseTransactions;

    public static function blockingSettings(): array
    {
        return [
            'auto assign disabled' => [['auto_assign' => false]],
            'system disabled' => [['system_disabled' => true]],
            'sla escalation disabled' => [['sla_escalation_enabled' => false]],
            'sla minutes below 5' => [['sla_minutes' => 4]],
        ];
    }

    #[DataProvider('blockingSettings')]
    public function test_batch_does_not_reassign_or_write_history_when_escalation_is_blocked(array $override): void
    {
        if (! $this->tablesReady()) {
            $this->markTestSkipped('SLA escalation tables are not migrated.');
        }

        $owner = $this->makeUser();
        $lead = $this->eligibleLead($owner);
        $this->enableEscalation($lead->stage_id, $override);

        $service = Mockery::mock(LeadAssignmentService::class)->makePartial();
        $service->shouldReceive('refreshSalesPerformanceSnapshot')->never();
        $service->shouldReceive('pickBestAgent')->never();

        $this->assertSame(0, $service->processSlaEscalations());
        $this->assertLeadUntouched($lead, $owner->id);
    }

    public function test_disabling_auto_assign_during_a_running_batch_stops_before_ownership_changes(): void
    {
        if (! $this->tablesReady()) {
            $this->markTestSkipped('SLA escalation tables are not migrated.');
        }

        $owner = $this->makeUser();
        $winner = $this->makeUser();
        $lead = $this->eligibleLead($owner);
        $this->enableEscalation($lead->stage_id);

        SalesPerformance::query()->updateOrCreate(['sales_id' => $owner->id], ['score' => 10]);
        SalesPerformance::query()->updateOrCreate(['sales_id' => $winner->id], ['score' => 90]);

        $service = Mockery::mock(LeadAssignmentService::class)->makePartial();
        $service->shouldReceive('refreshSalesPerformanceSnapshot')->once()->andReturnNull();
        $service->shouldReceive('pickBestAgent')->once()->andReturnUsing(function () use ($winner) {
            LeadAssignmentSetting::current()->update(['auto_assign' => false]);

            return [
                'user' => $winner,
                'reason' => 'higher performer',
                'explanation' => [],
                'final_score' => 90,
                'attendance_score' => 1,
                'performance_score' => 1,
                'load_score' => 1,
                'fairness_score' => 1,
            ];
        });

        $this->assertSame(0, $service->processSlaEscalations());
        $this->assertLeadUntouched($lead, $owner->id);
        $this->assertFalse((bool) LeadAssignmentSetting::current()->auto_assign);
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('lead_assignment_settings')
            && Schema::hasTable('leads')
            && Schema::hasTable('lead_histories')
            && Schema::hasTable('stages')
            && Schema::hasTable('sales_performance');
    }

    private function enableEscalation(int $stageId, array $override = []): void
    {
        $settings = LeadAssignmentSetting::current();
        $settings->update(array_merge([
            'auto_assign' => true,
            'system_disabled' => false,
            'sla_escalation_enabled' => true,
            'sla_minutes' => 30,
            'assigned_stage_id' => $stageId,
        ], $override));
    }

    private function eligibleLead(User $owner): Lead
    {
        $stageId = DB::table('stages')->insertGetId([
            'name' => 'SLA Guard '.uniqid(),
            'order' => 9000,
            'stage_type' => 'lead',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Lead::withoutEvents(fn () => Lead::query()->create([
            'lead_name' => 'SLA Guard '.uniqid(),
            'first_name' => 'SLA',
            'stage_id' => $stageId,
            'responsible_person_id' => $owner->id,
            'added_by' => $owner->id,
            'assignment_hold' => 0,
            'available_to_everyone' => 0,
            'no_answer_count' => 0,
            'lead_pool_visits' => 0,
            'notified_revert' => 0,
            'first_contacted_at' => null,
            'last_sla_escalation_at' => null,
            'last_stage_change_at' => now()->subHours(2),
        ]));
    }

    private function makeUser(): User
    {
        return User::factory()->create([
            'status' => 'active',
            'email' => uniqid('sla', true).'@example.test',
        ]);
    }

    private function assertLeadUntouched(Lead $lead, int $ownerId): void
    {
        $fresh = $lead->fresh();
        $this->assertSame($ownerId, (int) $fresh->responsible_person_id);
        $this->assertNull($fresh->last_sla_escalation_at);

        $escalations = LeadHistory::query()
            ->where('lead_id', $lead->id)
            ->get()
            ->filter(fn (LeadHistory $row) => ($row->changes['action'] ?? null) === 'sla_escalation');

        $this->assertCount(0, $escalations);
    }
}
