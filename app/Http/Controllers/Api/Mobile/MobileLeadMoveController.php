<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\Mobile\MobileLeadMoveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileLeadMoveController extends Controller
{
    public function store(Request $request, Lead $lead, MobileLeadMoveService $service): JsonResponse
    {
        $request->validate([
            'stage_id' => 'required|exists:stages,id',
            'expected_updated_at' => 'nullable|date',
            'idempotency_key' => 'nullable|string|max:128',
        ]);

        return $service->move($request, $lead);
    }
}
