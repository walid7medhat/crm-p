<?php

namespace App\Http\Resources\Deal;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\UserResource;

class DealResource extends JsonResource
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
            
            // Relationships (lead_id always when set so kanban / modals can link without loading full lead)
            'lead_id' => $this->lead_id,
            'lead' => $this->whenLoaded('lead', fn() => [
                'id' => $this->lead->id,
                'lead_name' => $this->lead->lead_name,
                'name' => $this->lead->lead_name,
                'email' => $this->lead->email,
                'phone' => $this->lead->phone ?? $this->lead->work_phone,
                'work_phone' => $this->lead->work_phone,
            ]),
            
            'stage' => [
                'id' => $this->stage->id,
                'name' => $this->stage->name,
                'color' => $this->stage->color,
            ],
            
          
            'listing' => $this->listing ? [
                'id' => $this->listing->id,
                'name' => $this->listing->area?->area_title,
                'agent' => $this->listing->agent->name,
            ] : null,
            
            'area' => [
                'id' => $this->area?->id,
                'name' => $this->area?->name,
            ],
            
            // ========== MULTI PROPERTIES ==========
            'properties' => $this->whenLoaded('properties', function() {
                return $this->properties->map(function($property) {
                    return [
                        'id' => $property->id,
                        'sort_order' => $property->sort_order,
                        'unit_no' => $property->unit_no,
                        'property_type_id' => $property->property_type_id,
                        'property_type' => $property->propertyType?->name,
                        'bedrooms' => $property->bedrooms,
                        'unit_size' => $property->unit_size,
                        'area_id' => $property->area_id,
                        'area' => $property->area?->name,
                        'project_id' => $property->project_id,
                        'developer_id' => $property->developer_id,
                        'developer_name' => $property->developer_name,
                        'developer_phone' => $property->developer_phone,
                        'budget_from' => $property->budget_from,
                        'budget_to' => $property->budget_to,
                        'purchase_price' => $property->purchase_price,
                        'rental_price' => $property->rental_price,
                        'payment_proof' => $property->payment_proof,
                        'spa_document' => $property->spa_document,
                        'display_name' => $property->display_name,
                        'budget_range' => $property->budget_range,
                    ];
                });
            }),
            
            'developer_name' => $this->developer_name,
            'developer_phone' => $this->developer_phone,
            'developer' => [
                'id' => $this->developer?->id,
                'name' => $this->developer?->name,
            ],
            
            'responsible_person_id' => $this->responsible_person_id,
            'responsible_person' => new UserResource($this->responsiblePerson),
            
            'buyer_name' => (function () {
                $buyer = $this->parties
                    ->where('party_type', 'buyer')
                    ->where('party_role', 'primary')
                    ->first();
                return $buyer ? trim($buyer->first_name . ' ' . $buyer->last_name) : null;
            })(),
            
            'parties' => DealPartyResource::collection($this->whenLoaded('parties')),
            'documents' => DealDocumentResource::collection($this->whenLoaded('documents')),
            'parent' => new UserResource($assignedBy),
            'assigned_at' => $assignmentHistory ? $assignmentHistory->created_at : $this->created_at,
            'lost_reason' => $this->lost_reason,
            
            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'converted_at' => $this->lead?->converted_at?->format('Y-m-d H:i:s'),
        ];
    }
}