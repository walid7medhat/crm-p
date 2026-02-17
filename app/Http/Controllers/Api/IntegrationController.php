<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntegrationController extends Controller
{
    private const META_GRAPH_VERSION = 'v17.0';
    private const META_GRAPH_BASE = 'https://graph.facebook.com';

    /**
     * List authenticated user's integrations.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return ApiResponse::error('Unauthorized', 401);
            }
            $integrations = Integration::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn (Integration $i) => [
                    'id' => $i->id,
                    'form_id' => $i->form_id,
                    'form_name' => $i->form_name,
                    'meta_account_id' => $i->meta_account_id,
                    'platform' => $i->platform,
                    'active' => $i->active,
                    'created_at' => $i->created_at?->toIso8601String(),
                ]);

            return ApiResponse::success($integrations, 'Integrations retrieved successfully');
        } catch (QueryException $e) {
            Log::warning('Integration index DB error: ' . $e->getMessage());
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'integrations')) {
                return ApiResponse::error('Integrations table not found. Run: php artisan migrate', 500);
            }
            return ApiResponse::error(config('app.debug') ? $e->getMessage() : 'Database error.', 500);
        } catch (\Throwable $e) {
            Log::error('Integration index error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $message = config('app.debug') ? $e->getMessage() : 'Failed to list integrations.';
            return ApiResponse::error($message, 500);
        }
    }

    /**
     * Fetch Facebook Pages for the current user (so they can pick a Page ID, not User ID).
     * POST body: access_token
     */
    public function fetchMetaPages(Request $request): JsonResponse
    {
        $request->validate([
            'access_token' => 'required|string',
        ]);

        $accessToken = $request->input('access_token');
        $url = self::META_GRAPH_BASE . '/' . self::META_GRAPH_VERSION . '/me/accounts';
        $params = [
            'access_token' => $accessToken,
            'fields' => 'id,name',
        ];

        try {
            $response = Http::timeout(15)->get($url, $params);

            if (!$response->successful()) {
                $body = $response->json() ?? [];
                $metaMessage = $body['error']['message'] ?? $body['error']['error_user_msg'] ?? $response->body();
                $fullMessage = 'Meta API error: ' . (is_string($metaMessage) ? $metaMessage : json_encode($metaMessage));
                Log::warning('Meta API pages error', ['status' => $response->status(), 'body' => $body]);
                return ApiResponse::error($fullMessage, 400);
            }

            $data = $response->json();
            $pages = $data['data'] ?? [];
            $list = array_map(fn ($p) => ['id' => $p['id'] ?? null, 'name' => $p['name'] ?? 'Unnamed'], $pages);

            return ApiResponse::success(['pages' => $list], 'Pages retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Fetch Meta pages error: ' . $e->getMessage());
            return ApiResponse::error('Failed to fetch pages: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Fetch Meta (Facebook) forms for the given account using Access Token.
     * POST body: access_token, meta_account_id, cursor (optional)
     * meta_account_id must be a PAGE id (from /me/accounts), not a User id.
     */
    public function fetchMetaForms(Request $request): JsonResponse
    {
        $request->validate([
            'access_token' => 'required|string',
            'meta_account_id' => 'required|string',
            'cursor' => 'nullable|string',
        ]);

        $accessToken = $request->input('access_token');
        $accountId = $request->input('meta_account_id');
        $cursor = $request->input('cursor');

        $url = self::META_GRAPH_BASE . '/' . self::META_GRAPH_VERSION . '/' . $accountId . '/leadgen_forms';
        $params = [
            'access_token' => $accessToken,
            'fields' => 'id,name,status,leads_count,created_time',
        ];
        if ($cursor) {
            $params['after'] = $cursor;
        }

        try {
            $response = Http::timeout(15)->get($url, $params);

            if (!$response->successful()) {
                $body = $response->json() ?? [];
                $metaMessage = $body['error']['message'] ?? $body['error']['error_user_msg'] ?? $response->body();
                $metaCode = $body['error']['code'] ?? $body['error']['error_subcode'] ?? null;
                $fullMessage = 'Meta API error: ' . (is_string($metaMessage) ? $metaMessage : json_encode($metaMessage));
                Log::warning('Meta API error', ['status' => $response->status(), 'body' => $body]);
                return ApiResponse::error($fullMessage, 400, [
                    'meta_code' => $metaCode,
                    'meta_error_type' => $body['error']['type'] ?? null,
                ]);
            }

            $data = $response->json();
            $forms = $data['data'] ?? [];
            $paging = $data['paging'] ?? [];
            $nextCursor = $paging['cursors']['after'] ?? null;
            $hasNext = isset($paging['next']);

            $list = array_map(function ($form) {
                return [
                    'id' => $form['id'] ?? null,
                    'name' => $form['name'] ?? 'Unnamed',
                    'status' => $form['status'] ?? null,
                    'leads_count' => $form['leads_count'] ?? 0,
                    'created_time' => $form['created_time'] ?? null,
                ];
            }, $forms);

            return ApiResponse::success([
                'forms' => $list,
                'next_cursor' => $nextCursor,
                'has_next' => $hasNext,
            ], 'Forms retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Fetch Meta forms error: ' . $e->getMessage());
            return ApiResponse::error('Failed to fetch Meta forms: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a new integration (connect a form).
     * POST body: form_id, form_name, meta_account_id, access_token, meta_app_id (optional)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'form_id' => 'required|string',
            'form_name' => 'required|string|max:500',
            'meta_account_id' => 'required|string',
            'access_token' => 'required|string',
            'meta_app_id' => 'nullable|string|max:100',
        ]);

        $user = auth()->user();

        try {
            $integration = Integration::create([
                'user_id' => $user->id,
                'form_id' => $request->input('form_id'),
                'form_name' => $request->input('form_name'),
                'meta_account_id' => $request->input('meta_account_id'),
                'access_token' => $request->input('access_token'),
                'meta_app_id' => $request->input('meta_app_id'),
                'platform' => 'meta',
                'active' => true,
            ]);

            return ApiResponse::success([
                'id' => $integration->id,
                'form_id' => $integration->form_id,
                'form_name' => $integration->form_name,
                'meta_account_id' => $integration->meta_account_id,
            ], 'Integration connected successfully');
        } catch (\Throwable $e) {
            Log::error('Store integration error: ' . $e->getMessage());
            return ApiResponse::error('Failed to save integration: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Toggle integration active status.
     */
    public function toggleActive(Integration $integration): JsonResponse
    {
        if ($integration->user_id !== auth()->id()) {
            return ApiResponse::error('Unauthorized', 403);
        }
        $integration->update(['active' => !$integration->active]);
        return ApiResponse::success([
            'id' => $integration->id,
            'active' => $integration->active,
        ], 'Integration updated');
    }

    /**
     * Delete an integration.
     */
    public function destroy(Integration $integration): JsonResponse
    {
        if ($integration->user_id !== auth()->id()) {
            return ApiResponse::error('Unauthorized', 403);
        }
        $integration->delete();
        return ApiResponse::success(null, 'Integration removed');
    }
}
