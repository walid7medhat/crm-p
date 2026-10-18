<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Jobs\RunLeadIntelligenceScan;
use App\Models\Lead;
use App\Models\LeadIntelligenceScan;
use App\Services\LeadScan\LeadScanRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LeadIntelligenceScanController extends Controller
{
    public function latest(Lead $lead): JsonResponse
    {
        $denied = $this->deny($lead);
        if ($denied) {
            return $denied;
        }

        $scan = $this->visibleScan($lead);

        return ApiResponse::success($scan?->toPanel(), $scan ? 'Scan loaded' : 'No scan yet');
    }

    public function show(Lead $lead, LeadIntelligenceScan $scan): JsonResponse
    {
        $denied = $this->deny($lead);
        if ($denied) {
            return $denied;
        }
        if ((int) $scan->lead_id !== (int) $lead->id) {
            return ApiResponse::error('Scan not found', 404);
        }

        return ApiResponse::success($scan->load('candidates')->toPanel(), 'Scan loaded');
    }

    public function store(Request $request, Lead $lead): JsonResponse
    {
        $denied = $this->deny($lead);
        if ($denied) {
            return $denied;
        }

        $force = $request->boolean('force');
        $active = LeadIntelligenceScan::query()
            ->where('lead_id', $lead->id)
            ->whereIn('status', ['queued', 'processing'])
            ->latest('id')
            ->first();
        if ($active) {
            $payload = $active->load('candidates')->toPanel();
            $payload['reused'] = true;

            return ApiResponse::success($payload, 'A scan is already running');
        }

        if (! $force) {
            $fresh = LeadIntelligenceScan::query()
                ->where('lead_id', $lead->id)
                ->where('status', 'completed')
                ->where('fresh_until', '>', now())
                ->latest('id')
                ->first();
            if ($fresh) {
                $payload = $fresh->load('candidates')->toPanel();
                $payload['reused'] = true;

                return ApiResponse::success($payload, 'A recent scan is still fresh');
            }
        }

        if (! Cache::add(LeadScanRunner::lockKey($lead->id), 1, 30)) {
            return ApiResponse::error('A scan is already starting for this lead', 429);
        }

        try {
            $scan = LeadIntelligenceScan::query()->create([
                'lead_id' => $lead->id,
                'user_id' => $request->user()->id,
                'status' => 'queued',
                'progress' => 5,
                'status_message' => 'Waiting to search. Progress is checked every few seconds.',
            ]);

            RunLeadIntelligenceScan::dispatch($scan->id);
        } catch (\Throwable $e) {
            Cache::forget(LeadScanRunner::lockKey($lead->id));
            throw $e;
        }

        return ApiResponse::success(
            $scan->fresh()->load('candidates')->toPanel(),
            'Scan started',
            202
        );
    }

    public function cancel(Lead $lead, LeadIntelligenceScan $scan): JsonResponse
    {
        $denied = $this->deny($lead);
        if ($denied) {
            return $denied;
        }
        if ((int) $scan->lead_id !== (int) $lead->id) {
            return ApiResponse::error('Scan not found', 404);
        }
        if ($scan->isActive()) {
            $scan->forceFill([
                'status' => 'cancelled',
                'progress' => 100,
                'status_message' => 'Scan cancelled.',
                'finished_at' => now(),
            ])->save();
            Cache::forget(LeadScanRunner::lockKey($lead->id));
        }

        return ApiResponse::success($scan->load('candidates')->toPanel(), 'Scan updated');
    }

    private function deny(Lead $lead): ?JsonResponse
    {
        $user = auth()->user();
        if (! $user) {
            return ApiResponse::error('Unauthenticated', 401);
        }
        if (! $user->hasRole('super_admin')) {
            return ApiResponse::error('You are not authorized to scan this lead', 403);
        }
        if (! $user->canViewLead($lead)) {
            return ApiResponse::error('You are not authorized to view this lead', 403);
        }

        return null;
    }

    private function visibleScan(Lead $lead): ?LeadIntelligenceScan
    {
        return LeadIntelligenceScan::query()
            ->where('lead_id', $lead->id)
            ->with('candidates')
            ->latest('id')
            ->first();
    }
}
