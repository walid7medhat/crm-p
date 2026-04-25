<?php
// app/Http/Resources/DealDocumentResource.php

namespace App\Http\Resources\Deal;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\UserResource;

class DealDocumentResource extends JsonResource
{
    public function toArray($request)
    {
         $category = match ($this->document_type) {
        'national_id' => 'emirates_id',
        'visa' => 'residence_visa',
        default => $this->document_type,
    };
        return [
            'id' => $this->id,
            'document_category' => $this->document_category,
            'document_label' => $category,
            'document_type' => $this->document_type,
            'document_type_name' => $this->document_type_name,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->file_size_formatted,
            'file_url' => $this->file_url,
            'mime_type' => $this->mime_type,
            'uploaded_by' => new UserResource($this->whenLoaded('uploadedBy')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}