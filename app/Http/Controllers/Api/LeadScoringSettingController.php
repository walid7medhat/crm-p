<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadScoringSetting;
use App\Services\LeadIntelligenceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadScoringSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return ApiResponse::success(LeadScoringSetting::resolved(), 'Lead scoring settings retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'weights' => 'required|array',
            'weights.budget' => 'required|numeric|min:0|max:100',
            'weights.whatsapp' => 'required|numeric|min:0|max:100',
            'weights.email' => 'required|numeric|min:0|max:100',
            'weights.source' => 'required|numeric|min:0|max:100',
            'weights.recency' => 'required|numeric|min:0|max:100',
            'weights.stage' => 'required|numeric|min:0|max:100',
            'thresholds' => 'required|array',
            'thresholds.hot' => 'required|numeric|min:0|max:100',
            'thresholds.warm' => 'required|numeric|min:0|max:100',
            'automation_flags' => 'required|array',
            'automation_flags.on_create' => 'required|boolean',
            'automation_flags.on_update' => 'required|boolean',
            'automation_flags.scheduled_enabled' => 'required|boolean',
            'ai_mode' => 'required|in:off,fallback,strict',
        ]);

        $row = LeadScoringSetting::query()->latest('id')->first();
        if ($row) {
            $row->update([
                'weights' => $request->weights,
                'thresholds' => $request->thresholds,
                'automation_flags' => $request->automation_flags,
                'ai_mode' => $request->ai_mode,
                'is_active' => true,
            ]);
        } else {
            LeadScoringSetting::create([
                'weights' => $request->weights,
                'thresholds' => $request->thresholds,
                'automation_flags' => $request->automation_flags,
                'ai_mode' => $request->ai_mode,
                'is_active' => true,
            ]);
        }

        LeadScoringSetting::clearResolvedCache();

        return ApiResponse::success(LeadScoringSetting::resolved(), 'Lead scoring settings saved successfully');
    }

    public function test(Request $request, LeadIntelligenceService $service): JsonResponse
    {
        $request->validate([
            'lead' => 'required|array',
            'lead.budget' => 'nullable|numeric|min:0',
            'lead.lead_source' => 'nullable|string',
            'lead.email' => 'nullable|string',
            'lead.whatsapp_number' => 'nullable|string',
            'lead.stage_id' => 'nullable|integer',
            'lead.comment' => 'nullable|string',
            'lead.raw_meta_data' => 'nullable',
            'lead.created_at' => 'nullable|date',
            'settings' => 'nullable|array',
        ]);

        $leadData = $request->input('lead', []);
        $lead = new Lead($leadData);
        $lead->created_at = !empty($leadData['created_at'])
            ? Carbon::parse($leadData['created_at'])
            : now();

        $settings = $request->input('settings');
        $result = $service->generateRecommendation($lead, is_array($settings) ? $settings : null);

        return ApiResponse::success([
            'result' => $result,
            'effective_settings' => is_array($settings) ? array_replace_recursive(LeadScoringSetting::resolved(), $settings) : LeadScoringSetting::resolved(),
        ], 'Lead scoring simulation completed');
    }
}
