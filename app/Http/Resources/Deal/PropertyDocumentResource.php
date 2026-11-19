<?php
// app/Http/Resources/Deal/PropertyDocumentResource.php

namespace App\Http\Resources\Deal;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PropertyDocumentResource extends JsonResource
{
    /**
     * $propertyId: the owning DealProperty. Required for files on the private disk — the
     * signed URL is bound to its owner; without it urlForLegacyPath() fails closed (null)
     * and the UI fell back to the bare path, which opened the SPA home page.
     */
    public function __construct($resource, private string $documentKind = 'payment_proof', private ?int $propertyId = null)
    {
        parent::__construct($resource);
    }

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
        
        $kind = $this->documentKind;
        $propertyId = $this->propertyId;
        $files = app(\App\Support\Brand\CompanyFileService::class);

        return array_map(function ($doc) use ($kind, $propertyId, $files) {
            $path = $doc['path'] ?? null;

            return [
                'id' => $doc['id'] ?? null,
                'original_name' => $doc['original_name'] ?? 'Document',
                'file_name' => $doc['original_name'] ?? 'Document',
                'path' => $path ?: null,
                'url' => $path
                    ? ($propertyId
                        ? $files->urlForLegacyPath($path, \App\Support\Brand\CompanyFileAuthorizer::TYPE_DEAL_PROPERTY, $propertyId)
                        : $files->urlForLegacyPath($path))
                    : null,
                'file_url' => $doc['path'] ?? null,
                'mime_type' => $doc['mime_type'] ?? 'application/octet-stream',
                'size' => $doc['size'] ?? 0,
                'file_size' => $doc['size'] ?? 0,
                'document_type' => $doc['document_type'] ?? $kind,
                'document_category' => 'property',
                'created_at' => now()->toISOString(),
            ];
        }, $documents);
    }
}