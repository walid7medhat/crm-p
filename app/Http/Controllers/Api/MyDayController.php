<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\MyDay\MyDayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyDayController extends Controller
{
    public function __construct(private MyDayService $myDay)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        return ApiResponse::success(
            $this->myDay->forUser($user),
            'Personal actions retrieved'
        );
    }
}
