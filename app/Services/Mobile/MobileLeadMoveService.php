<?php

namespace App\Services\Mobile;

use App\Helpers\MobileApiResponse;
use App\Http\Controllers\Api\LeadController;
use App\Http\Resources\Mobile\MobileLeadCardResource;
use App\Http\Resources\Mobile\MobileStageSnippetResource;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MobileLeadMoveService
{
    public function __construct(
        protected LeadController $leadController
    ) {}

    public function move(Request $request, Lead $lead): JsonResponse
    {
        $user = auth()->user();
        if (! $user->canViewLead($lead)) {
            return MobileApiResponse::error('You are not authorized to move this lead', 403);
        }

        if (! $user->can('leads-edit')) {
            return MobileApiResponse::error('You do not have permission to move leads', 403);
        }

        $idempotencyKey = $request->header('Idempotency-Key')
            ?? $request->input('idempotency_key');

        if ($idempotencyKey) {
            $cacheKey = 'mobile.v1.move.'.auth()->id().'.'.$lead->id.'.'.hash('sha256', (string) $idempotencyKey);
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return response()->json($cached, 200);
            }
        }

        $lead->refresh();

        if ($request->filled('expected_updated_at')) {
            $expected = Carbon::parse($request->input('expected_updated_at'));
            if ($lead->updated_at) {
                $delta = abs($lead->updated_at->getTimestamp() - $expected->getTimestamp());
                if ($delta > 2) {
                    $fresh = $lead->fresh(['stage', 'responsiblePerson']);

                    return MobileApiResponse::conflict('Lead was modified on the server; refresh and retry', [
                        'lead' => (new MobileLeadCardResource($fresh))->resolve(),
                        'stage' => $fresh->stage ? (new MobileStageSnippetResource($fresh->stage))->resolve() : null,
                    ]);
                }
            }
        }

        $forward = Request::create(
            '/api/leads/'.$lead->id.'/change-stage',
            'POST',
            $request->except(['expected_updated_at', 'idempotency_key'])
        );
        $forward->setUserResolver($request->getUserResolver());
        $forward->setRouteResolver($request->getRouteResolver());

        $response = $this->leadController->changeStage($forward, $lead);

        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $lead->refresh()->load(['stage', 'responsiblePerson']);

        $mobileResponse = MobileApiResponse::success([
            'lead' => (new MobileLeadCardResource($lead))->resolve(),
            'new_stage' => $lead->stage ? (new MobileStageSnippetResource($lead->stage))->resolve() : null,
        ], 'Lead moved successfully');

        if ($idempotencyKey) {
            $cacheKey = 'mobile.v1.move.'.auth()->id().'.'.$lead->id.'.'.hash('sha256', (string) $idempotencyKey);
            $decoded = json_decode($mobileResponse->getContent(), true);
            if (is_array($decoded)) {
                Cache::put($cacheKey, $decoded, (int) config('mobile-api.move_idempotency_ttl', 86400));
            }
        }

        return $mobileResponse;
    }
}
