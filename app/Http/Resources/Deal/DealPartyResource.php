<?php
// app/Http/Resources/DealPartyResource.php

namespace App\Http\Resources\Deal;

use Illuminate\Http\Resources\Json\JsonResource;
class DealPartyResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'party_type' => $this->party_type,
            'party_type_name' => $this->getPartyTypeName(),
            'party_role' => $this->party_role,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'phone' => $this->phone,
            'email' => $this->email,
            'nationality' => $this->nationality,
            'residency_status' => $this->residency_status,
            'city' => $this->city,
            'country' => $this->country,
            'language' => $this->language,
            'amount' => $this->amount,
            'amount_formatted' => $this->amount ? number_format($this->amount, 2) . ' AED' : null,
            'documents' => DealDocumentResource::collection($this->whenLoaded('documents')),
        ];
    }

    private function getPartyTypeName(): string
    {
        return match($this->party_type) {
            'buyer' => 'Buyer',
            'seller' => 'Seller',
            'tenant' => 'Tenant',
            'landlord' => 'Landlord',
            'client' => 'Client',
            default => $this->party_type
        };
    }
}