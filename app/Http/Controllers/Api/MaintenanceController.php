<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\Maintenance\MaintenanceModeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class MaintenanceController extends Controller
{
    public function status(Request $request, MaintenanceModeService $service): JsonResponse
    {
        $payload = $service->statusPayload();
        $user = $this->optionalUser($request);
        $affectsMe = $service->subjectIsAffected($user)
            && ! ($user && $user->hasRole('super_admin') && ! $this->isImpersonating());

        return ApiResponse::success([
            'enabled' => (bool) ($payload['enabled'] ?? false),
            'scope' => $payload['scope'] ?? 'all',
            'branch_user_ids' => $payload['branch_user_ids'] ?? [],
            'title' => $payload['title'] ?? null,
            'message' => $payload['message'] ?? null,
            'estimated_return' => $payload['estimated_return'] ?? null,
            'updated_at' => $payload['updated_at'] ?? null,
            'affects_me' => $affectsMe,
        ], 'Maintenance status');
    }

    public function show(Request $request, MaintenanceModeService $service): JsonResponse
    {
        if (! $this->assertSuperAdmin($request)) {
            return ApiResponse::error('Only a super admin can manage maintenance mode.', 403);
        }

        return ApiResponse::success(
            $service->toAdminArray($service->current()),
            'Maintenance settings'
        );
    }

    public function update(Request $request, MaintenanceModeService $service): JsonResponse
    {
        if (! $this->assertSuperAdmin($request)) {
            return ApiResponse::error('Only a super admin can manage maintenance mode.', 403);
        }

        $validated = $request->validate([
            'is_enabled' => ['sometimes', 'boolean'],
            'scope' => ['sometimes', 'string', 'in:all,branches,users'],
            'branch_user_ids' => ['sometimes', 'nullable', 'array'],
            'branch_user_ids.*' => ['integer'],
            'except_admin_role' => ['sometimes', 'boolean'],
            'except_user_ids' => ['sometimes', 'nullable', 'array', 'max:50'],
            'except_user_ids.*' => ['integer'],
            'target_user_ids' => ['sometimes', 'nullable', 'array', 'max:50'],
            'target_user_ids.*' => ['integer'],
            'title' => ['sometimes', 'nullable', 'string', 'max:160'],
            'message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'estimated_return' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if ($validated === []) {
            return ApiResponse::error('No maintenance fields provided.', 422);
        }

        try {
            $service->update($validated, $request->user());
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            $service->toAdminArray($service->current()),
            'Maintenance settings updated'
        );
    }

    public function users(Request $request, MaintenanceModeService $service): JsonResponse
    {
        if (! $this->assertSuperAdmin($request)) {
            return ApiResponse::error('Only a super admin can manage maintenance mode.', 403);
        }

        return ApiResponse::success(
            $service->searchUsers((string) $request->query('search', '')),
            'Maintenance user search'
        );
    }

    private function assertSuperAdmin(Request $request): bool
    {
        $user = $request->user();

        return $user && $user->hasRole('super_admin');
    }

    private function optionalUser(Request $request): ?\App\Models\User
    {
        try {
            if (! $request->bearerToken()) {
                return null;
            }
            $user = JWTAuth::setToken($request->bearerToken())->authenticate();

            return $user instanceof \App\Models\User ? $user : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function isImpersonating(): bool
    {
        try {
            return (bool) JWTAuth::getPayload()->get('impersonated_by');
        } catch (\Throwable) {
            return false;
        }
    }
}
