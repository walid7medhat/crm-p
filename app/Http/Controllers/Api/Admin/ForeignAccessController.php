<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ForeignAccessEvent;
use App\Models\User;
use App\Services\Security\ForeignAccessMonitorService;
use App\Support\Brand\BrandIsolation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForeignAccessController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->hasRole('super_admin')) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $request->validate([
            'user_id' => 'nullable|integer|min:1',
            'country' => 'nullable|string|max:80',
            'country_code' => 'nullable|string|max:8',
            'status' => 'nullable|string|in:new,reviewed',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = ForeignAccessEvent::query()
            ->with(['user:id,name,email,display_name'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if (BrandIsolation::columnReady('foreign_access_events')) {
            BrandIsolation::constrain($query, 'foreign_access_events');
        }

        $this->excludeExemptUsers($query);

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }
        if ($request->filled('country_code')) {
            $query->where('country_code', strtoupper((string) $request->input('country_code')));
        } elseif ($request->filled('country')) {
            $query->where('country', 'like', '%'.$request->input('country').'%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('from')) {
            $query->where('occurred_at', '>=', Carbon::parse($request->input('from')));
        }
        if ($request->filled('to')) {
            $query->where('occurred_at', '<=', Carbon::parse($request->input('to')));
        }

        $perPage = (int) ($request->input('per_page') ?: 50);
        $page = $query->paginate($perPage);

        $rows = collect($page->items())->map(fn (ForeignAccessEvent $event) => $this->serialize($event));

        $openCount = ForeignAccessEvent::query()
            ->when(
                BrandIsolation::columnReady('foreign_access_events'),
                fn ($q) => BrandIsolation::constrain($q, 'foreign_access_events')
            )
            ->tap(fn ($q) => $this->excludeExemptUsers($q))
            ->where('status', 'new')
            ->count();

        return ApiResponse::success([
            'events' => $rows,
            'open_count' => $openCount,
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ], 'Foreign access events');
    }

    public function show(Request $request, ForeignAccessEvent $foreignAccess): JsonResponse
    {
        if (! $request->user()?->hasRole('super_admin')) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $this->ensureBrandAccess($foreignAccess);
        $foreignAccess->load(['user:id,name,email,display_name', 'reviewer:id,name,email,display_name']);

        return ApiResponse::success($this->serialize($foreignAccess, detailed: true), 'Foreign access event');
    }

    public function markReviewed(Request $request, ForeignAccessEvent $foreignAccess): JsonResponse
    {
        if (! $request->user()?->hasRole('super_admin')) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $this->ensureBrandAccess($foreignAccess);

        if ($foreignAccess->status !== 'reviewed') {
            $foreignAccess->forceFill([
                'status' => 'reviewed',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ])->save();
        }

        $foreignAccess->load(['user:id,name,email,display_name', 'reviewer:id,name,email,display_name']);

        return ApiResponse::success($this->serialize($foreignAccess, detailed: true), 'Marked as reviewed');
    }

    /**
     * Hide events of exempt users (super admins), including any recorded before the exemption.
     */
    private function excludeExemptUsers($query): void
    {
        $query->whereDoesntHave('user.roles', function ($q) {
            $q->whereIn('name', ForeignAccessMonitorService::EXEMPT_ROLES);
        });
    }

    private function ensureBrandAccess(ForeignAccessEvent $event): void
    {
        if (ForeignAccessMonitorService::isExempt($event->user)) {
            abort(404);
        }

        if (! BrandIsolation::columnReady('foreign_access_events')) {
            return;
        }
        if (BrandIsolation::superAdminCrossBrandEnabled()) {
            return;
        }
        if ((string) $event->brand_key !== BrandIsolation::trustedKey()) {
            abort(404);
        }
    }

    private function serialize(ForeignAccessEvent $event, bool $detailed = false): array
    {
        $user = $event->user;

        $payload = [
            'id' => $event->id,
            'user_id' => $event->user_id,
            'user_name' => $user ? User::resolveDisplayName($user) : null,
            'user_email' => $user?->email,
            'brand_key' => $event->brand_key,
            'ip' => $event->ip,
            'country' => $event->country,
            'country_code' => $event->country_code,
            'city' => $event->city,
            'region' => $event->region,
            'isp' => $event->isp,
            'browser' => $event->browser,
            'operating_system' => $event->operating_system,
            'device' => trim(implode(' / ', array_filter([$event->browser, $event->operating_system]))) ?: null,
            'session_key' => $event->session_key,
            'detection_reason' => $event->detection_reason,
            'status' => $event->status,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
            'reviewed_at' => $event->reviewed_at?->toIso8601String(),
            'reviewed_by' => $event->reviewed_by,
            'is_alert' => true,
        ];

        if ($detailed) {
            $payload['user_agent'] = $event->user_agent;
            $payload['meta'] = $event->meta;
            $payload['reviewer_name'] = $event->reviewer
                ? User::resolveDisplayName($event->reviewer)
                : null;
        }

        return $payload;
    }
}
