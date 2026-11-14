<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\LeadPoolAssignment;
use App\Models\User;
use App\Services\LeadPoolAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lead Pool Settings: per agent — may they see the Lead Pool, and their "Assign To Me"
 * limits per day / per hour (defaults 40 / 10).
 *
 * super_admin and user #30 manage every agent; users with the manage-lead-pool-settings
 * permission manage their own branch.
 *
 *   GET /api/lead-pool/settings?search=
 *   PUT /api/lead-pool/settings/bulk     { user_ids[], lead_pool_enabled, daily_limit, hourly_limit }
 *   PUT /api/lead-pool/settings/{user}   { lead_pool_enabled, daily_limit, hourly_limit }
 */
class LeadPoolSettingsController extends Controller
{
    public function __construct(private LeadPoolAssignmentService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (! $this->service->canManageSettings($user)) {
            return ApiResponse::error('You are not allowed to manage Lead Pool settings', 403);
        }

        $search = trim((string) $request->query('search', ''));

        $agents = $this->service->manageableAgentsQuery($user)
            ->with('roles')
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->get();

        $todayCounts = LeadPoolAssignment::query()
            ->whereIn('user_id', $agents->pluck('id'))
            ->where('assigned_at', '>=', now()->startOfDay())
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        return ApiResponse::success([
            'agents' => $agents->map(fn (User $agent) => $this->row($agent, (int) ($todayCounts[$agent->id] ?? 0)))->values(),
            'defaults' => [
                'daily_limit' => LeadPoolAssignmentService::DAILY_LIMIT,
                'hourly_limit' => LeadPoolAssignmentService::BATCH_LIMIT,
                'max_daily_limit' => LeadPoolAssignmentService::MAX_DAILY_LIMIT,
                'max_hourly_limit' => LeadPoolAssignmentService::MAX_HOURLY_LIMIT,
            ],
            'scope' => $this->service->managesAllSettings($user) ? 'all' : 'branch',
        ], 'Lead Pool settings');
    }

    public function update(Request $request, int $user): JsonResponse
    {
        $manager = auth()->user();
        if (! $this->service->canManageSettings($manager)) {
            return ApiResponse::error('You are not allowed to manage Lead Pool settings', 403);
        }
        if (! $this->service->canManageAgent($manager, $user)) {
            return ApiResponse::error('You can only manage agents in your branch', 403);
        }

        $data = $request->validate($this->rules());

        $agent = User::with('roles')->findOrFail($user);
        $this->apply($agent, $data);

        return ApiResponse::success($this->row($agent, $this->service->getTodayCount($agent->id)), 'Lead Pool settings saved');
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $manager = auth()->user();
        if (! $this->service->canManageSettings($manager)) {
            return ApiResponse::error('You are not allowed to manage Lead Pool settings', 403);
        }

        $data = $request->validate($this->rules() + [
            'user_ids' => 'required|array|min:1|max:1000',
            'user_ids.*' => 'integer|distinct',
        ]);

        $ids = array_map('intval', $data['user_ids']);
        $agents = $this->service->manageableAgentsQuery($manager)->whereIn('id', $ids)->get();

        if ($agents->count() !== count($ids)) {
            return ApiResponse::error('You can only manage agents in your branch', 403);
        }

        foreach ($agents as $agent) {
            $this->apply($agent, $data);
        }

        return ApiResponse::success(['updated' => $agents->count()], 'Lead Pool settings saved');
    }

    private function rules(): array
    {
        return [
            'lead_pool_enabled' => 'required|boolean',
            'daily_limit' => 'required|integer|min:1|max:' . LeadPoolAssignmentService::MAX_DAILY_LIMIT,
            'hourly_limit' => 'required|integer|min:1|max:' . LeadPoolAssignmentService::MAX_HOURLY_LIMIT . '|lte:daily_limit',
        ];
    }

    private function apply(User $agent, array $data): void
    {
        $agent->forceFill([
            'lead_pool_enabled' => (bool) $data['lead_pool_enabled'],
            'lead_pool_daily_limit' => (int) $data['daily_limit'],
            'lead_pool_hourly_limit' => (int) $data['hourly_limit'],
        ])->save();
    }

    private function row(User $agent, int $todayCount): array
    {
        return [
            'id' => $agent->id,
            'name' => User::resolveDisplayName($agent),
            'email' => $agent->email,
            'role' => ucwords(str_replace('_', ' ', (string) $agent->roles->first()?->name)),
            'branch' => User::resolveDisplayName($agent->admin_parent),
            'lead_pool_enabled' => $agent->lead_pool_enabled === null || (bool) $agent->lead_pool_enabled,
            'daily_limit' => $this->service->dailyLimitFor($agent),
            'hourly_limit' => $this->service->hourlyLimitFor($agent),
            'today_count' => $todayCount,
        ];
    }
}
