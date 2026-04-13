<?php

namespace App\Services\Mobile;

use App\Http\Controllers\Api\KanbanSettingsController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\StageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MobileKanbanDataService
{
    public function __construct(
        protected StageController $stageController,
        protected KanbanSettingsController $kanbanSettingsController,
        protected LeadController $leadController
    ) {}

    /**
     * Reuses existing web stack (same permissions, filters, pagination) — additive only.
     *
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $kanbanResponse = $this->stageController->getStagesWithLeads($request);
        $kanbanJson = json_decode($kanbanResponse->getContent(), true);

        if (! ($kanbanJson['status'] ?? false)) {
            Log::warning('mobile.kanban.stages_failed', ['message' => $kanbanJson['message'] ?? 'unknown']);

            throw new \RuntimeException($kanbanJson['message'] ?? 'Failed to load kanban');
        }

        $stagesPayload = $kanbanJson['data']['stages'] ?? [];

        $stages = [];
        $leadsByStage = [];

        foreach ($stagesPayload as $stage) {
            $rawLeads = $stage['leads'] ?? [];
            if (isset($rawLeads['data']) && is_array($rawLeads['data'])) {
                $rawLeads = $rawLeads['data'];
            }

            $flatLeads = [];
            foreach ($rawLeads as $leadRow) {
                if (! is_array($leadRow)) {
                    continue;
                }
                $flatLeads[] = MobileKanbanPayloadTransformer::flattenLeadArray($leadRow);
            }

            $sid = (string) ($stage['id'] ?? '');
            $leadsByStage[$sid] = $flatLeads;

            $stages[] = MobileKanbanPayloadTransformer::normalizeStageRow($stage, $flatLeads);
        }

        $settingsResponse = $this->kanbanSettingsController->getSettings();
        $settingsJson = json_decode($settingsResponse->getContent(), true);
        $settingsData = $settingsJson['data'] ?? [];

        $kanbanSettings = [
            'card_fields' => $settingsData['card_fields'] ?? [],
            'revert_hours' => $settingsData['revert_hours'] ?? null,
        ];

        $usersResponse = $this->leadController->getAvailableResponsiblePersons();
        $usersJson = json_decode($usersResponse->getContent(), true);
        $assignableUsers = $usersJson['data'] ?? [];

        return [
            'stages' => $stages,
            'leads_by_stage' => $leadsByStage,
            'kanban_settings' => $kanbanSettings,
            'assignable_users' => $assignableUsers,
            'meta' => [
                'source' => 'stages.kanban.stages-with-leads',
                'pagination' => $kanbanJson['data']['pagination'] ?? null,
            ],
        ];
    }
}
