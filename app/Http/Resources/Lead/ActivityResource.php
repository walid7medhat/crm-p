<?php

namespace App\Http\Resources\Lead;

use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user->name ?? null,
            'title' => $this->title,
            'reminder_date' => $this->reminder_date->format('Y-m-d H:i:s'),
            'is_completed' => $this->is_completed,
            'status' => $this->is_completed ? 'Completed' : ($this->reminder_date < now() ? 'Overdue' : 'Pending'),
            'reminders'=>$this->reminders,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}