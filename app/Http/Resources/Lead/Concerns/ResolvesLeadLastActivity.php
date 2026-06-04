<?php

namespace App\Http\Resources\Lead\Concerns;

use App\Http\Resources\User\UserResource;
use App\Models\User;

/**
 * "Last activity" person + time for the lead card "Activity" tile.
 *
 * The activity person is resolved from the local database (lead_histories →
 * the user who performed the most recent action), NOT from the Bitrix24 mirror
 * columns. For the Kanban board the controller batch-resolves these once
 * (setKanbanDbActivity) so each card doesn't run its own history query.
 */
trait ResolvesLeadLastActivity
{
    /** @var array<int, array{user: User|null, at: mixed}> lead id => resolved activity */
    protected static array $kanbanDbActivityByLeadId = [];

    /**
     * Pre-resolved per-lead activity for the Kanban board (avoids per-card queries).
     *
     * @param  array<int, array{user: User|null, at: mixed}>  $map
     */
    public static function setKanbanDbActivity(array $map): void
    {
        static::$kanbanDbActivityByLeadId = $map;
    }

    public static function clearKanbanActivityUsers(): void
    {
        static::$kanbanDbActivityByLeadId = [];
    }

    /**
     * @return array{0: mixed, 1: User|null}
     */
    protected function resolveLastActivity(bool $includeHistoryFallback = true): array
    {
        // Kanban board: use the batch-resolved map so we don't query per card.
        if (array_key_exists((int) $this->id, static::$kanbanDbActivityByLeadId)) {
            $entry = static::$kanbanDbActivityByLeadId[(int) $this->id];

            return [
                $entry['at'] ?? $this->updated_at,
                $entry['user'] ?? null,
            ];
        }

        // Detail path: resolve straight from the database history — the most
        // recent action performed by a real local user.
        $latestWithUser = $this->histories()
            ->whereNotNull('user_id')
            ->whereHas('user')
            ->first();

        $lastActivityUser = $latestWithUser?->user;
        $lastActivityAt = $latestWithUser?->created_at;

        if (! $lastActivityAt && $includeHistoryFallback) {
            $lastActivityAt = $this->histories()->first()?->created_at;
        }

        return [
            $lastActivityAt ?? $this->updated_at,
            $lastActivityUser,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function formatActivityUser(?User $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        $user->loadMissing([
            'parent:id,name,avatar',
            'roles:id,name',
            'employeeProfile.companyBranch:id,name',
            'employeeProfile.designation:id,name',
        ]);

        $payload = (new UserResource($user))->resolve(request());

        return array_merge($payload, [
            'is_external' => false,
            'bitrix24_id' => null,
            'branch_name' => $payload['branch'] ?? $user->employeeProfile?->companyBranch?->name,
            'parent_name' => $payload['parent_name'] ?? $user->parent?->name,
        ]);
    }
}
