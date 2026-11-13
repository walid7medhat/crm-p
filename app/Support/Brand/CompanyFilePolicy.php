<?php

namespace App\Support\Brand;

/**
 * Classifies storage paths as public (allowlisted) vs company-sensitive (private disk).
 *
 * Existing OIA public URLs under allowlisted / legacy public roots remain valid.
 * New sensitive uploads go to the private "company" disk and are served only
 * through authenticated, brand-checked endpoints.
 */
class CompanyFilePolicy
{
    /**
     * Relative prefixes that may remain on the public disk (CRM gallery / branding).
     * Everything else company-owned is treated as sensitive.
     *
     * @var list<string>
     */
    public const PUBLIC_PREFIXES = [
        'listings/gallery',
        'listings/hero',
        'listings/floor_plans',
        'projects/',
        'avatars/',
        'users/avatars',
        'backgrounds/',
        'developers/avatars',
        'images/features',
        'system-campaigns/',
        'Setting/',
        'watermark/',
        // Brand-prefixed public equivalents (Luxvant gallery etc.)
        'luxvant/listings/gallery',
        'luxvant/listings/hero',
        'luxvant/listings/floor_plans',
        'luxvant/projects/',
        'luxvant/avatars/',
        'luxvant/users/avatars',
        'luxvant/backgrounds/',
        'luxvant/developers/avatars',
        'luxvant/images/features',
        'luxvant/system-campaigns/',
        'oia/listings/gallery',
        'oia/listings/hero',
        'oia/listings/floor_plans',
        'oia/projects/',
    ];

    /**
     * Sensitive prefixes — must use private company disk for NEW uploads.
     *
     * @var list<string>
     */
    public const SENSITIVE_PREFIXES = [
        'deals/',
        'comments/attachments',
        'owners/documents',
        'owners/additional_documents',
        'listings/documents',
        'listings/additional_documents',
        'employees/',
        'attendance/',
        'leave_attachments/',
        'document_requests/',
        'applicants/',
        'marketing/references',
        'offer-bases/',
    ];

    public static function normalize(?string $path): string
    {
        $path = ltrim(str_replace('\\', '/', (string) $path), '/');
        foreach (['storage/', 'public/storage/', 'app/public/', 'app/private/', 'company/'] as $strip) {
            if (str_starts_with($path, $strip)) {
                $path = substr($path, strlen($strip));
            }
        }

        // Collapse traversal
        $parts = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $seg;
        }

        return implode('/', $parts);
    }

    public static function isPublicAllowlisted(?string $path): bool
    {
        $path = self::normalize($path);
        if ($path === '') {
            return false;
        }

        foreach (self::PUBLIC_PREFIXES as $prefix) {
            $prefix = rtrim($prefix, '/');
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    public static function isSensitive(?string $path): bool
    {
        $path = self::normalize($path);
        if ($path === '') {
            return false;
        }

        // Strip brand prefix for classification
        foreach (['luxvant/', 'oia/'] as $brand) {
            if (str_starts_with($path, $brand)) {
                $path = substr($path, strlen($brand));
                break;
            }
        }

        foreach (self::SENSITIVE_PREFIXES as $prefix) {
            $prefix = rtrim($prefix, '/');
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        // Default: if not public allowlisted, treat as sensitive.
        return ! self::isPublicAllowlisted($path);
    }

    /**
     * Disk for a NEW upload of the given logical category path (without brand prefix).
     */
    public static function diskForNewUpload(string $logicalPath): string
    {
        return self::isSensitive($logicalPath) ? 'company' : 'public';
    }
}
