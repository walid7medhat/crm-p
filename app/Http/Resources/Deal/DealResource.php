<?php
// app/Http/Resources/DealResource.php

namespace App\Http\Resources\Deal;

use Illuminate\Http\Resources\Json\JsonResource;

class DealResource extends JsonResource
{
    public function toArray($request)
    {
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
            
            // Property
            'unit_no' => $this->unit_no,
            'bedrooms' => $this->bedrooms,
            'unit_size' => $this->unit_size,
            'property_link' => $this->property_link,
            'property_reference' => $this->property_reference,
            
            // Relationships
            'lead' => $this->whenLoaded('lead', fn() => [
                'id' => $this->lead->id,
                'name' => $this->lead->lead_name,
                'email' => $this->lead->email,
                'phone' => $this->lead->phone,
            ]),
            
            'stage' => $this->whenLoaded('stage', fn() => [
                'id' => $this->stage->id,
                'name' => $this->stage->name,
                'color' => $this->stage->color,
            ]),
            
            'property_type' => $this->whenLoaded('propertyType', fn() => [
                'id' => $this->propertyType->id,
                'name' => $this->propertyType->name,
            ]),
            
            'project' => $this->whenLoaded('project', fn() => [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ]),
            
            'area' => $this->whenLoaded('area', fn() => [
                'id' => $this->area->id,
                'name' => $this->area->name,
            ]),
            
            'developer' => $this->whenLoaded('developer', fn() => [
                'id' => $this->developer->id,
                'name' => $this->developer->name,
            ]),
            
            'responsible_person' => $this->whenLoaded('responsiblePerson', fn() => [
                'id' => $this->responsiblePerson->id,
                'name' => $this->responsiblePerson->name,
                'avatar' => $this->responsiblePerson->avatar,
            ]),
           'buyer_name' => (function () {
                    $buyer = $this->parties
                        ->where('party_type', 'buyer')
                        ->where('party_role', 'primary')
                        ->first();
                
                    return $buyer ? trim($buyer->first_name . ' ' . $buyer->last_name) : null;
                })(),
            'parties' => DealPartyResource::collection($this->whenLoaded('parties')),
            'documents' => DealDocumentResource::collection($this->whenLoaded('documents')),
            
            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'converted_at' => $this->lead?->converted_at?->format('Y-m-d H:i:s'),
        ];
    }
}