<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Suggestion;
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
     * List all suggestions (admin/super_admin only). Returns suggestions with sender name and avatar.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        if (!$user->hasRole('super_admin') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $suggestions = Suggestion::with(['user:id,name,avatar'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Suggestion $s) {
                $u = $s->user;
                return [
                    'id' => $s->id,
                    'content' => $s->content,
                    'created_at' => $s->created_at->toIso8601String(),
                    'user' => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'avatar' => $u->avatar ? asset('storage/' . $u->avatar) : null,
                    ],
                ];
            });

        return response()->json(['suggestions' => $suggestions]);
    }
}
