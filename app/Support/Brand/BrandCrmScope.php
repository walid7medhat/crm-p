<?php

namespace App\Support\Brand;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;

/**
 * When the active host brand is Luxvant, CRM lists/visibility are limited to the
 * configured CRM branch root (default: user named "Admin Luxvant") and its
 * parent_id descendants. OIA hosts are unaffected (no-op).
 */
class BrandCrmScope
{
    private static ?array $allowedUserIds = null;

    private static ?User $branchRoot = null;

    private static bool $rootResolved = false;

    /** @var list<int>|null host-independent memo of the configured branch tree */
    private static ?array $configuredIds = null;

    public static function active(): bool
    {
        if (! app(BrandContext::class)->isLuxvant()) {
            return false;
        }

        $scope = config('brands.brands.luxvant.crm_scope', []);

        return (bool) ($scope['enabled'] ?? true);
    }

    public static function reset(): void
    {
        self::$allowedUserIds = null;
        self::$branchRoot = null;
        self::$rootResolved = false;
        self::$configuredIds = null;
    }

    /**
     * Resolve Admin Luxvant (or configured) branch root from config — host-independent.
     */
    public static function resolveConfiguredBranchRoot(): ?User
    {
        $scope = config('brands.brands.luxvant.crm_scope', []);
        $id = (int) ($scope['branch_user_id'] ?? 0);
        $name = trim((string) ($scope['branch_user_name'] ?? 'Admin Luxvant'));

        if ($id > 0) {
            $root = User::query()->find($id);
            if ($root) {
                return $root;
            }
        }

        if ($name === '') {
            return null;
        }

        return User::query()
            ->where(function ($q) use ($name) {
                $q->where('name', $name)
                    ->orWhere('display_name', $name);
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * @return list<int>
     */
    public static function resolveConfiguredBranchUserIds(): array
    {
        if (self::$configuredIds !== null) {
            return self::$configuredIds;
        }

        return self::$configuredIds = self::loadConfiguredBranchUserIds();
    }

    /**
     * @return list<int>
     */
    private static function loadConfiguredBranchUserIds(): array
    {
        $root = self::resolveConfiguredBranchRoot();
        if (! $root) {
            return [];
        }

        $cacheKey = 'luxvant_crm_scope_user_ids_'.$root->id;

        return Cache::remember($cacheKey, 60, function () use ($root) {
            return array_values(array_unique(array_map(
                'intval',
                $root->getAllSubordinatesIds()
            )));
        });
    }

    /**
     * Login allowlist helper: is this user under the configured Luxvant CRM branch?
     * Does not require BrandContext (uses config only).
     */
    public static function userInConfiguredCrmBranch(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return in_array($userId, self::resolveConfiguredBranchUserIds(), true);
    }

    public static function branchRoot(): ?User
    {
        if (! self::active()) {
            return null;
        }

        if (self::$rootResolved) {
            return self::$branchRoot;
        }

        self::$rootResolved = true;
        self::$branchRoot = self::resolveConfiguredBranchRoot();

        return self::$branchRoot;
    }

    /**
     * @return list<int>
     */
    public static function allowedUserIds(): array
    {
        if (! self::active()) {
            return [];
        }

        if (self::$allowedUserIds !== null) {
            return self::$allowedUserIds;
        }

        self::$allowedUserIds = self::resolveConfiguredBranchUserIds();

        return self::$allowedUserIds;
    }

    public static function userAllowed(?int $userId): bool
    {
        if (! self::active()) {
            return true;
        }

        if ($userId === null || $userId <= 0) {
            return false;
        }

        return in_array((int) $userId, self::allowedUserIds(), true);
    }

    public static function leadAllowed(Lead $lead): bool
    {
        if (! self::active()) {
            return true;
        }

        $ids = self::allowedUserIds();
        if ($ids === []) {
            return false;
        }

        $root = self::branchRoot();
        $branchName = $root?->name;

        if ($branchName && (string) $lead->lead_branch_source === $branchName) {
            return true;
        }

        if ($lead->responsible_person_id && in_array((int) $lead->responsible_person_id, $ids, true)) {
            return true;
        }

        if ($lead->added_by && in_array((int) $lead->added_by, $ids, true)) {
            return true;
        }

        return false;
    }

    /**
     * @param  Builder<User>|Relation  $query
     */
    public static function constrainUsers(Builder|Relation $query): void
    {
        if (! self::active()) {
            return;
        }

        $ids = self::allowedUserIds();
        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($query->getModel()->getTable().'.id', $ids);
    }

    /**
     * Accepts Eloquent Builder or relation builders (e.g. Stage::leads() HasMany
     * used by kanban infinite scroll). Type must allow Relation: PHP checks the
     * signature before the OIA no-op early-return.
     *
     * @param  Builder<Lead>|Relation  $query
     */
    public static function constrainLeads(Builder|Relation $query): void
    {
        if (! self::active()) {
            return;
        }

        $ids = self::allowedUserIds();
        $root = self::branchRoot();
        $branchName = $root?->name;

        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($q) use ($ids, $branchName) {
            $q->whereIn('responsible_person_id', $ids)
                ->orWhereIn('added_by', $ids);

            if ($branchName) {
                $q->orWhere('lead_branch_source', $branchName);
            }
        });
    }

    /**
     * @param  Builder<\App\Models\Deal>|Relation  $query
     */
    public static function constrainDeals(Builder|Relation $query): void
    {
        if (! self::active()) {
            return;
        }

        $ids = self::allowedUserIds();
        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('responsible_person_id', $ids);
    }
}
