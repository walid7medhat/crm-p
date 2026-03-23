<?php

namespace App\Http\Resources\Deal;

use App\Models\Stage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\Resources\Json\JsonResource;

class DealHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'action'      => $this->changes['action'] ?? null,
            'changes'     => $this->changes,
            'description' => $this->buildDescription(),
            'user'        => $this->whenLoaded('user', function () {
                return [
                    'id'     => $this->user?->id,
                    'name'   => $this->user?->name,
                    'avatar' => $this->user?->avatar_url,
                ];
            }),
            'date'                  => $this->created_at->format('Y-m-d H:i'),
            'time_ago'              => $this->created_at->diffForHumans(),
            'deal_name'             => $this->deal?->deal_name,
            'response_person'       => $this->deal?->responsiblePerson?->name,
            'response_person_avatar'=> $this->deal?->responsiblePerson?->avatar,
            'source'                 => $this->deal?->source,
            'createdBy'              => $this->deal?->addedBy?->name,
        ];
    }

    /**
     * Build a human-readable description of what happened.
     */
    protected function buildDescription(): string
    {
        $changes = $this->changes ?? [];
        $action = $changes['action'] ?? null;

        switch ($action) {
            case 'stage_changed':
                $oldId = $changes['old_stage'] ?? null;
                $newId = $changes['new_stage'] ?? null;
                $oldName = $oldId ? (Stage::find($oldId)?->name ?? (string) $oldId) : '—';
                $newName = $newId ? (Stage::find($newId)?->name ?? (string) $newId) : '—';
                return "Stage changed from {$oldName} to {$newName}";

            case 'activity_created':
                $title = $changes['title'] ?? 'Activity';
                return "Activity created: " . Str::limit($title, 60);

            case 'activity_updated':
                $title = $changes['title'] ?? 'Activity';
                return "Activity updated: " . Str::limit($title, 60);

            case 'activity_deleted':
                $title = $changes['title'] ?? 'Activity';
                return "Activity deleted: " . Str::limit($title, 60);

            case 'comment_added':
                $preview = $changes['comment'] ?? 'Comment';
                return "Comment added: " . Str::limit($preview, 50);

            case 'comment_updated':
                $preview = $changes['comment'] ?? 'Comment';
                return "Comment updated: " . Str::limit($preview, 50);

            case 'comment_deleted':
                return 'Comment deleted';

            case 'updated':
                $nested = $changes['changes'] ?? [];
                if (!empty($nested)) {
                    $parts = [];
                    if (isset($nested['stage'])) {
                        $old = $nested['stage']['old'] ?? null;
                        $new = $nested['stage']['new'] ?? null;
                        $oldName = $old ? (Stage::find($old)?->name ?? $old) : '—';
                        $newName = $new ? (Stage::find($new)?->name ?? $new) : '—';
                        $parts[] = "Stage: {$oldName} → {$newName}";
                    }
                    if (!empty($parts)) {
                        return implode('; ', $parts);
                    }
                }
                return 'Deal details updated';

            case 'created':
                return 'Deal created';

            case 'view':
                return 'Deal viewed';

            default:
                if ($action) {
                    return str_replace('_', ' ', ucfirst($action));
                }
                return '—';
        }
    }
}
