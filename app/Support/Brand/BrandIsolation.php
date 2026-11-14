<?php

namespace App\Support\Brand;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

/**
 * Helpers for company isolation. Never trust client-supplied brand_key.
 */
class BrandIsolation
{
    /** @var array<string, bool> */
    private static array $columnCache = [];

    public static function trustedKey(): string
    {
        return app(BrandContext::class)->key();
    }

    /**
     * Explicit Super Admin cross-brand exception — OFF by default.
     * Never infer from role alone.
     */
    public static function superAdminCrossBrandEnabled(): bool
    {
        return (bool) config('brands.super_admin_cross_brand', false);
    }

    public static function columnReady(string $table): bool
    {
        if (array_key_exists($table, self::$columnCache)) {
            return self::$columnCache[$table];
        }

        try {
            self::$columnCache[$table] = Schema::hasColumn($table, 'brand_key');
        } catch (\Throwable) {
            self::$columnCache[$table] = false;
        }

        return self::$columnCache[$table];
    }

    public static function clearColumnCache(): void
    {
        self::$columnCache = [];
    }

    /**
     * Reject / ignore any client attempt to set brand_key.
     */
    public static function stripClientBrandKey(mixed $payload): mixed
    {
        if (is_array($payload)) {
            unset($payload['brand_key']);
        }

        return $payload;
    }

    /**
     * CRM leads/deals on Luxvant may still be NULL until classified. BrandCrmScope
     * restricts those rows to the Admin Luxvant branch — do not open listings/etc.
     */
    public static function luxvantAllowsUnclassifiedCrmRows(string $table): bool
    {
        // Strict since brand:backfill-legacy classifies every legacy row
        // (Admin Luxvant tree → luxvant, rest → oia). Allowing NULL here leaked
        // unclassified OIA leads/deals into the Luxvant dashboard and matching.
        // Escape hatch only for a not-yet-backfilled environment.
        if (! in_array($table, ['leads', 'deals'], true)) {
            return false;
        }

        if (! (bool) config('brands.luxvant_allow_unclassified_crm', false)) {
            return false;
        }

        return BrandCrmScope::active();
    }

    /**
     * Apply the same visibility rules used by BrandScope to a query builder.
     *
     * @param  Builder|QueryBuilder  $query
     */
    public static function constrain($query, string $table, ?string $column = null)
    {
        if (! self::columnReady($table)) {
            return $query;
        }

        if (self::superAdminCrossBrandEnabled()) {
            return $query;
        }

        $col = $column ?? ($table.'.brand_key');
        $key = self::trustedKey();

        if ($key === 'luxvant') {
            if (self::luxvantAllowsUnclassifiedCrmRows($table)) {
                return $query->where(function ($q) use ($col) {
                    $q->where($col, 'luxvant')->orWhereNull($col);
                });
            }

            return $query->where($col, 'luxvant');
        }

        return $query->where(function ($q) use ($col) {
            $q->where($col, 'oia')->orWhereNull($col);
        });
    }

    /**
     * Cache key segment — always include trusted brand.
     */
    public static function cacheSegment(): string
    {
        return 'brand:'.self::trustedKey();
    }

    /**
     * Relative storage prefix for NEW uploads only.
     * OIA keeps legacy flat paths (no forced oia/ prefix) so existing URLs work.
     */
    public static function storagePrefix(): string
    {
        $key = self::trustedKey();

        return $key === 'luxvant' ? 'luxvant' : '';
    }

    /**
     * Join path segments under the company storage namespace when Luxvant.
     */
    public static function storagePath(string ...$segments): string
    {
        $parts = array_values(array_filter(array_map(function ($s) {
            return trim(str_replace('\\', '/', (string) $s), '/');
        }, $segments), fn ($s) => $s !== ''));

        return self::ensureCompanyStoragePath(implode('/', $parts));
    }

    /**
     * Prefix a relative storage directory for Luxvant; leave OIA paths unchanged.
     */
    public static function ensureCompanyStoragePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        $prefix = self::storagePrefix();
        if ($prefix === '' || $path === '') {
            return $path;
        }

        // Projects / developers are a shared catalog — same path on both brands.
        if (BrandStorage::isShared($path)) {
            return $path;
        }

        if (str_starts_with($path, $prefix.'/')) {
            return $path;
        }

