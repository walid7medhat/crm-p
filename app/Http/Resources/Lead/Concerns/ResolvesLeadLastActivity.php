<?php

namespace App\Http\Resources\Lead\Concerns;

use App\Http\Resources\User\UserResource;
use App\Models\User;

/**
 * "Last activity" person + time for the lead card "Activity" tile.
 *
 * The activity person is the Bitrix24 LAST_ACTIVITY_BY user, resolved to a
 * LOCAL database user via users.bitrix24_id. Those local users are provisioned
 * (created + photo downloaded) from Bitrix24 by the
 * `bitrix24:provision-activity-users` command, so the card always renders a
 * real saved DB user — we never call Bitrix24 here on the read path.
 *
 * For the Kanban board the controller batch-loads these users once
 * (setKanbanActivityUsersByBitrixId) to avoid a per-card query.
 */
trait ResolvesLeadLastActivity
{
    /** @var array<int, User|null> bitrix24 user id => local user (null = resolved, none found) */
    protected static array $kanbanActivityUsersByBitrixId = [];

    /**
     * @param  array<int, User|null>  $map
     */
    public static function setKanbanActivityUsersByBitrixId(array $map): void
    {
        static::$kanbanActivityUsersByBitrixId = $map;
    }

    public static function clearKanbanActivityUsers(): void
    {
        static::$kanbanActivityUsersByBitrixId = [];
    }

    /**
     * @return array{0: mixed, 1: User|null}
     */
    protected function resolveLastActivity(bool $includeHistoryFallback = true): array
    {
        $lastActivityAt = $this->bitrix24_last_activity_at;
        $lastActivityUser = $this->resolveBitrixActivityUser();

        // Non-Bitrix leads (or Bitrix users not yet provisioned) fall back to the
        // most recent local history row performed by a real user.
        if ($includeHistoryFallback && (! $lastActivityUser || ! $lastActivityAt)) {
            $latest = $this->histories()
                ->whereNotNull('user_id')
                ->whereHas('user')
                ->first();

            if ($latest) {
                $lastActivityAt = $lastActivityAt ?? $latest->created_at;
                $lastActivityUser = $lastActivityUser ?? $latest->user;
            }
        }

        return [
            $lastActivityAt ?? $this->updated_at,
            $lastActivityUser,
        ];
    }

    /** Local user provisioned from the lead's Bitrix24 LAST_ACTIVITY_BY id. */
    protected function resolveBitrixActivityUser(): ?User
    {
        $b24Id = $this->bitrix24_last_activity_by_id
            ? (int) $this->bitrix24_last_activity_by_id
            : null;

        if (! $b24Id) {
            return null;
        }

        // Kanban board: served from the batch map (key is present even when null,
        // so we never fall through to a per-card query).
        if (array_key_exists($b24Id, static::$kanbanActivityUsersByBitrixId)) {
            return static::$kanbanActivityUsersByBitrixId[$b24Id];
        }

        return User::where('bitrix24_id', $b24Id)->first();
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
            'bitrix24_id' => $user->bitrix24_id,
            'branch_name' => $payload['branch'] ?? $user->employeeProfile?->companyBranch?->name,
            'parent_name' => $payload['parent_name'] ?? $user->parent?->name,
        ]);
    }
}
