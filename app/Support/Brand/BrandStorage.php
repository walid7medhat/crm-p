<?php

namespace App\Support\Brand;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Separate physical storage for Luxvant CRM files.
 *
 * New Luxvant uploads for leads, listings, deals, users, owners and
 * recruitment (applicants) go to their own
 * disks (luxvant_public / luxvant_company, root LUXVANT_STORAGE_ROOT) instead
 * of OIA's main storage. Stored DB paths keep the "luxvant/..." prefix, so
 * URLs stay /storage/luxvant/... and every brand path check keeps working;
 * files that are not found in main storage are streamed from the Luxvant disk
 * by the storage/luxvant fallback route.
 *
 * Old Luxvant files already on the main disks stay there (read fallback).
 * Projects / developers are a shared catalog and always use main storage.
 */
class BrandStorage
{
    public const DISK_PUBLIC = 'public';

    public const DISK_COMPANY = 'company';

    public const DISK_LUXVANT_PUBLIC = 'luxvant_public';

    public const DISK_LUXVANT_COMPANY = 'luxvant_company';

    /** Logical (brand-prefix-free) roots that use the separate Luxvant storage. */
    public const SEPARATE_PREFIXES = [
        'leads/',
        'comments/attachments',
        'listings/',
        'deals/',
        'users/',
        'avatars/',
        'owners/',
        'applicants/',
    ];

    /** Shared catalog — never brand-prefixed, always main public disk. */
    public const SHARED_PREFIXES = [
        'projects/',
        'developers/',
    ];

    /** @var array<string, bool> */
    private static array $existsMemo = [];

    public static function allDisks(): array
    {
        return [self::DISK_LUXVANT_COMPANY, self::DISK_LUXVANT_PUBLIC, self::DISK_COMPANY, self::DISK_PUBLIC];
    }

    public static function isLuxvantDisk(string $disk): bool
    {
        return in_array($disk, [self::DISK_LUXVANT_PUBLIC, self::DISK_LUXVANT_COMPANY], true);
    }

    public static function isPrivateDisk(string $disk): bool
    {
        return in_array($disk, [self::DISK_COMPANY, self::DISK_LUXVANT_COMPANY], true);
    }

    /** Strip a leading luxvant/ or oia/ segment. */
    public static function logical(string $path): string
    {
        $path = CompanyFilePolicy::normalize($path);
        foreach (['luxvant/', 'oia/'] as $brand) {
            if (str_starts_with($path, $brand)) {
                return substr($path, strlen($brand));
            }
        }

        return $path;
    }

    private static function matches(string $logical, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            $p = rtrim($prefix, '/');
            if ($logical === $p || str_starts_with($logical, $p.'/')) {
                return true;
            }
        }

        return false;
    }

    public static function isShared(string $path): bool
    {
        return self::matches(self::logical($path), self::SHARED_PREFIXES);
    }

    public static function usesSeparateStorage(string $path): bool
    {
        return BrandIsolation::trustedKey() === 'luxvant'
            && self::matches(self::logical($path), self::SEPARATE_PREFIXES);
    }

    /**
     * Disk for a NEW upload into $directory (logical, e.g. "listings/gallery").
     */
    public static function diskForNewUpload(string $directory): string
    {
        $sensitive = CompanyFilePolicy::isSensitive($directory);

        if (! self::isShared($directory) && self::usesSeparateStorage($directory)) {
            return $sensitive ? self::DISK_LUXVANT_COMPANY : self::DISK_LUXVANT_PUBLIC;
        }

        return $sensitive ? self::DISK_COMPANY : self::DISK_PUBLIC;
    }

    /**
     * Public disk to write a NEW public image into (ImageHelper).
     */
    public static function publicDiskForNewUpload(string $directory): string
    {
        return (! self::isShared($directory) && self::usesSeparateStorage($directory))
            ? self::DISK_LUXVANT_PUBLIC
            : self::DISK_PUBLIC;
    }

    public static function exists(string $disk, string $path): bool
    {
        $key = $disk.'|'.$path;
        if (! array_key_exists($key, self::$existsMemo)) {
            try {
                self::$existsMemo[$key] = Storage::disk($disk)->exists($path);
            } catch (\Throwable) {
                self::$existsMemo[$key] = false;
            }
        }

        return self::$existsMemo[$key];
    }

    public static function forgetExists(string $path): void
    {
        foreach (self::allDisks() as $disk) {
            unset(self::$existsMemo[$disk.'|'.$path]);
        }
    }

    /**
     * Public disk that currently holds $path (Luxvant disk first for luxvant/ paths).
     */
    public static function publicDiskFor(?string $path): ?string
    {
        $path = CompanyFilePolicy::normalize((string) $path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'luxvant/') && self::exists(self::DISK_LUXVANT_PUBLIC, $path)) {
            return self::DISK_LUXVANT_PUBLIC;
        }

        return self::exists(self::DISK_PUBLIC, $path) ? self::DISK_PUBLIC : null;
    }

    public static function publicExists(?string $path): bool
    {
        return self::publicDiskFor($path) !== null;
    }

    /**
     * Any disk (private first) that holds $path.
     */
    public static function diskHolding(?string $path): ?string
    {
        $path = CompanyFilePolicy::normalize((string) $path);
        if ($path === '') {
            return null;
        }

        foreach (self::allDisks() as $disk) {
            if (self::isLuxvantDisk($disk) && ! str_starts_with($path, 'luxvant/')) {
                continue;
            }
            if (self::exists($disk, $path)) {
                return $disk;
            }
        }

        return null;
    }

    public static function publicDisk(?string $path): Filesystem
    {
        return Storage::disk(self::publicDiskFor($path) ?? self::DISK_PUBLIC);
    }
}
