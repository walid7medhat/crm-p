<?php

namespace App\Http\Resources\Lead;

use App\Http\Resources\User\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight lead payload for Kanban board cards (avoids per-lead history/duplicate queries).
 */
class KanbanLeadCardResource extends JsonResource
{
    /** @var array<string, int> */
    protected static array $duplicateCountsByPhone = [];

    /** @var array<int, bool> */
    protected static array $serviceDuplicateByLeadId = [];

    public static function setKanbanMeta(array $duplicateCountsByPhone, array $serviceDuplicateByLeadId): void
    {
        static::$duplicateCountsByPhone = $duplicateCountsByPhone;
        static::$serviceDuplicateByLeadId = $serviceDuplicateByLeadId;
    }

    public static function clearKanbanMeta(): void
    {
        static::$duplicateCountsByPhone = [];
        static::$serviceDuplicateByLeadId = [];
    }

    public function toArray($request): array
    {
        $phone = $this->work_phone;
        $duplicateNo = $phone
            ? (static::$duplicateCountsByPhone[$phone] ?? 0)
            : 0;

        return [
            'id' => $this->id,
            'added_by' => $this->added_by,
            'lead_name' => $this->lead_name,
            'lead_number' => $this->lead_number,
            'stage_id' => $this->stage_id,
            'salutation' => $this->salutation,
            'first_name' => $this->first_name,
            'second_name' => $this->second_name,
            'last_name' => $this->last_name,
            'whatsapp_number' => $this->whatsapp_number,
            'work_phone' => $this->work_phone,
            'work_phone_2' => $this->work_phone_2,
            'email' => $this->email,
            'lead_source' => $this->lead_source,
            'lead_branch_source' => $this->lead_branch_source,
            'source_information' => $this->source_information,
            'status_lead' => $this->status_lead,
            'interaction_result' => $this->interaction_result,
            'bedrooms' => $this->bedrooms === 0 || $this->bedrooms === '0' ? 'studio' : $this->bedrooms,
            'purpose_buying' => $this->purpose_buying,
            'extra_client_requirements' => $this->extra_client_requirements ?? [],
            'responsible_person_id' => $this->responsible_person_id,
            'budget' => (int) $this->budget,
            'budget_from' => (int) $this->budget_from,
            'budget_to' => (int) $this->budget_to,
            'property_status' => $this->property_status,
            'lead_type' => $this->lead_type,
            'property_type_id' => $this->property_type_id,
            'area_id' => $this->area_id,
            'property_type' => $this->propertyType?->name,
            'area' => $this->area?->title,
            'created_at' => $this->created_at?->setTimezone(config('app.timezone')),
            'updated_at' => $this->updated_at,
            'duplicate_no' => $duplicateNo,
            'duplicate_ids' => [],
            'is_reverted' => ! is_null($this->revert),
            'added_by_user' => new UserResource($this->whenLoaded('addedBy')),
            'responsible_person' => new UserResource($this->whenLoaded('responsiblePerson')),
            'parent' => new UserResource($this->whenLoaded('addedBy')),
            'assigned_at' => $this->created_at,
            'api_first_question' => null,
            'has_service_duplicate' => static::$serviceDuplicateByLeadId[$this->id] ?? false,
            'score' => $this->score,
            'priority' => $this->priority,
            'intent' => $this->intent,
            'next_action' => $this->next_action,
        ];
    }
}
