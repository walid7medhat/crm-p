<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\NextActionTour\NextActionTourCampaignService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NextActionTourController extends Controller
{
    public function status(Request $request, NextActionTourCampaignService $service)
    {
        if (! $request->user()?->hasRole('super_admin')) {
            return ApiResponse::error('Only a super admin can manage this campaign.', 403);
        }

        return ApiResponse::success($service->status($this->feature($request)));
    }

    public function start(Request $request, NextActionTourCampaignService $service)
    {
        if (! $request->user()?->hasRole('super_admin')) {
            return ApiResponse::error('Only a super admin can start this campaign.', 403);
        }

        return ApiResponse::success($service->start($request->user(), $this->feature($request)), 'Campaign started.');
    }

    public function stop(Request $request, NextActionTourCampaignService $service)
    {
        if (! $request->user()?->hasRole('super_admin')) {
            return ApiResponse::error('Only a super admin can stop this campaign.', 403);
        }

        return ApiResponse::success($service->stop($request->user(), $this->feature($request)), 'Campaign stopped.');
    }

    public function me(Request $request, NextActionTourCampaignService $service)
    {
        return ApiResponse::success($service->forUser($request->user(), $this->feature($request)));
    }

    private function feature(Request $request): string
    {
        $validated = $request->validate([
            'feature' => ['sometimes', 'string', Rule::in([
                NextActionTourCampaignService::FEATURE_NEXT_ACTION,
                NextActionTourCampaignService::FEATURE_PROPERTY_COMPARE,
            ])],
        ]);

        return $validated['feature'] ?? NextActionTourCampaignService::FEATURE_NEXT_ACTION;
    }

    public function shown(Request $request, NextActionTourCampaignService $service)
    {
        $launchId = (string) $request->input('launch_id');
        if ($launchId === '') {
            return ApiResponse::error('launch_id is required.', 422);
        }

        return ApiResponse::success($service->recordShown($request->user(), $launchId));
    }

    public function dismissed(Request $request, NextActionTourCampaignService $service)
    {
        $launchId = (string) $request->input('launch_id');
        if ($launchId === '') {
            return ApiResponse::error('launch_id is required.', 422);
        }

        return ApiResponse::success($service->recordDismissed($request->user(), $launchId));
    }

    public function completed(Request $request, NextActionTourCampaignService $service)
    {
        $launchId = (string) $request->input('launch_id');
        if ($launchId === '') {
            return ApiResponse::error('launch_id is required.', 422);
        }

        return ApiResponse::success($service->recordCompleted($request->user(), $launchId));
    }
}
