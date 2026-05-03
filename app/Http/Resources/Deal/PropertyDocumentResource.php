<?php
// app/Http/Resources/Deal/PropertyDocumentResource.php

namespace App\Http\Resources\Deal;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PropertyDocumentResource extends JsonResource
{
    public function toArray($request)
    {
        // لو جاتلك كـ string (JSON) decodeها
        $documents = $this->resource;
        
        if (is_string($documents)) {
            $documents = json_decode($documents, true);
        }
        
        if (!is_array($documents)) {
            return [];
        }
        
        return array_map(function($doc) {
            return [
                'id' => $doc['id'] ?? null,
                'original_name' => $doc['original_name'] ?? 'Document',
                'file_name' => $doc['original_name'] ?? 'Document',
                'path' => $doc['path'] ? $doc['path'] : null,
                'url' => $doc['path'] ? Storage::disk('public')->url($doc['path']) : null,
                'file_url' => $doc['path'] ?? null,
                'mime_type' => $doc['mime_type'] ?? 'application/octet-stream',
                'size' => $doc['size'] ?? 0,
                'file_size' => $doc['size'] ?? 0,
                'document_type' => 'payment_proof',
                'document_category' => 'property',
                'created_at' => now()->toISOString(),
            ];
        }, $documents);
    }
}