<?php

namespace App\Http\Middleware;

use App\Support\Brand\BrandContext;
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
        $brand = $this->resolver->resolveFromRequest($request);
        $this->context->set($brand);

        $request->attributes->set('brand_key', $brand['key'] ?? 'oia');
        $request->attributes->set('brand', $brand);

        return $next($request);
    }
}
