<?php

namespace App\Http\Resources\Deal;

/**
 * Lightweight Deal payload for Kanban board cards (grouped-by-stage + get-more).
 *
 * Intentionally omits parties/documents/properties/listing graphs that belong on
 * GET /deals/{id} (ViewDealModal / stage-gate detail fetch). Shape aligns with
 * DealPusherResource so realtime inserts remain compatible with Deals.vue cards.
 */
class DealKanbanCardResource extends DealResource
{
    public function toArray($request)
    {
        $assignmentHistory = $this->resolveAssignmentHistory();

        if ($assignmentHistory && $assignmentHistory->user) {
            $assignedBy = $assignmentHistory->user;
        } else {
            $assignedBy = $this->addedBy;
        }

        $buyer = null;
        if ($this->relationLoaded('parties')) {
            $buyerParty = $this->parties
                ->where('party_type', 'buyer')
                ->where('party_role', 'primary')
                ->first();
            if ($buyerParty) {
                $buyer = trim($buyerParty->first_name.' '.$buyerParty->last_name) ?: null;
            }
        }

        return [
            'id' => $this->id,
            'deal_number' => $this->deal_number,
            'deal_type' => $this->deal_type,
            'status' => $this->status,
            'source' => $this->source,
            'deal_name' => $this->deal_name,
            'listing_id' => $this->listing_id,
            'stage_id' => $this->stage_id,

            'deal_total_amount' => $this->deal_total_amount,
            'currency' => $this->currency,

            'lead_id' => $this->lead_id,
            'lead' => $this->whenLoaded('lead', fn () => [
                'id' => $this->lead->id,
                'lead_name' => $this->lead->lead_name,
                'name' => $this->lead->lead_name,
            ]),

            'stage' => $this->whenLoaded('stage', fn () => [
                'id' => $this->stage->id,
                'name' => $this->stage->name,
                'color' => $this->stage->color,
                'order' => $this->stage->order,
            ]),

            'responsible_person_id' => $this->responsible_person_id,
            'responsible_person' => $this->formatLeadUser($this->responsiblePerson),
            'parent' => $this->formatLeadUser($assignedBy),

            'buyer_name' => $buyer,

            'assigned_at' => $assignmentHistory ? $assignmentHistory->created_at : $this->created_at,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'converted_at' => $this->lead?->converted_at?->format('Y-m-d H:i:s'),
        ];
    }
}
