<?php

namespace App\Http\Resources\Deal;

use Illuminate\Http\Resources\Json\JsonResource;

class CommentAttachmentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'comment_id' => $this->comment_id,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'file_url' => asset('storage/' . $this->file_path),
            'file_type' => $this->file_type,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->formatFileSize($this->file_size),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    private function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 0) {
            return $bytes . ' bytes';
        } else {
            return '0 bytes';
        }
    }
}