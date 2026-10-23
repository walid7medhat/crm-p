<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
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

        $onlyUserId = $request->filled('user_id') ? (int) $request->input('user_id') : null;
        if ($onlyUserId) {
            $target = User::query()->where('id', $onlyUserId)->where('status', 'active')->first();
            if (! $target) {
                return ApiResponse::error('Choose an active user.', 422);
            }
        }

        return ApiResponse::success($service->start($request->user(), $this->feature($request), $onlyUserId), 'Campaign started.');
    }

    public function users(Request $request)
    {
        if (! $request->user()?->hasRole('super_admin')) {
            return ApiResponse::error('Only a super admin can manage this campaign.', 403);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search === '') {
            return ApiResponse::success([]);
        }

        $users = User::query()
            ->select(['id', 'name', 'display_name', 'email', 'status'])
            ->where('status', 'active')
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('display_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
                if (ctype_digit($search)) {
                    $query->orWhere('id', (int) $search);
                }
            })
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->display_name ?: $user->name,
                'email' => $user->email,
            ])
            ->values();

        return ApiResponse::success($users);
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
