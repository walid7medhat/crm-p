<?php

namespace App\Http\Resources\Listing;

use Illuminate\Http\Resources\Json\JsonResource;

class OwnerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'avatar_path' => $this->avatar_path ? asset('storage/' . $this->avatar_path) : null,
            
            // Personal Information
            'salutation' => $this->salutation,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'whatsapp_number' => $this->whatsapp_number,
            'second_phone_number' => $this->second_phone_number,
            
            // Location Information - Simplified
            'nationality' => $this->nationality,
            'residency_status' => $this->residency_status,
            'is_resident' => $this->is_resident, // Computed attribute
            'location_id' => $this->location_id,
            
            // Document Paths
            'id_front_path' => $this->id_front_path ? asset('storage/' . $this->id_front_path) : null,
            'id_back_path' => $this->id_back_path ? asset('storage/' . $this->id_back_path) : null,
            'visa_copy_path' => $this->visa_copy_path ? asset('storage/' . $this->visa_copy_path) : null,
            'passport_copy_path' => $this->passport_copy_path ? asset('storage/' . $this->passport_copy_path) : null,
            'additional_documents' => $this->whenLoaded('additionalDocuments', function () {
                return $this->additionalDocuments->map(fn ($doc) => [
                    'id' => $doc->id,
                    'url' => asset('storage/' . $doc->path),
                    'name' => $doc->original_name ?? basename($doc->path),
                ]);
            }, []),
            // Additional Information
            'notes' => $this->notes,
            
            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Relationships
            'added_by_user' => $this->whenLoaded('addedBy'),
            'location' => $this->whenLoaded('location'),
        ];
    }
}