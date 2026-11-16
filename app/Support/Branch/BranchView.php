<?php

namespace App\Support\Branch;

use App\Models\User;
use App\Support\Brand\BrandContext;
use Illuminate\Support\Facades\Cache;

/**
 * OIA "Abu Dhabi / Dubai" view switch (dashboard top tabs).
 *
 * Only super_admin and user #30, only on the OIA host. When a branch is picked the
 * frontend sends `X-Branch-View: abu_dhabi|dubai` and every READ request (GET) sees
 * only that branch: leads, deals, listings and dashboard numbers.
 *
 * Writes (POST/PUT/DELETE) are never filtered, so duplicate checks, assignment and
 * saves still see the whole company.
 *
 * A branch = the user directly under super admin (Abu Dhabi #25, Dubai #59) and
 * everyone below them.
 */
class BranchView
{
    public const HEADER = 'X-Branch-View';

    /** key => [match name, legacy user id, label] */
    public const BRANCHES = [
        'abu_dhabi' => ['name' => 'abu dhabi', 'legacy_id' => 25, 'label' => 'Abu Dhabi'],
        'dubai' => ['name' => 'dubai', 'legacy_id' => 59, 'label' => 'Dubai'],
    ];

    /** Extra users (besides super_admin) allowed to switch branch. */
    public const EXTRA_USER_IDS = [30];

    private static ?string $current = null;

    /** @var array<string, list<int>> */
    private static array $memberMemo = [];

    public static function set(?string $key): void
    {
        self::$current = ($key !== null && isset(self::BRANCHES[$key])) ? $key : null;
    }

    public static function reset(): void
    {
        self::$current = null;
        self::$memberMemo = [];
    }

    public static function current(): ?string
    {
        return self::$current;
    }

    public static function active(): bool
    {
        return self::$current !== null;
    }

    public static function normalizeKey(?string $raw): ?string
    {
        $key = strtolower(trim((string) $raw));
        $key = str_replace([' ', '-'], '_', $key);

        return isset(self::BRANCHES[$key]) ? $key : null;
    }

    public static function canUse($user): bool
    {
        if (! $user) {
            return false;
        }
        if (app(BrandContext::class)->key() !== 'oia') {
            return false;
        }

        if (in_array((int) $user->id, self::EXTRA_USER_IDS, true)) {
            return true;
        }

        return method_exists($user, 'hasRole') && $user->hasRole('super_admin');
    }

    /** Branch root user (the node right under super admin). */
    public static function rootUser(string $key): ?User
    {
        $def = self::BRANCHES[$key] ?? null;
        if (! $def) {
            return null;
        }

        $byName = User::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$def['name']])
            ->whereHas('parent', fn ($q) => $q->whereNull('parent_id'))
            ->orderBy('id')
            ->first();

        return $byName ?: User::query()->find($def['legacy_id']);
    }

    /** @return list<int> branch root + every user below it */
    public static function memberIds(?string $key = null): array
    {
        $key ??= self::$current;
        if ($key === null) {
            return [];
        }

        if (isset(self::$memberMemo[$key])) {
            return self::$memberMemo[$key];
        }

        $ids = Cache::remember('branch_view:members:'.$key, now()->addMinutes(10), function () use ($key) {
            $root = self::rootUser($key);
            if (! $root) {
                return [];
            }

            return array_values(array_unique(array_map('intval', array_merge(
                [$root->id],
                $root->getAllSubordinatesIds()
            ))));
        });

        return self::$memberMemo[$key] = $ids;
    }

    public static function label(?string $key = null): ?string
    {
        $key ??= self::$current;

        return $key ? (self::BRANCHES[$key]['label'] ?? null) : null;
    }

    /** Cache-key suffix ('' when no branch is picked, so OIA keys stay unchanged). */
    public static function cacheSuffix(): string
    {
        return self::$current ? ':branch:'.self::$current : '';
    }

    /** Owner column per table. */
    public static function ownerColumn(string $table): ?string
    {
        return match ($table) {
            'leads', 'deals' => 'responsible_person_id',
            'listings' => 'agent_id',
            default => null,
        };
    }

    /**
     * Apply the branch filter to an Eloquent or query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Relations\Relation  $query
     * @param  string  $table  base table (leads|deals|listings)
     * @param  string|null  $qualifier  table name or alias used in the query (defaults to $table)
     */
    public static function constrain($query, string $table, ?string $qualifier = null)
    {
        if (! self::active()) {
            return $query;
        }

        $column = self::ownerColumn($table);
        if ($column === null) {
            return $query;
        }

        $qualifier ??= $table;
        $ids = self::memberIds();
        $branchName = self::BRANCHES[self::$current]['name'];

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($qualifier, $column, $ids, $table, $branchName) {
            $q->whereIn($qualifier.'.'.$column, $ids);

            // Unassigned leads still carry their branch name.
            if ($table === 'leads') {
                $q->orWhere(function ($q2) use ($qualifier, $branchName) {
                    $q2->whereNull($qualifier.'.responsible_person_id')
                        ->whereRaw('LOWER(TRIM('.$qualifier.'.lead_branch_source)) = ?', [$branchName]);
                });
            }
        });
    }

    /** Users list (dashboard agents etc.) → branch members only. */
    public static function constrainUsers($query, string $table = 'users')
    {
        if (! self::active()) {
            return $query;
        }

        $ids = self::memberIds();

        return $ids === []
            ? $query->whereRaw('1 = 0')
            : $query->whereIn($table.'.id', $ids);
    }
}
