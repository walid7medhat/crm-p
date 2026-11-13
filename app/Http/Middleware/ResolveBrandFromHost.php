<?php

namespace App\Http\Middleware;

use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandCrmScope;
use App\Support\Brand\BrandResolver;
use Closure;
use Illuminate\Http\Request;

class ResolveBrandFromHost
{
    public function __construct(
        private BrandResolver $resolver,
        private BrandContext $context,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        BrandCrmScope::reset();

        $brand = $this->resolver->resolveFromRequest($request);
        $this->context->set($brand);

        $request->attributes->set('brand_key', $brand['key'] ?? 'oia');
        $request->attributes->set('brand', $brand);

        // Never trust client-supplied brand_key — strip from request input.
        if ($request->request->has('brand_key')) {
            $request->request->remove('brand_key');
        }
        if ($request->query->has('brand_key')) {
            $request->query->remove('brand_key');
        }
        if ($request->isJson() && is_array($request->json()->all()) && array_key_exists('brand_key', $request->json()->all())) {
            $json = $request->json()->all();
            unset($json['brand_key']);
            $request->json()->replace($json);
        }

        return $next($request);
    }
}
