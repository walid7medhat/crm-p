<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Suggestion;
use App\Models\SuggestionReply;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SuggestionController extends Controller
{
    /**
     * Store a new suggestion (any authenticated user/agent).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $suggestion = Suggestion::create([
            'user_id' => Auth::id(),
            'content' => $request->content,
        ]);

        return response()->json([
            'message' => 'Suggestion submitted successfully.',
            'suggestion' => $suggestion->load('user:id,name,avatar'),
        ], 201);
    }

    /**
     * List suggestions the caller is allowed to see.
     * Admin and super_admin see every suggestion. Everyone else sees only their own.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $isManager = $this->canManageSuggestions($user);

        $query = Suggestion::with([
            'user:id,name,avatar',
            'replies' => function ($q) {
                $q->orderBy('created_at')->orderBy('id');
            },
        ])->orderByDesc('created_at');

        if (!$isManager) {
            $query->where('user_id', $user->id);
        }

        $suggestions = $query->get()->map(
            fn (Suggestion $suggestion) => $this->formatSuggestion($suggestion, $isManager)
        );

        return response()->json(['suggestions' => $suggestions]);
    }

    /**
     * Official reply from Admin / Super Admin. The responding user id is stored
     * for audit; clients display the reply as "OIA Properties".
     */
    public function reply(Request $request, Suggestion $suggestion): JsonResponse
    {
        $user = Auth::user();
        if (!$this->canManageSuggestions($user)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $content = trim($validated['content']);
        if ($content === '') {
            return response()->json([
                'message' => 'Reply cannot be empty.',
                'errors' => ['content' => ['Reply cannot be empty.']],
            ], 422);
        }

        $existing = SuggestionReply::query()
            ->where('suggestion_id', $suggestion->id)
            ->where('user_id', $user->id)
            ->where('content', $content)
            ->where('created_at', '>=', now()->subSeconds(15))
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Reply already sent.',
                'reply' => $this->formatReply($existing, true),
            ]);
        }

        $reply = SuggestionReply::create([
            'suggestion_id' => $suggestion->id,
            'user_id' => $user->id,
            'content' => $content,
        ]);

        return response()->json([
            'message' => 'Reply sent.',
            'reply' => $this->formatReply($reply, true),
        ], 201);
    }

    private function canManageSuggestions(?User $user): bool
    {
        return $user && ($user->hasRole('super_admin') || $user->hasRole('admin'));
    }

    private function formatSuggestion(Suggestion $suggestion, bool $includeReplierId): array
    {
        $owner = $suggestion->user;

        return [
            'id' => $suggestion->id,
            'content' => $suggestion->content,
            'created_at' => optional($suggestion->created_at)->toIso8601String(),
            'user' => $owner ? [
                'id' => $owner->id,
                'name' => $owner->name,
                'avatar' => $owner->avatar ? asset('storage/' . $owner->avatar) : null,
            ] : null,
            'replies' => $suggestion->replies->map(
                fn (SuggestionReply $reply) => $this->formatReply($reply, $includeReplierId)
            )->values(),
        ];
    }

    private function formatReply(SuggestionReply $reply, bool $includeReplierId): array
    {
        $payload = [
            'id' => $reply->id,
            'content' => $reply->content,
            'created_at' => optional($reply->created_at)->toIso8601String(),
            'sender' => 'OIA Properties',
        ];

        if ($includeReplierId) {
            $payload['user_id'] = $reply->user_id;
        }

        return $payload;
    }
}
