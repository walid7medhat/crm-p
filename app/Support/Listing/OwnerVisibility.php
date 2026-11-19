<?php

namespace App\Support\Listing;

use App\Models\Owner;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Who may see listing owners and unit numbers.
 *
 *  - super_admin and user #30 → every owner / unit number.
 *  - permission `owners-view-branch` → every owner / unit number of their own branch
 *    (Abu Dhabi, Dubai, …): listings whose agent is in the branch, owners added by
 *    someone in the branch or linked to one of those listings.
 *  - everyone else → only their own (agent / added by them / approved access request).
 */
class OwnerVisibility
{
    public const PERMISSION = 'owners-view-branch';

    /** Besides super_admin — legacy full access. */
    public const FULL_ACCESS_USER_IDS = [30];

    /** @var array<int, list<int>> */
    private static array $memberMemo = [];

    public static function seesAll(?User $user): bool
    {
        return $user && (
            in_array((int) $user->id, self::FULL_ACCESS_USER_IDS, true)
            || $user->hasRole('super_admin')
        );
    }

    public static function hasBranchScope(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        try {
            // checkPermissionTo() returns false (no exception) if the permission row is missing.
            return $user->checkPermissionTo(self::PERMISSION);
        } catch (\Throwable) {
            return false;
        }
    }

    /** User ids of the viewer's branch (cached 10 min). */
    public static function branchMemberIds(User $user): array
    {
        $id = (int) $user->id;
        if (isset(self::$memberMemo[$id])) {
            return self::$memberMemo[$id];
        }

        $ids = Cache::remember("owner_visibility:branch_members:{$id}", 600, function () use ($user) {
            return array_values(array_unique(array_map('intval', $user->getBranchUserIds())));
        });

        return self::$memberMemo[$id] = $ids;
    }

    /** Can the viewer see owner + unit number of a listing whose agent is $agentId? */
    public static function canSeeListingOf(?User $user, $agentId): bool
    {
        if (! $user) {
            return false;
        }
        if (self::seesAll($user)) {
            return true;
        }
        if ($agentId && (int) $agentId === (int) $user->id) {
            return true;
        }

        return $agentId
            && self::hasBranchScope($user)
            && in_array((int) $agentId, self::branchMemberIds($user), true);
    }

    /**
     * Listing agent ids whose unit number / owner the viewer may see (null = all).
     * Approved access requests are per listing and are not included here.
     */
    public static function visibleAgentIds(?User $user): ?array
    {
        if (! $user) {
            return [];
        }
        if (self::seesAll($user)) {
            return null;
        }

        return self::hasBranchScope($user) ? self::branchMemberIds($user) : [(int) $user->id];
    }

    /**
     * Add an OR search condition on a sensitive field (unit number, owner name) that only
     * matches listings the viewer may see — so searching can't reveal another branch's units.
     *
     * @param  callable(\Illuminate\Database\Eloquent\Builder):void  $match
     */
    public static function orWhereSensitive($query, ?User $user, callable $match, string $table = 'listings')
    {
        $agents = self::visibleAgentIds($user);
        if ($agents === null) {
            return $query->orWhere($match);
        }

        return $query->orWhere(function ($q) use ($match, $agents, $table) {
            $q->where($match)->whereIn("{$table}.agent_id", $agents ?: [0]);
        });
    }

    /** Limit an Owner query to what the viewer may see. */
    public static function constrainOwners(Builder $query, User $user): Builder
    {
        if (self::seesAll($user)) {
            return $query;
        }

        if (! self::hasBranchScope($user)) {
            return $query->where('added_by', $user->id);
        }

        $members = self::branchMemberIds($user);
        $table = $query->getModel()->getTable();

        return $query->where(function ($q) use ($members, $table) {
            $q->whereIn("{$table}.added_by", $members)
                ->orWhereIn("{$table}.id", DB::table('listings')
                    ->select('owner_id')
                    ->whereNotNull('owner_id')
                    ->whereIn('agent_id', $members));
        });
    }

    public static function canSeeOwner(?User $user, Owner $owner): bool
    {
        if (! $user) {
            return false;
        }
        if ((int) $owner->added_by === (int) $user->id || self::seesAll($user)) {
            return true;
        }
        if (! self::hasBranchScope($user)) {
            return false;
        }

        return self::constrainOwners(Owner::query()->whereKey($owner->getKey()), $user)->exists();
    }

    /** Part of cache keys so a scope change never serves another scope's cached list. */
    public static function scopeKey(User $user): string
    {
        if (self::seesAll($user)) {
            return 'all';
        }
        if (self::hasBranchScope($user)) {
            return 'branch' . (optional($user->getBranchNode())->id ?? $user->id);
        }

        return 'own';
    }

    public static function resetMemo(): void
    {
        self::$memberMemo = [];
    }
}
