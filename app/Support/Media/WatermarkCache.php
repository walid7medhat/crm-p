<?php

namespace App\Support\Media;

use App\Support\Brand\BrandIsolation;
use App\Support\Security\WatermarkMediaGate;

/**
 * Rendered watermark images saved as static files under public/wm-cache.
 *
 * The /image/watermark endpoint renders a photo once (after WatermarkMediaGate passes) and
 * writes it here. API resources then hand out the static URL, so every later view is served
 * by the web server directly — no PHP / Laravel boot per image.
 *
 * File names are a hash of brand + path + source mtime/size + watermark mtime, so:
 *  - replacing a photo or the brand watermark produces a new file (no stale images),
 *  - names cannot be guessed from a listing path.
 */
class WatermarkCache
{
    /** Bump when the rendering itself changes (rotation, size, quality). */
    private const VERSION = 'v2';

    private const DIR = 'wm-cache';

    /**
     * Static file (absolute) for a normalized public path, or null when the source is missing.
     */
    public static function fileFor(string $path, ?string $fullPath = null): ?string
    {
        $fullPath ??= WatermarkMediaGate::absolutePublicPath($path);
        if ($fullPath === null || ! is_file($fullPath)) {
            return null;
        }

        return public_path(self::relativeFor($path, $fullPath));
    }

    /**
     * Best URL for a watermarked image: the static file when it already exists,
     * otherwise the rendering endpoint (which creates it on first view).
     */
    public static function url(?string $rawPath): ?string
    {
        if ($rawPath === null || $rawPath === '') {
            return null;
        }

        try {
            $path = WatermarkMediaGate::normalize($rawPath);
            if ($path !== null) {
                $fullPath = WatermarkMediaGate::absolutePublicPath($path);
                if ($fullPath !== null && is_file($fullPath)) {
                    $relative = self::relativeFor($path, $fullPath);
                    if (is_file(public_path($relative))) {
                        return asset($relative);
                    }
                }
            }
        } catch (\Throwable) {
            // Fall through to the dynamic endpoint.
        }

        return route('image.watermark', ['path' => $rawPath]);
    }

    private static function relativeFor(string $path, string $fullPath): string
    {
        $watermark = BrandIsolation::watermarkAbsolutePath();
        $brand = preg_replace('/[^a-z0-9_-]/i', '', (string) BrandIsolation::trustedKey()) ?: 'default';

        $hash = sha1(implode('|', [
            self::VERSION,
            $brand,
            $path,
            (string) @filemtime($fullPath),
            (string) @filesize($fullPath),
            (string) ($watermark ? @filemtime($watermark) : 'none'),
        ]));

        return self::DIR.'/'.$brand.'/'.substr($hash, 0, 2).'/'.$hash.'.webp';
    }
}
