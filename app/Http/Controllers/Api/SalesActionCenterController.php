<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SalesAction\SalesActionCenterService;
use Illuminate\Http\Request;

class SalesActionCenterController extends Controller
{
    public function index(Request $request, SalesActionCenterService $center)
    {
        $actor = $request->user();
        $subject = $actor;
        $preview = false;

        $previewId = (int) $request->input('preview_user_id');
        if ($previewId && $previewId !== (int) $actor->id) {
            if (! $actor->hasRole('super_admin')) {
                return ApiResponse::error('Only a super admin can preview another agent.', 403);
            }

            $subject = User::query()->find($previewId);
            if (! $subject) {
                return ApiResponse::error('Agent not found.', 404);
            }

            $preview = true;
        }

        $data = $center->center(
            $subject,
            $request->input('category'),
            $request->input('search'),
            max(1, (int) $request->input('page', 1))
        );
        $data['preview'] = $preview;
        $data['live'] = ! $preview;
        $data['subject_user_id'] = (int) $subject->id;
        $data['subject_name'] = $subject->name;

        return ApiResponse::success($data);
    }

    public function agents(Request $request)
    {
        $actor = $request->user();
        if (! $actor->hasRole('super_admin')) {
            return ApiResponse::error('Only a super admin can list agents for preview.', 403);
        }

        $search = trim((string) $request->input('search'));
        $agents = User::role('sales')
            ->where('status', 'active')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->orderBy('name')
            ->limit(40)
            ->get(['id', 'name']);

        return ApiResponse::success($agents);
    }
}
