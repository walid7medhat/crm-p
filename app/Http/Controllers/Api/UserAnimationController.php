<?php

namespace App\Http\Controllers\Api;

use App\Events\UserAnimationUpdated;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAnimationState;
use App\Support\UserAnimationCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserAnimationController extends Controller
{
    public function me(): JsonResponse
    {
        $user = Auth::user();
        $state = UserAnimationState::query()->where('user_id', $user->id)->first();

        return ApiResponse::success($this->payloadFor($state));
    }

    public function catalog(): JsonResponse
    {
        $items = array_map(fn (array $item) => [
            'id' => $item['id'],
            'label' => $item['label'],
            'kind' => $item['kind'],
            'src' => $item['src'],
            'motion' => $item['motion'],
        ], UserAnimationCatalog::all());

        return ApiResponse::success($items);
    }

    public function search(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        if ($search === '') {
            return ApiResponse::success([
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0,
            ]);
        }

        $query = User::query()->select(['id', 'name', 'display_name', 'email', 'status']);

        $query->where(function ($inner) use ($search) {
            $inner->where('name', 'like', '%'.$search.'%')
                ->orWhere('display_name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%');
            if (ctype_digit($search)) {
                $inner->orWhere('id', (int) $search);
            }
        });

        $page = $query->orderBy('name')->paginate(10)->withQueryString();

        return ApiResponse::success([
            'data' => collect($page->items())->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->display_name ?: $user->name,
                'email' => $user->email,
                'status' => $user->status,
            ])->values(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $state = UserAnimationState::query()->where('user_id', $user->id)->first();

        return ApiResponse::success($this->adminView($user, $state));
    }

    public function start(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'animation_id' => ['required', 'string', 'max:64'],
        ]);

        $animation = UserAnimationCatalog::find($validated['animation_id']);
        if (! $animation) {
            return ApiResponse::error('Unknown animation.', 422);
        }

        $state = UserAnimationState::query()->firstOrNew(['user_id' => $user->id]);
        $same = $state->exists
            && $state->enabled
            && $state->animation_id === $animation['id'];

        if (! $same) {
            $state->animation_id = $animation['id'];
            $state->enabled = true;
            $state->version = (int) $state->version + 1;
            $state->updated_by = Auth::id();
            $state->save();
            $this->publish($user, $state, $animation);
        }

        Log::info('user animation start accepted', [
            'actor_id' => Auth::id(),
            'target_id' => $user->id,
            'animation_id' => $animation['id'],
            'version' => (int) $state->version,
        ]);

        return ApiResponse::success($this->adminView($user, $state->fresh() ?? $state, true));
    }

    public function stop(User $user): JsonResponse
    {
        $state = UserAnimationState::query()->firstOrNew(['user_id' => $user->id]);

        if ($state->exists && $state->enabled) {
            $state->enabled = false;
            $state->animation_id = null;
            $state->version = (int) $state->version + 1;
            $state->updated_by = Auth::id();
            $state->save();
            $this->publish($user, $state, null);
        }

        Log::info('user animation stop accepted', [
            'actor_id' => Auth::id(),
            'target_id' => $user->id,
            'version' => (int) ($state->version ?? 0),
        ]);

        return ApiResponse::success($this->adminView($user, $state->fresh() ?? $state, true));
    }

    /** @param array{id: string, kind: string, src: ?string}|null $animation */
    private function publish(User $user, UserAnimationState $state, ?array $animation): void
    {
        try {
            broadcast(new UserAnimationUpdated(
                userId: (int) $user->id,
                enabled: (bool) $state->enabled,
                animationId: $state->enabled ? $state->animation_id : null,
                version: (int) $state->version,
                kind: $state->enabled ? ($animation['kind'] ?? 'canvas') : null,
                src: $state->enabled ? ($animation['src'] ?? null) : null,
            ));
        } catch (Throwable $e) {
            Log::warning('user animation broadcast failed', [
                'target_id' => $user->id,
                'version' => (int) $state->version,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function payloadFor(?UserAnimationState $state): array
    {
        $enabled = (bool) ($state?->enabled && $state?->animation_id);
        $animation = $enabled ? UserAnimationCatalog::find((string) $state->animation_id) : null;
        if ($enabled && ! $animation) {
            $enabled = false;
        }

        return [
            'enabled' => $enabled,
            'animation_id' => $enabled ? $animation['id'] : null,
            'version' => (int) ($state->version ?? 0),
            'kind' => $enabled ? $animation['kind'] : null,
            'src' => $enabled ? $animation['src'] : null,
            'motion' => $enabled ? $animation['motion'] : null,
        ];
    }

    private function adminView(User $user, ?UserAnimationState $state, bool $commandAccepted = false): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->display_name ?: $user->name,
                'email' => $user->email,
            ],
            'animation' => $this->payloadFor($state),
            'presence' => $this->presence($user),
            'command_accepted' => $commandAccepted,
            'playback_confirmed' => false,
        ];
    }

    private function presence(User $user): string
    {
        if (app()->environment('testing')) {
            return 'unknown';
        }

        $connection = config('broadcasting.connections.pusher');
        if (
            config('broadcasting.default') !== 'pusher'
            || empty($connection['key'])
            || empty($connection['secret'])
            || empty($connection['app_id'])
        ) {
            return 'unknown';
        }

        try {
            $pusher = new \Pusher\Pusher(
                $connection['key'],
                $connection['secret'],
                $connection['app_id'],
                $connection['options'] ?? []
            );
            $info = $pusher->get_channel_info('private-user.'.$user->id, ['info' => 'subscription_count']);
            $count = is_object($info)
                ? (int) ($info->subscription_count ?? 0)
                : (int) ($info['subscription_count'] ?? 0);

            return $count > 0 ? 'online' : 'offline';
        } catch (Throwable $e) {
            Log::debug('user animation presence unavailable', [
                'target_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return 'unknown';
        }
    }
}
