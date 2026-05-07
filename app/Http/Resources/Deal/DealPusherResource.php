<?php

namespace App\Http\Resources\Deal;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\UserResource;

class DealPusherResource extends JsonResource
{
    public function toArray($request)
    {
        $assignmentHistory = $this->histories()
            ->where('changes->action', 'assigned')
            ->orderBy('created_at', 'desc') 
            ->first();
        
        if ($assignmentHistory && $assignmentHistory->user) {
            $assignedBy = $assignmentHistory->user;
        } else {
            $assignedBy = $this->addedBy;
        }
        
        return [
            'id' => $this->id,
            'deal_number' => $this->deal_number,
            'deal_type' => $this->deal_type,
            'status' => $this->status,
            'source' => $this->source,
            'deal_name' => $this->deal_name,
            
            // Financial
            'deal_total_amount' => $this->deal_total_amount,
            'currency' => $this->currency,
            'deal_commission' => $this->deal_commission,
            'agent_share' => $this->agent_share,
            'company_share' => $this->company_share,
            
            // Relationships
            'lead' => $this->whenLoaded('lead', fn() => [
                'id' => $this->lead->id,
                'name' => $this->lead->lead_name,
            ]),
            
            'stage' => [
                'id' => $this->stage->id,
                'name' => $this->stage->name,
                'color' => $this->stage->color,
            ],
            
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
             
            'responsible_person_id' => $this->responsible_person_id,
            'responsible_person' => new UserResource($this->responsiblePerson),
            
            'buyer_name' => (function () {
                $buyer = $this->parties
                    ->where('party_type', 'buyer')
                    ->where('party_role', 'primary')
                    ->first();
                return $buyer ? trim($buyer->first_name . ' ' . $buyer->last_name) : null;
            })(),
                        'assigned_at' => $assignmentHistory ? $assignmentHistory->created_at : $this->created_at,

        ];
    }
}