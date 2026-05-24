<?php

namespace App\Http\Resources\Lead\Concerns;

use App\Models\User;

/**
 * Bitrix24 LAST_ACTIVITY_TIME / LAST_ACTIVITY_BY for lead card "Activity" tile.
 */
trait ResolvesLeadLastActivity
{
    /** @var array<int, User> */
    protected static array $kanbanActivityUsersById = [];

    public static function setKanbanActivityUsers(iterable $users): void
    {
        static::$kanbanActivityUsersById = [];
        foreach ($users as $user) {
            if ($user instanceof User) {
                static::$kanbanActivityUsersById[(int) $user->id] = $user;
            }
        }
    }

    public static function clearKanbanActivityUsers(): void
    {
        static::$kanbanActivityUsersById = [];
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
            } elseif ($b24Name) {
                $b24UserStub = [
                    'name' => $b24Name,
                    'bitrix24_id' => (int) $this->bitrix24_last_activity_by_id,
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
            return [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar ? asset('storage/'.$user->avatar) : null,
                'email' => $user->email,
                'is_external' => false,
                'bitrix24_id' => null,
            ];
        }
        if (is_array($user) && ! empty($user['name'])) {
            return [
                'id' => null,
                'name' => $user['name'],
                'avatar' => null,
                'email' => null,
                'is_external' => true,
                'bitrix24_id' => $user['bitrix24_id'] ?? null,
            ];
        }

        return null;
    }
}
