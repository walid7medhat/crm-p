<?php

namespace App\Support\Security;

use App\Models\GalleryImage;
use App\Models\Listing;
use App\Models\ProjectImage;
use App\Support\Brand\BrandIsolation;

/**
 * Authorizes /image/watermark path access.
 *
 * Public (unauthenticated) delivery is limited to brand-allowed listing/project
 * marketing images. Private CRM namespaces (deals, employees, documents, etc.)
 * are never served through this endpoint.
 */
class WatermarkMediaGate
{
    /** Path prefixes that may contain public/marketing listing imagery. */
    private const PUBLIC_PREFIXES = [
        'listings/',
        'luxvant/listings/',
        'projects/',
        'luxvant/projects/',
        'floorplans/',
        'luxvant/floorplans/',
        'watermark/',
    ];

    /** Always denied — private CRM documents and uploads. */
    private const PRIVATE_PREFIXES = [
        'deals/',
        'luxvant/deals/',
        'employees/',
        'luxvant/employees/',
        'document_requests/',
        'luxvant/document_requests/',
        'attendance/',
        'luxvant/attendance/',
        'avatars/',
        'luxvant/avatars/',
        'users/',
        'luxvant/users/',
        'marketing/',
        'luxvant/marketing/',
        'backgrounds/',
        'system-campaigns/',
        'listings/documents/',
        'luxvant/listings/documents/',
        'listings/additional_documents/',
        'luxvant/listings/additional_documents/',
    ];

    /**
     * Normalize a client path and reject traversal / absolute escapes.
     */
    public static function normalize(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, "\0") || str_contains($path, '..')) {
            return null;
        }

        foreach (['storage/', 'public/storage/', 'app/public/'] as $strip) {
            if (str_starts_with($path, $strip)) {
                $path = ltrim(substr($path, strlen($strip)), '/');
            }
        }

        return $path !== '' ? $path : null;
    }

    /**
     * Absolute file under storage/app/public, or null if outside the public disk root.
     */
    public static function absolutePublicPath(string $relative): ?string
    {
        $root = realpath(storage_path('app/public'));
        if ($root === false) {
            return null;
        }

        $candidate = storage_path('app/public/'.$relative);
        $full = realpath($candidate);

        // File may not exist yet — still ensure the resolved directory stays in root.
        if ($full === false) {
            $parent = realpath(dirname($candidate));
            if ($parent === false || ! str_starts_with($parent, $root)) {
                return null;
            }

            return $candidate;
        }

        if (! str_starts_with($full, $root.DIRECTORY_SEPARATOR) && $full !== $root) {
            return null;
        }

        return $full;
    }

    public static function isPrivateCrmDocument(string $relative): bool
    {
        $path = strtolower($relative);
        foreach (self::PRIVATE_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function isPublicListingMediaNamespace(string $relative): bool
    {
        if (self::isPrivateCrmDocument($relative)) {
            return false;
        }

        $path = strtolower($relative);
        foreach (self::PUBLIC_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the request may receive this media path.
     * Uses 404 (not 403) to avoid path oracles.
     *
     * Listing/project image namespaces stay reachable without JWT because CRM
     * and website clients load /image/watermark via <img src> (no Authorization
     * header). Private CRM document namespaces are never served here.
     */
    public static function authorizeOrAbort(string $relative): void
    {
        if (! BrandIsolation::pathAllowedForCurrentBrand($relative)) {
            abort(404);
        }

        if (self::isPrivateCrmDocument($relative)) {
            abort(404);
        }

        if (! self::isPublicListingMediaNamespace($relative)) {
            abort(404);
        }
    }

    /**
     * Path is tied to a published+approved listing, or a project gallery image.
     */
    public static function isPubliclyMarketablePath(string $relative): bool
    {
        $listingMatch = Listing::query()
            ->where('status', 'published')
            ->where('approved', true)
            ->where(function ($q) use ($relative) {
                $q->where('hero_image_path', $relative)
                    ->orWhereHas('galleryImages', fn ($g) => $g->where('image_path', $relative))
                    ->orWhereHas('floorPlans', fn ($f) => $f->where('image_path', $relative));
            })
            ->exists();

        if ($listingMatch) {
            return true;
        }

        return ProjectImage::query()->where('image_path', $relative)->exists()
            || GalleryImage::query()
                ->where('image_path', $relative)
                ->where('imageable_type', 'App\\Models\\Project')
                ->exists();
    }
}
