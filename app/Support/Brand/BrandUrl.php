<?php

namespace App\Support\Brand;

use App\Models\User;

/**
 * Site address for links in emails / notifications: Luxvant people get Luxvant links,
 * OIA people get OIA links — also from queues and scheduled jobs, where url() would
 * fall back to APP_URL (the OIA domain).
 */
class BrandUrl
{
    /** Base URL (no trailing slash) of a brand; defaults to the current brand context. */
    public static function base(?string $brandKey = null): string
    {
        $brandKey ??= app(BrandContext::class)->key();
        $brand = config("brands.brands.{$brandKey}", []);

        $url = $brand['app_url'] ?? null;
        if (! $url && $brandKey !== 'oia' && ! empty($brand['hosts'][0])) {
            $url = 'https://'.$brand['hosts'][0];
        }
        $url = $url ?: config('app.frontend_url', config('app.url'));

        return rtrim((string) $url, '/');
    }

    /**
     * Base URL for a recipient: their company's site. Super admin and shared users
     * (#30, #33) work on both, so they get the site currently in use.
     */
    public static function forUser($user): string
    {
        if (! $user instanceof User) {
            return self::base();
        }

        $shared = array_map('intval', (array) config('brands.shared_user_ids', []));
        if (BrandResolver::isSuperAdmin($user) || in_array((int) $user->id, $shared, true)) {
            return self::base();
        }

        return self::base(BrandIsolation::brandKeyOfUser($user));
    }

    /** Full link for a recipient, e.g. BrandUrl::to('/leads/5', $user). */
    public static function to(string $path, $user = null): string
    {
        $base = $user ? self::forUser($user) : self::base();

        return $base.'/'.ltrim($path, '/');
    }
}
