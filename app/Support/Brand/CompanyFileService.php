<?php

namespace App\Support\Brand;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Central store / authorize / stream / delete for company files.
 */
class CompanyFileService
{
    public const DISK_PUBLIC = 'public';

    public const DISK_COMPANY = 'company';

    public function __construct(private CompanyFileAuthorizer $authorizer)
    {
    }

    /**
     * Store an upload under a logical directory (e.g. deals/1/docs).
     * Sensitive dirs → private company disk; allowlisted → public disk.
     *
     * @return array{disk: string, path: string, url: string|null}
     */
    public function storeInDirectory(UploadedFile $file, string $directory, ?string $resource = null, ?int $resourceId = null): array
    {
        $directory = CompanyFilePolicy::normalize($directory);
        $disk = CompanyFilePolicy::diskForNewUpload($directory);
        $dir = BrandIsolation::ensureCompanyStoragePath($directory);

        $path = $file->store($dir, $disk);

        return [
            'disk' => $disk,
            'path' => $path,
            'url' => $this->urlFor($disk, $path, $resource, $resourceId),
        ];
    }

    /**
     * Build a URL. Sensitive files require an explicit owning resource binding
     * (embedded in the signed URL). Public allowlisted files stay on /storage.
     */
    public function urlFor(string $disk, ?string $path, ?string $resource = null, ?int $resourceId = null): ?string
    {
        if (! $path) {
            return null;
        }

        $path = CompanyFilePolicy::normalize($path);

        if ($disk === self::DISK_PUBLIC && CompanyFilePolicy::isPublicAllowlisted($path)) {
            return asset('storage/'.$path);
        }

        if (CompanyFilePolicy::isSensitive($path) || $disk === self::DISK_COMPANY) {
            if ($resource === null || $resourceId === null) {
                $binding = $this->authorizer->resolveBindingFromPath($path);
                if ($binding) {
                    $resource = $binding['resource'];
                    $resourceId = $binding['resource_id'];
                }
            }

            if ($resource === null || $resourceId === null) {
                // Fail closed: never issue a private URL without an owning record.
                return null;
            }

            return URL::temporarySignedRoute(
                'company-files.serve',
                now()->addMinutes(30),
                [
                    'disk' => $disk,
                    'path' => $path,
                    'resource' => $resource,
                    'resource_id' => $resourceId,
                ]
            );
        }

        return asset('storage/'.$path);
    }

    /**
     * Resolve a URL for a DB-stored relative path (legacy public or private company).
     */
    public function urlForLegacyPath(?string $path, ?string $resource = null, ?int $resourceId = null): ?string
    {
        if (! $path) {
            return null;
        }

        $path = CompanyFilePolicy::normalize($path);

        if (Storage::disk(self::DISK_COMPANY)->exists($path)) {
            return $this->urlFor(self::DISK_COMPANY, $path, $resource, $resourceId);
        }

        if (CompanyFilePolicy::isSensitive($path)) {
            if (BrandIsolation::trustedKey() === 'luxvant') {
                $disk = Storage::disk(self::DISK_PUBLIC)->exists($path)
                    ? self::DISK_PUBLIC
                    : self::DISK_COMPANY;

                return $this->urlFor($disk, $path, $resource, $resourceId);
            }

            // OIA legacy: keep publicly addressable URLs for files still on the public disk.
            if (Storage::disk(self::DISK_PUBLIC)->exists($path)) {
                return asset('storage/'.$path);
            }

            return $this->urlFor(self::DISK_COMPANY, $path, $resource, $resourceId);
        }

        return asset('storage/'.$path);
    }

    public function assertCanAccess(string $disk, string $path): void
    {
        $path = CompanyFilePolicy::normalize($path);
        if ($path === '' || str_contains($path, "\0")) {
            abort(403, 'Invalid file path.');
        }

        if (! in_array($disk, [self::DISK_PUBLIC, self::DISK_COMPANY], true)) {
            abort(403, 'Invalid disk.');
        }

        // Cross-brand paths → 404 (no existence leak).
        if (! BrandIsolation::pathAllowedForCurrentBrand($path)) {
            abort(404);
        }

        $root = Storage::disk($disk)->path('');
        $realRoot = realpath($root) ?: $root;
        $full = Storage::disk($disk)->path($path);

        if (! Storage::disk($disk)->exists($path)) {
            abort(404, 'File not found.');
        }

        $realFile = realpath($full);
        $normalizedRoot = rtrim(str_replace('\\', '/', $realRoot), '/').'/';

        if ($realFile !== false) {
            $normalizedFile = str_replace('\\', '/', $realFile);
            if (! str_starts_with($normalizedFile, $normalizedRoot)) {
                abort(403, 'Path escape denied.');
            }
        } else {
            $normalizedFull = str_replace('\\', '/', $full);
            if (! str_starts_with($normalizedFull, $normalizedRoot)) {
                abort(403, 'Path escape denied.');
            }
        }
    }

    /**
     * Stream a private file after signature (middleware) + auth + record ACL.
     */
    public function streamAuthorized(string $disk, string $path, string $resource, int $resourceId): StreamedResponse
    {
        $path = CompanyFilePolicy::normalize($path);
        $this->assertCanAccess($disk, $path);
        $this->authorizer->authorizeOrAbort($resource, $resourceId, $path, Auth::user());

        return Storage::disk($disk)->response($path);
    }

    /**
     * @deprecated Prefer streamAuthorized — path-only streaming is not permitted for sensitive files.
     */
    public function stream(string $disk, string $path): StreamedResponse
    {
        $path = CompanyFilePolicy::normalize($path);
        if (CompanyFilePolicy::isSensitive($path) || $disk === self::DISK_COMPANY) {
            abort(403, 'Resource binding required.');
        }

        $this->assertCanAccess($disk, $path);

        return Storage::disk($disk)->response($path);
    }

    public function deleteAuthorized(?string $path, ?string $expectedParentPath = null): bool
    {
        if (! $path) {
            return false;
        }

        $path = CompanyFilePolicy::normalize($path);

        if ($expectedParentPath !== null) {
            $expected = CompanyFilePolicy::normalize($expectedParentPath);
            if ($expected !== '' && $path !== $expected) {
                abort(403, 'File does not belong to this record.');
            }
        }

        BrandIsolation::assertPathAllowedForCurrentBrand($path);

        $deleted = false;
        foreach ([self::DISK_COMPANY, self::DISK_PUBLIC] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                $this->assertCanAccess($disk, $path);
                Storage::disk($disk)->delete($path);
                $deleted = true;
            }
        }

        return $deleted;
    }
}
