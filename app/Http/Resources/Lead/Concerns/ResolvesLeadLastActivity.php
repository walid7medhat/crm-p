<?php

namespace App\Http\Resources\Lead\Concerns;

use App\Http\Resources\User\UserResource;
use App\Models\User;

/**
 * Bitrix24 LAST_ACTIVITY_TIME / LAST_ACTIVITY_BY for lead card "Activity" tile.
 */
trait ResolvesLeadLastActivity
{
    /** @var array<int, User> */
    protected static array $kanbanActivityUsersById = [];

    /** @var array<int, int> bitrix24 user id => local user id */
    protected static array $kanbanBitrixToLocalUserId = [];

    /** @var array<string, User> normalized display name => user */
    protected static array $kanbanActivityUsersByName = [];

    public static function setKanbanActivityUsers(iterable $users): void
    {
        static::$kanbanActivityUsersById = [];
        static::$kanbanActivityUsersByName = [];
        foreach ($users as $user) {
            if ($user instanceof User) {
                static::$kanbanActivityUsersById[(int) $user->id] = $user;
                $nameKey = mb_strtolower(trim((string) $user->name));
                if ($nameKey !== '') {
                    static::$kanbanActivityUsersByName[$nameKey] = $user;
                }
            }
        }
    }

    /**
     * @param  array<int, int>  $map
     */
    public static function setKanbanBitrixActivityUserMap(array $map): void
    {
        static::$kanbanBitrixToLocalUserId = [];
        foreach ($map as $b24Id => $localId) {
            if ($b24Id && $localId) {
                static::$kanbanBitrixToLocalUserId[(int) $b24Id] = (int) $localId;
            }
        }
    }

    public static function clearKanbanActivityUsers(): void
    {
        static::$kanbanActivityUsersById = [];
        static::$kanbanBitrixToLocalUserId = [];
        static::$kanbanActivityUsersByName = [];
    }

    /**
     * @return array{0: mixed, 1: User|array<string, mixed>|null}
     */
    protected function resolveLastActivity(bool $includeHistoryFallback = true): array
    {
        $lastActivityAt = $this->bitrix24_last_activity_at;
        $lastActivityUser = null;
        $b24UserStub = null;

        if ($this->bitrix24_last_activity_by_id) {
            $data = is_string($this->bitrix24_data)
                ? json_decode($this->bitrix24_data, true)
                : $this->bitrix24_data;
            $localId = data_get($data, '_users.last_activity.local_user_id');
            $b24Name = data_get($data, '_users.last_activity.name');

            if ($localId) {
                $localId = (int) $localId;
                $lastActivityUser = static::$kanbanActivityUsersById[$localId]
                    ?? User::find($localId);
            } elseif ($this->bitrix24_last_activity_by_id) {
                $b24Id = (int) $this->bitrix24_last_activity_by_id;
                $mappedLocalId = static::$kanbanBitrixToLocalUserId[$b24Id] ?? null;
                if ($mappedLocalId) {
                    $lastActivityUser = static::$kanbanActivityUsersById[$mappedLocalId]
                        ?? User::find($mappedLocalId);
                }
            }

            if (! $lastActivityUser && is_string($b24Name) && trim($b24Name) !== '') {
                $nameKey = mb_strtolower(trim($b24Name));
                $lastActivityUser = static::$kanbanActivityUsersByName[$nameKey] ?? null;
            }

            if (! $lastActivityUser) {
                $b24UserStub = [
                    'name' => $b24Name ?: ('Bitrix24 #'.$this->bitrix24_last_activity_by_id),
                    'bitrix24_id' => (int) $this->bitrix24_last_activity_by_id,
                    'photo_url' => data_get($data, '_users.last_activity.photo_url'),
                ];
            }
        }

        if ($includeHistoryFallback && (! $lastActivityUser || ! $lastActivityAt)) {
            $latest = $this->histories()
                ->orderBy('created_at', 'desc')
                ->first();
            if ($latest) {
                $lastActivityAt = $lastActivityAt ?? $latest->created_at;
                if (! $lastActivityUser && $latest->user_id && $latest->user) {
                    $lastActivityUser = $latest->user;
                }
            }
        }

        return [
            $lastActivityAt ?? $this->updated_at,
            $lastActivityUser ?? $b24UserStub,
        ];
    }

    /**
     * @param  User|array<string, mixed>|null  $user
     * @return array<string, mixed>|null
     */
    protected function formatActivityUser($user): ?array
    {
        if ($user instanceof User) {
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
        if (is_array($user) && ! empty($user['name'])) {
            $photo = $user['photo_url'] ?? null;

            return [
                'id' => null,
                'name' => $user['name'],
                'avatar' => is_string($photo) && $photo !== '' ? $photo : null,
                'email' => null,
                'is_external' => true,
                'bitrix24_id' => $user['bitrix24_id'] ?? null,
            ];
        }

        return null;
    }
}
