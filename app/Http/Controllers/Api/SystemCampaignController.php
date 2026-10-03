<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SystemCampaign;
use App\Models\SystemCampaignImpression;
use App\Support\SystemCampaignCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SystemCampaignController extends Controller
{
    /** super_admin or user 33 (same exception used elsewhere for user 33). */
    private function assertCanManage(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasRole('super_admin') && (int) $user->id !== 33)) {
            return ApiResponse::error('Unauthorized access.', 403);
        }

        return null;
    }

    public function index(Request $request)
    {
        if ($denied = $this->assertCanManage($request)) {
            return $denied;
        }

        $campaigns = SystemCampaign::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (SystemCampaign $campaign) => $this->present($campaign))
            ->values();

        return ApiResponse::success([
            'campaigns' => $campaigns,
            'audiences' => $this->audienceOptions(),
            'frequency_hours' => SystemCampaignCatalog::frequencyHours(),
        ], 'Announcements retrieved successfully');
    }

    public function store(Request $request)
    {
        if ($denied = $this->assertCanManage($request)) {
            return $denied;
        }

        $validator = $this->validator($request, true);
        if ($validator->fails()) {
            return ApiResponse::error('Validation error', 422, $validator->errors());
        }

        $desktopPath = null;
        $mobilePath = null;

        try {
            $desktopPath = $request->file('desktop_image')->store('system-campaigns', 'public');
            $mobilePath = $request->file('mobile_image')->store('system-campaigns', 'public');

            $campaign = SystemCampaign::create([
                'title' => trim((string) $request->input('title')),
                'audiences' => array_values($request->input('audiences', [])),
                'frequency_hours' => (int) $request->input('frequency_hours'),
                'is_active' => $request->boolean('is_active'),
                'desktop_image_path' => $desktopPath,
                'mobile_image_path' => $mobilePath,
                'created_by' => $request->user()->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->deletePaths(array_filter([$desktopPath, $mobilePath]));
            return ApiResponse::error('Failed to save announcement');
        }

        return ApiResponse::success($this->present($campaign), 'Announcement created successfully', 201);
    }

    public function update(Request $request, SystemCampaign $campaign)
    {
        if ($denied = $this->assertCanManage($request)) {
            return $denied;
        }

        $validator = $this->validator($request, false);
        if ($validator->fails()) {
            return ApiResponse::error('Validation error', 422, $validator->errors());
        }

        $desktopPath = null;
        $mobilePath = null;
        $previousDesktop = $campaign->desktop_image_path;
        $previousMobile = $campaign->mobile_image_path;

        try {
            if ($request->hasFile('desktop_image')) {
                $desktopPath = $request->file('desktop_image')->store('system-campaigns', 'public');
            }
            if ($request->hasFile('mobile_image')) {
                $mobilePath = $request->file('mobile_image')->store('system-campaigns', 'public');
            }

            $campaign->update([
                'title' => trim((string) $request->input('title')),
                'audiences' => array_values($request->input('audiences', [])),
                'frequency_hours' => (int) $request->input('frequency_hours'),
                'is_active' => $request->boolean('is_active'),
                'desktop_image_path' => $desktopPath ?: $previousDesktop,
                'mobile_image_path' => $mobilePath ?: $previousMobile,
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->deletePaths(array_filter([$desktopPath, $mobilePath]));
            return ApiResponse::error('Failed to update announcement');
        }

        if ($desktopPath && $previousDesktop && $previousDesktop !== $desktopPath) {
            $this->deletePaths([$previousDesktop]);
        }
        if ($mobilePath && $previousMobile && $previousMobile !== $mobilePath) {
            $this->deletePaths([$previousMobile]);
        }

        return ApiResponse::success($this->present($campaign->fresh()), 'Announcement updated successfully');
    }

    public function updateActive(Request $request, SystemCampaign $campaign)
    {
        if ($denied = $this->assertCanManage($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation error', 422, $validator->errors());
        }

        $campaign->update(['is_active' => $request->boolean('is_active')]);

        return ApiResponse::success($this->present($campaign->fresh()), 'Announcement updated successfully');
    }

    public function destroy(Request $request, SystemCampaign $campaign)
    {
        if ($denied = $this->assertCanManage($request)) {
            return $denied;
        }

        $campaign->deleteStoredImages();
        $campaign->delete();

        return ApiResponse::success(null, 'Announcement deleted successfully');
    }

    /**
     * Active announcements the current user is allowed to see, and whose
     * frequency window has elapsed since they were last shown.
     */
    public function due(Request $request)
    {
        $user = $request->user();
        $campaigns = SystemCampaign::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($campaigns->isEmpty()) {
            return ApiResponse::success([]);
        }

        $impressions = SystemCampaignImpression::query()
            ->where('user_id', $user->id)
            ->whereIn('system_campaign_id', $campaigns->pluck('id'))
            ->get()
            ->keyBy('system_campaign_id');

        $due = $campaigns
            ->filter(fn (SystemCampaign $campaign) => $campaign->isDueFor($user, $impressions->get($campaign->id)))
            ->map(fn (SystemCampaign $campaign) => $this->present($campaign))
            ->values();

        return ApiResponse::success($due);
    }

    public function shown(Request $request, SystemCampaign $campaign)
    {
        $user = $request->user();
        if (!$this->userCanSee($user, $campaign)) {
            return ApiResponse::error('This announcement is not available.', 403);
        }

        $impression = SystemCampaignImpression::firstOrNew([
            'system_campaign_id' => $campaign->id,
            'user_id' => $user->id,
        ]);

        if ($campaign->isWithinCooldown($impression->shown_at)) {
            return ApiResponse::success(null, 'Already shown');
        }

        $impression->shown_at = now();
        $impression->save();

        return ApiResponse::success(null, 'Recorded');
    }

    public function dismiss(Request $request, SystemCampaign $campaign)
    {
        $user = $request->user();
        if (!$this->userCanSee($user, $campaign)) {
            return ApiResponse::error('This announcement is not available.', 403);
        }

        $impression = SystemCampaignImpression::firstOrNew([
            'system_campaign_id' => $campaign->id,
            'user_id' => $user->id,
        ]);

        if (!$impression->shown_at || !$campaign->isWithinCooldown($impression->shown_at)) {
            $impression->shown_at = now();
        }
        $impression->dismissed_at = now();
        $impression->save();

        return ApiResponse::success(null, 'Dismissed');
    }

    private function userCanSee($user, SystemCampaign $campaign): bool
    {
        return $campaign->is_active && SystemCampaignCatalog::matches($user, $campaign->audiences ?? []);
    }

    private function validator(Request $request, bool $imagesRequired)
    {
        $imageRule = ($imagesRequired ? 'required' : 'nullable') . '|image|mimes:jpeg,png,jpg,webp|max:8192';

        return Validator::make($request->all(), [
            'title' => 'required|string|max:160',
            'audiences' => 'required|array|min:1',
            'audiences.*' => 'required|string|in:' . implode(',', SystemCampaignCatalog::keys()),
            'frequency_hours' => 'required|integer|min:1|max:' . SystemCampaignCatalog::MAX_FREQUENCY_HOURS,
            'is_active' => 'required|boolean',
            'desktop_image' => $imageRule,
            'mobile_image' => $imageRule,
        ]);
    }

    private function present(SystemCampaign $campaign): array
    {
        $audiences = $campaign->audiences ?? [];

        return [
            'id' => $campaign->id,
            'title' => $campaign->title,
            'audiences' => $audiences,
            'audience_labels' => SystemCampaignCatalog::labels($audiences),
            'frequency_hours' => (int) $campaign->frequency_hours,
            'frequency_label' => SystemCampaignCatalog::frequencyLabel((int) $campaign->frequency_hours),
            'is_active' => (bool) $campaign->is_active,
            'desktop_image_url' => $campaign->desktop_image_url,
            'mobile_image_url' => $campaign->mobile_image_url,
            'created_at' => $campaign->created_at?->toIso8601String(),
        ];
    }

    private function audienceOptions(): array
    {
        $options = [];
        foreach (SystemCampaignCatalog::audiences() as $key => $label) {
            $options[] = ['key' => $key, 'label' => $label];
        }

        return $options;
    }

    private function deletePaths(array $paths): void
    {
        $disk = Storage::disk('public');
        foreach ($paths as $path) {
            if ($path && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }
}
