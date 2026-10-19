<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\NextActionTour\NextActionTourCampaignService;
use Illuminate\Http\Request;

class NextActionTourController extends Controller
{
    public function status(Request $request, NextActionTourCampaignService $service)
    {
        if (! $request->user()?->hasRole('super_admin')) {
            return ApiResponse::error('Only a super admin can manage this campaign.', 403);
        }

        return ApiResponse::success($service->status());
    }

    public function start(Request $request, NextActionTourCampaignService $service)
    {
        if (! $request->user()?->hasRole('super_admin')) {
            return ApiResponse::error('Only a super admin can start this campaign.', 403);
        }

        return ApiResponse::success($service->start($request->user()), 'Campaign started.');
    }

    public function stop(Request $request, NextActionTourCampaignService $service)
    {
        if (! $request->user()?->hasRole('super_admin')) {
            return ApiResponse::error('Only a super admin can stop this campaign.', 403);
        }

        return ApiResponse::success($service->stop($request->user()), 'Campaign stopped.');
    }

    public function me(Request $request, NextActionTourCampaignService $service)
    {
        return ApiResponse::success($service->forUser($request->user()));
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
