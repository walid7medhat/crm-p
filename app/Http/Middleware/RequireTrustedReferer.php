<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequireTrustedReferer
{
    /** A host is allowed if it equals one of these or is a subdomain of one. */
    private array $allowedHosts = [
        // CRM
        'oiaproperties.altcrm.com',
        // 'oiaproperties.altcrm.ae',

        // Landing pages
        'oiaproperties.com',
        'alrahabeachprojects.ae',
        'alreemislandprojects.ae',
        'athlonbyaldar.ae',
        'bayn-by-ora.com',
        'binghattiprojects.ae',
        'burtvilleprojects.ae',
        'emiratesdevelopments.ae',
        'fahidislandprojects.ae',
        'ghantootprojects.ae',
        'hq-by-rove.ae',
        'hudayriyat-island-villas.com',
        'hudayriyatprojects.ae',
        'luxvant.com',
        'manchesteryasresidences.ae',
        'masdarcityprojects.ae',
        'modonprojects.ae',
        'nawayef-village.allproperties.ae',
        'nawayef-village.aqarmarket.ae',
        'ohana-projects.ae',
        'onedevelopment.ae',
        'palmhills-uae.ae',
        'ramhanislandprojects.ae',
        'saadiyat-culturaldistrict.ae',
        'saadiyatgrove-projects.ae',
        'saadiyatisland-projects.ae',
        'yascanal-projects.ae',
        'yascanalbyohana.ae',
        'yasislandprojects.ae',
        'yasresidencesbyohana.ae',
    ];

    /** Paths that never send a referer. Adjust to YOUR real routes. */
    private array $exemptPaths = [
        'api/login',
        'api/webhooks/*',
        'api/bitrix/*',
        'api/mobile/*',
         'api/website-lead',
    'api/website-lead/*',
    ];

    public function handle(Request $request, Closure $next)
    {
       $mode = env('REFERER_GUARD', 'log');

        // Disabled, CORS preflight, or exempt path: let it through
        if ($mode === 'off'
            || $request->isMethod('OPTIONS')
            || $request->is(...$this->exemptPaths)) {
            return $next($request);
        }

        // Use Referer, fall back to Origin
        $source = $request->header('Referer') ?: $request->header('Origin');
        $host   = $source ? parse_url($source, PHP_URL_HOST) : null;

        if ($this->isAllowed($host)) {
            return $next($request);
        }

        Log::channel('endpoint_usage')->warning(
            'referer guard: ' . ($mode === 'enforce' ? 'BLOCKED' : 'would block'),
            [
                'user_id' => $request->user()?->id,
                'ip'      => $request->ip(),
                'path'    => $request->path(),
                'source'  => $source,
                'ua'      => $request->userAgent(),
            ]
        );

        if ($mode === 'enforce') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request); // log mode: let it through
    }

    private function isAllowed($host): bool
    {
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        foreach ($this->allowedHosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }
}