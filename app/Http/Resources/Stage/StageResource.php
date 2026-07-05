<?php

namespace App\Http\Resources\Stage;

use App\Http\Resources\Lead\LeadResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'order' => $this->order,
            'color' => $this->color,

            // ✅ Auto Revert Settings
            'auto_revert' => (bool) $this->auto_revert,
            'revert_after_hours' => $this->revert_after_hours,
            'notify_before_minutes' => $this->notify_before_minutes,

            'leads_count' => $this->whenCounted('leads'),

            'leads'=> $this->whenLoaded('leads', function () {
                return LeadResource::collection($this->leads);
            }),
            'revert_to_stage_id' => $this->revert_to_stage_id,
            'revert_notification_message' => $this->revert_notification_message,
            'notification_times' => $this->notification_times ?? [30, 15, 5],

            'revert_to_stage' => $this->whenLoaded('revertToStage', function () {
                return [
                    'id' => $this->revertToStage?->id,
                    'name' => $this->revertToStage?->name,
                    'order' => $this->revertToStage?->order,
                    'color' => $this->revertToStage?->color,
                ];
            }),
            'notification_times_readable' => $this->getReadableNotificationTimes(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
     protected function getReadableNotificationTimes(): array
    {
        $times = $this->notification_times ?? [30, 15, 5];
        
        return array_map(function ($minutes) {
            if ($minutes >= 60) {
                $hours = floor($minutes / 60);
                $remainingMinutes = $minutes % 60;
                return $remainingMinutes > 0 
                    ? "{$hours}h {$remainingMinutes}m" 
                    : "{$hours}h";
            }
            return "{$minutes}m";
        }, $times);
    }
}
