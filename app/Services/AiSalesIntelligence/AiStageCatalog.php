<?php

namespace App\Services\AiSalesIntelligence;

use App\Models\Stage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class AiStageCatalog
{
  /** @var Collection<int, Stage>|null */
    protected ?Collection $stages = null;

    public function leadStages(): Collection
    {
        if ($this->stages === null) {
            $this->stages = Cache::remember('ai_sales_intelligence:lead_stages', 600, function () {
                return Stage::query()
                    ->where('stage_type', 'lead')
                    ->orderBy('order')
                    ->get()
                    ->keyBy('id');
            });
        }

        return $this->stages;
    }

    public function orderForStageId(?int $stageId): ?int
    {
        if (!$stageId) {
            return null;
        }

        $stage = $this->leadStages()->get($stageId);

        return $stage ? (int) $stage->order : null;
    }

    public function stageIdsByOrders(array $orders): array
    {
        return $this->leadStages()
            ->filter(fn (Stage $s) => in_array((int) $s->order, $orders, true))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function terminalStageIds(): array
    {
        return $this->stageIdsByOrders([6, 8, 9, 10]);
    }

    public function activeStageIds(): array
    {
        $terminal = $this->terminalStageIds();

        return $this->leadStages()
            ->reject(fn (Stage $s) => in_array((int) $s->id, $terminal, true))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public static function clearCache(): void
    {
        Cache::forget('ai_sales_intelligence:lead_stages');
    }
}
