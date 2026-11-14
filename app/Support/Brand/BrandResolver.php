<?php

namespace App\Support\Brand;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BrandResolver
{
    public static function defaultBrand(): array
    {
        $key = (string) config('brands.default', 'oia');
        $brands = config('brands.brands', []);

        return $brands[$key] ?? ($brands['oia'] ?? [
            'key' => 'oia',
            'company_name' => 'OIA Properties',
            'company_name_short' => 'OIA',
            'company_name_display' => 'OIA PROPERTIES',
            'product_name' => 'Alt CRM',
            'product_mark' => 'A',
            'document_title' => 'Alt CRM',
            'logos' => [],
            'colors' => [],
            'copy' => [],
            'access' => ['mode' => 'open'],
            'hosts' => [],
        ]);
    }

    public function resolveFromRequest(Request $request): array
    {
        // Local/dev only — never honor BRAND_FORCE in production (would override live hosts).
        if (! app()->environment('production')) {
            $forced = strtolower(trim((string) env('BRAND_FORCE', '')));
            if ($forced !== '') {
                $brands = config('brands.brands', []);
                if (isset($brands[$forced])) {
                    return $brands[$forced];
                }
            }
        }

        // Prefer the raw Host header for brand selection so X-Forwarded-Host cannot
        // spoof Luxvant ↔ OIA when trustProxies(at: '*') is enabled.
        $rawHost = $request->headers->get('Host');
        if (is_string($rawHost) && trim($rawHost) !== '') {
            return $this->resolveFromHost($rawHost);
        }

        return $this->resolveFromHost($request->getHost());
    }

    public function resolveFromHost(?string $host): array
    {
        $host = strtolower(trim((string) $host));
        $host = preg_replace('/:\d+$/', '', $host) ?: $host;

        $brands = config('brands.brands', []);

        // 1) Exact host match wins (prevents luxvant.localhost matching OIA "localhost").
        foreach ($brands as $brand) {
            foreach ($brand['hosts'] ?? [] as $allowed) {
                $allowed = strtolower(trim((string) $allowed));
                if ($allowed !== '' && $host === $allowed) {
                    return $brand;
                }
            }
        }

        // 2) Longest subdomain suffix wins (foo.luxvant.altcrm.com → luxvant).
        $bestBrand = null;
        $bestLength = -1;
        foreach ($brands as $brand) {
            foreach ($brand['hosts'] ?? [] as $allowed) {
                $allowed = strtolower(trim((string) $allowed));
                if ($allowed === '') {
                    continue;
                }
                if (str_ends_with($host, '.' . $allowed) && strlen($allowed) > $bestLength) {
                    $bestBrand = $brand;
                    $bestLength = strlen($allowed);
                }
            }
        }
        if ($bestBrand !== null) {
            return $bestBrand;
        }

        if (config('brands.reject_unknown_hosts')) {
            Log::warning('brand resolver: unknown host rejected', ['host' => $host]);
            abort(404, 'Unknown host');
        }

        // Local / unmapped hosts keep OIA unchanged.
        return self::defaultBrand();
    }

    /**
     * Whether the authenticated user may use this brand workspace.
     */
    public function userMayAccess(array $brand, $user): bool
    {
        if (!$user) {
            return false;
        }

        $access = $brand['access'] ?? ['mode' => 'open'];
        $mode = $access['mode'] ?? 'open';
        $brandKey = (string) ($brand['key'] ?? 'oia');

        // Platform Super Admin is the one identity shared by both companies.
        if (self::isSuperAdmin($user)) {
            return true;
        }

        if ($mode === 'open') {
            // OIA host: members of another company (Luxvant) may not sign in here.
            return ! self::userIsMemberOfOtherBrand($user, $brandKey);
        }

        $roles = $access['roles'] ?? [];
        if (!empty($roles) && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles)) {
            return true;
        }

        // Stamped members of this brand.
        if ((string) ($user->brand_key ?? '') === $brandKey) {
            return true;
        }

        $userIds = array_map('intval', $access['user_ids'] ?? []);
        if (in_array((int) $user->id, $userIds, true)) {
            return true;
        }

        $emails = array_map('strtolower', $access['emails'] ?? []);
        $email = strtolower((string) ($user->email ?? ''));
        if ($email !== '' && in_array($email, $emails, true)) {
            return true;
        }

        // Luxvant host: members of the configured CRM branch (Admin Luxvant tree) may enter.
        if (($brand['key'] ?? '') === 'luxvant') {
            return BrandCrmScope::userInConfiguredCrmBranch((int) $user->id);
        }

        return false;
    }

    public static function isSuperAdmin($user): bool
    {
        return $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
    }

    /**
     * True when the user belongs to a brand other than $brandKey
     * (stamped brand_key, or — for Luxvant — the configured CRM branch tree).
     */
    public static function userIsMemberOfOtherBrand($user, string $brandKey): bool
    {
        $userBrand = (string) ($user->brand_key ?? '');
        if ($userBrand !== '' && $userBrand !== $brandKey) {
            return true;
        }

        if ($brandKey !== 'luxvant' && BrandCrmScope::userInConfiguredCrmBranch((int) $user->id)) {
            return true;
        }

        return false;
    }
}
