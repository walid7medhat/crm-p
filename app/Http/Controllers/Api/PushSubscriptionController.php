<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\WebPush\LeadAssignmentWebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function config(LeadAssignmentWebPushSender $sender): JsonResponse
    {
        $user = auth()->user();
        $eligible = $sender->isTestRecipient((int) $user->id);
        $publicKey = (string) config('services.web_push.public_key');

        return ApiResponse::success([
            'eligible' => $eligible,
            'configured' => $publicKey !== '' && (string) config('services.web_push.private_key') !== '',
            'public_key' => $eligible ? ($publicKey !== '' ? $publicKey : null) : null,
            'subscribed' => PushSubscription::query()->where('user_id', $user->id)->exists(),
        ]);
    }

    public function store(Request $request, LeadAssignmentWebPushSender $sender): JsonResponse
    {
        $user = auth()->user();
        if (! $sender->isTestRecipient((int) $user->id)) {
            return ApiResponse::error('Mobile notifications are not enabled for this account', 403);
        }

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:32'],
        ]);

        $endpoint = $data['endpoint'];
        $hash = hash('sha256', $endpoint);
        $existing = PushSubscription::query()->where('endpoint_hash', $hash)->first();
        if ($existing && (int) $existing->user_id !== (int) $user->id) {
            return ApiResponse::error('This device is already registered to another account', 403);
        }

        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => $hash],
            [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 255) ?: null,
            ]
        );

        return ApiResponse::success([
            'subscribed' => true,
        ], 'Mobile notifications enabled');
    }
}
