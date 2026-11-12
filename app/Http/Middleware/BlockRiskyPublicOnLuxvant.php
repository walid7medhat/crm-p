<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Support\Brand\BrandContext;
use Closure;
use Illuminate\Http\Request;

/**
 * Blocks selected public/high-risk endpoints on the Luxvant brand only.
 * OIA callers keep existing behavior (middleware is a no-op for open brands).
 */
class BlockRiskyPublicOnLuxvant
{
    public function __construct(private BrandContext $brand)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$this->brand->isLuxvant()) {
            return $next($request);
        }

        return ApiResponse::error(
            'This endpoint is not available on the Luxvant workspace.',
            403
        );
    }
}