        return $prefix.'/'.$path;
    }

    /**
     * Absolute filesystem path to the active brand watermark (public disk).
     * OIA keeps the existing production watermark file.
     */
    public static function watermarkStorageRelativePath(): string
    {
        if (self::trustedKey() === 'luxvant') {
            return 'luxvant/Setting/watermark.png';
        }

        return 'Setting/1745128256Oia Watermark.png';
    }

    /**
     * Absolute path of the active brand's watermark image, or null if none exists.
     * Luxvant: uploaded luxvant/Setting/watermark.png if present, else the brand
     * logo from config('brands.brands.luxvant.logos.watermark'). Never the OIA mark.
     */
    public static function watermarkAbsolutePath(): ?string
    {
        $stored = storage_path('app/public/'.self::watermarkStorageRelativePath());
        if (is_file($stored)) {
            return $stored;
        }

        if (self::trustedKey() === 'luxvant') {
            $logo = (string) (app(BrandContext::class)->logos()['watermark'] ?? '');
            $candidate = $logo !== '' ? public_path(ltrim($logo, '/')) : null;

            return $candidate && is_file($candidate) ? $candidate : null;
        }

        return null;
    }

    /**
     * Same watermark as a public_path()-relative path (used by ImageHelper options).
     */
    public static function watermarkPublicRelativePath(): ?string
    {
        $abs = self::watermarkAbsolutePath();
        if ($abs === null) {
            return null;
        }

        $public = rtrim(str_replace('\\', '/', public_path()), '/').'/';
        $abs = str_replace('\\', '/', $abs);
        if (str_starts_with($abs, $public)) {
            return substr($abs, strlen($public));
        }

        // storage/app/public/* is served through the public/storage symlink.
        $storage = rtrim(str_replace('\\', '/', storage_path('app/public')), '/').'/';
        if (str_starts_with($abs, $storage)) {
            return 'storage/'.substr($abs, strlen($storage));
        }

        return null;
    }

    /**
     * Whether a stored relative path is allowed for the current brand.
     * Luxvant may only access luxvant/… (and never raw OIA legacy paths).
     * OIA may access non-luxvant paths (legacy) and optional oia/ paths.
     */
    public static function pathAllowedForCurrentBrand(?string $relativePath): bool
    {
        if ($relativePath === null || $relativePath === '') {
            return false;
        }

        $path = ltrim(str_replace('\\', '/', $relativePath), '/');
        foreach (['storage/', 'public/storage/', 'app/public/'] as $strip) {
            if (str_starts_with($path, $strip)) {
                $path = substr($path, strlen($strip));
            }
        }
        $path = ltrim($path, '/');
        $key = self::trustedKey();

        // Shared catalog (projects / developers) is readable by both companies.
        if (! str_starts_with($path, 'luxvant/') && BrandStorage::isShared($path)) {
            return true;
        }

        if ($key === 'luxvant') {
            return str_starts_with($path, 'luxvant/');
        }

        return ! str_starts_with($path, 'luxvant/');
    }

    /**
     * Abort 403 when a relative public-disk path is not allowed for the current brand.
     */
    public static function assertPathAllowedForCurrentBrand(?string $relativePath): void
    {
        if (! self::pathAllowedForCurrentBrand($relativePath)) {
            abort(403, 'Cross-company file access denied.');
        }
    }

    /**
     * Abort 404 (no existence leak) when the user is missing or not a member of the current brand.
     */
    public static function ensureUserBelongsOrNotFound(?\App\Models\User $user): \App\Models\User
    {
        if (! $user || ! self::userBelongsToCurrentBrand($user)) {
            abort(404, 'User not found');
        }

        return $user;
    }

    /**
     * Load a user by id only when they belong to the current brand (else null).
     */
    public static function findUserInCurrentBrand(int|string|null $id): ?\App\Models\User
    {
        if ($id === null || $id === '' || (int) $id <= 0) {
            return null;
        }

        $user = \App\Models\User::query()->find((int) $id);
        if (! $user || ! self::userBelongsToCurrentBrand($user)) {
            return null;
        }

        return $user;
    }

    /**
     * Safe public metadata for API responses — never leak another brand's PII.
     *
     * @return array{id: int, name: string|null, avatar: string|null, email: string|null}|null
     */
    public static function publicUserMetadata(?\App\Models\User $user): ?array
    {
        if (! $user || ! self::userBelongsToCurrentBrand($user)) {
            return null;
        }

        return [
            'id' => (int) $user->id,
            'name' => $user->display_name ?: $user->name,
            'avatar' => $user->avatar,
            'email' => $user->email,
        ];
    }

    /**
     * True when a user membership record belongs to the current brand.
     * NULL brand_key is treated as OIA-legacy only (visible on OIA host).
     */
    public static function userBelongsToCurrentBrand(?\App\Models\User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (! self::columnReady('users')) {
            if (self::trustedKey() === 'luxvant') {
                return BrandCrmScope::userAllowed((int) $user->id);
            }

            return true;
        }

        $key = self::trustedKey();
        $userBrand = $user->brand_key;

        if ($key === 'luxvant') {
            if ($userBrand === 'luxvant') {
                return true;
            }

            return BrandCrmScope::userAllowed((int) $user->id);
        }

        if (! ($userBrand === null || $userBrand === '' || $userBrand === 'oia')) {
            return false;
        }

        // Unclassified Admin Luxvant tree members are Luxvant, not OIA.
        return ! BrandCrmScope::userInConfiguredCrmBranch((int) $user->id);
    }

    /**
     * Limit a users query (Eloquent or query builder) to members of the current brand.
     * Luxvant: brand_key=luxvant or in the Admin Luxvant tree.
     * OIA: brand_key oia/NULL and not in the Admin Luxvant tree.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Relations\Relation  $query
     */
    public static function constrainUsers($query, string $table = 'users')
    {
        if (self::superAdminCrossBrandEnabled()) {
            return $query;
        }

        $tree = BrandCrmScope::resolveConfiguredBranchUserIds();
        $hasColumn = self::columnReady('users');

        if (self::trustedKey() === 'luxvant') {
            return $query->where(function ($q) use ($table, $tree, $hasColumn) {
                if ($hasColumn) {
                    $q->where($table.'.brand_key', 'luxvant');
                }
                if ($tree !== []) {
                    $q->orWhereIn($table.'.id', $tree);
                }
                if (! $hasColumn && $tree === []) {
                    $q->whereRaw('1 = 0');
                }
            });
        }

        if ($hasColumn) {
            $query->where(function ($q) use ($table) {
                $q->where($table.'.brand_key', 'oia')->orWhereNull($table.'.brand_key');
            });
        }
        if ($tree !== []) {
            $query->whereNotIn($table.'.id', $tree);
        }

        return $query;
    }

    /**
     * Brand key the user belongs to (for login / storage decisions).
     */
    public static function brandKeyOfUser(?\App\Models\User $user): string
    {
        if (! $user) {
            return 'oia';
        }

        $b = (string) ($user->brand_key ?? '');
        if ($b !== '') {
            return $b;
        }

        return BrandCrmScope::userInConfiguredCrmBranch((int) $user->id) ? 'luxvant' : 'oia';
    }

    /**
     * Store an uploaded file under the correct disk (public allowlist vs private company)
     * with Luxvant brand prefix when required. Returns the relative path for DB storage.
     */
    public static function storePublic(UploadedFile $file, string ...$segments): string
    {
        $directory = implode('/', array_map(
            fn ($s) => trim(str_replace('\\', '/', (string) $s), '/'),
            $segments
        ));

        return app(CompanyFileService::class)->storeInDirectory($file, $directory)['path'];
    }

    /**
     * Store into a directory string (e.g. "deals/12/eoi_documents").
     */
    public static function storeInDirectory(UploadedFile $file, string $directory): string
    {
        return app(CompanyFileService::class)->storeInDirectory($file, $directory)['path'];
    }

    /**
     * Delete a stored path only when allowed for the current brand (public or company disk).
     */
    public static function deletePublicPath(?string $relativePath): bool
    {
        return app(CompanyFileService::class)->deleteAuthorized($relativePath);
    }

    /**
     * Stamp brand_key on a raw attributes array for DB::table inserts (never from client).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function stampAttributes(array $attributes, string $table): array
    {
        if (self::columnReady($table)) {
            $attributes['brand_key'] = self::trustedKey();
        }

        return $attributes;
    }
}
