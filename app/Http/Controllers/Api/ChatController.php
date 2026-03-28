<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    /**
     * Search users available for chat.
     * GET /api/chat/users-search?q=...
     */
    public function usersSearch(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $q = trim((string) $request->input('q', ''));
        $limit = min(max((int) $request->input('limit', 8), 1), 25);

        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $users = User::query()
            ->where('id', '!=', $user->id)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                    ->orWhere('email', 'like', '%' . $q . '%');
            })
            ->select(['id', 'name', 'email', 'avatar'])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'avatar' => $u->avatar_url ?? null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Start or get existing conversation with an agent (optionally for a listing).
     * POST /api/chat/start
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|integer|min:1|exists:users,id',
            'listing_id' => 'nullable|integer|min:1|exists:listings,id',
        ], [
            'agent_id.required' => 'Please select an agent to chat with.',
            'agent_id.exists' => 'The selected agent was not found.',
            'listing_id.exists' => 'The selected listing was not found.',
        ]);

        $user = $request->user();
        $agentId = (int) $validated['agent_id'];
        $listingId = isset($validated['listing_id']) ? (int) $validated['listing_id'] : null;

        if ($agentId === $user->id) {
            return response()->json(['message' => 'Cannot start conversation with yourself.'], 422);
        }

        $query = Conversation::where('type', 'private')
            ->whereHas('users', fn ($q) => $q->where('user_id', $user->id))
            ->whereHas('users', fn ($q) => $q->where('user_id', $agentId));

        if ($listingId) {
            $query->where('listing_id', $listingId);
        } else {
            $query->whereNull('listing_id');
        }

        $conversation = $query
            ->with([
                'users:id,name,email,avatar',
                // Select the minimum columns we need for the property context card.
                'listing' => function ($q) {
                    $q->select(['id', 'reference_number', 'price', 'area_id', 'project_id']);
                },
                'listing.area',
                'listing.project',
            ])
            ->first();

        if (!$conversation) {
            DB::beginTransaction();
            try {
                $conversation = Conversation::create([
                    'type' => 'private',
                    'listing_id' => $listingId,
                ]);
                $conversation->users()->attach([$user->id, $agentId]);
                $conversation->load([
                    'users:id,name,email,avatar',
                    'listing' => function ($q) {
                        $q->select(['id', 'reference_number', 'price', 'area_id', 'project_id']);
                    },
                    'listing.area',
                    'listing.project',
                ]);
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                return response()->json(['message' => 'Failed to create conversation.'], 500);
            }
        }

        return response()->json([
            'success' => true,
            'conversation' => $this->formatConversation($conversation, $user),
        ]);
    }

    /**
     * Get total unread message count for the authenticated user.
     * GET /api/chat/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = Message::whereHas('conversation', function ($q) use ($user) {
            $q->whereHas('users', fn ($q2) => $q2->where('user_id', $user->id));
        })
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['success' => true, 'count' => $count]);
    }

    /**
     * Get authenticated user's conversations.
     * GET /api/chat/conversations
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('super_admin') || $user->id==30 || $user->id == 33;

        $query = Conversation::with([
            'users:id,name,email,avatar',
            'listing' => function ($q) {
                $q->select(['id', 'reference_number', 'price', 'area_id', 'project_id']);
            },
            'listing.area',
            'listing.project',
        ])
            ->withCount('messages')
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)]);

        // if (!$isSuperAdmin) {
            $query->whereHas('users', fn ($q) => $q->where('user_id', $user->id));
        // }

        $conversations = $query->orderByDesc('updated_at')->paginate(20);

        $items = $conversations->getCollection()->map(fn ($c) => $this->formatConversation($c, $user));
        $conversations->setCollection($items);

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    /**
     * Get messages for a conversation (paginated).
     * GET /api/chat/messages/{conversation}
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        if ((!$request->user()->hasRole('super_admin') &&  !($user->id == 30 || $user->id == 33)  ) && !$conversation->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $messages = $conversation->messages()
            ->with('sender:id,name,email,avatar')
            ->latest()
            ->paginate(30);

        $messages->getCollection()->transform(function (Message $m) {
            return [
                'id' => $m->id,
                'conversation_id' => $m->conversation_id,
                'sender_id' => $m->sender_id,
                'sender' => $m->sender ? [
                    'id' => $m->sender->id,
                    'name' => $m->sender->name,
                    'avatar' => $m->sender->avatar_url ?? null,
                ] : null,
                'message' => $m->message,
                'read_at' => $m->read_at?->toIso8601String(),
                'created_at' => $m->created_at->toIso8601String(),
                'is_mine' => $m->sender_id === request()->user()?->id,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $messages->items(),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Send a message.
     * POST /api/chat/send
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|integer|exists:conversations,id',
            'message' => 'required|string|max:5000',
        ]);

        $user = $request->user();
        $conversation = Conversation::findOrFail($validated['conversation_id']);

        if ((!$request->user()->hasRole('super_admin') && !($user->id == 30 || $user->id == 33)) && !$conversation->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'message' => $validated['message'],
        ]);
        $message->load('sender:id,name,email,avatar');

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            // continue even if broadcast fails
        }

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'sender_id' => $message->sender_id,
                'sender' => [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'avatar' => $message->sender->avatar_url ?? null,
                ],
                'message' => $message->message,
                'read_at' => null,
                'created_at' => $message->created_at->toIso8601String(),
                'is_mine' => true,
            ],
        ]);
    }

    /**
     * Mark messages in a conversation as read.
     * POST /api/chat/read
     */
    public function read(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|integer|exists:conversations,id',
        ]);

        $user = $request->user();
        $conversation = Conversation::findOrFail($validated['conversation_id']);

        if ((!$request->user()->hasRole('super_admin') && !($user->id == 30 || $user->id == 33))&& !$conversation->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $conversation->users()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Admin: list all conversations (super_admin only).
     * GET /api/chat/admin/conversations
     */
    public function adminConversations(Request $request): JsonResponse
    {
        $user=auth()->user();
        
        if (  !$request->user()->hasRole('super_admin') && !($user->id == 30 || $user->id == 33)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = Conversation::with([
            'users:id,name,email,avatar',
            'listing' => function ($q) {
                $q->select(['id', 'reference_number', 'price', 'area_id', 'project_id']);
            },
            'listing.area',
            'listing.project',
        ])
            ->withCount('messages')
            ->orderByDesc('updated_at');

        if ($request->filled('user_id')) {
            $query->whereHas('users', fn ($q) => $q->where('user_id', $request->user_id));
        }
        if ($request->filled('agent_id')) {
            $query->whereHas('users', fn ($q) => $q->where('user_id', $request->agent_id));
        }
        if ($request->filled('q')) {
            $search = trim($request->q);
            $query->whereHas('users', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $perPage = (int) $request->input('per_page', 20);
        $perPage = min(max($perPage, 1), 500);
        $conversations = $query->paginate($perPage);
        $user = $request->user();
        $items = $conversations->getCollection()->map(fn ($c) => $this->formatConversation($c, $user));
        $conversations->setCollection($items);

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    private function formatConversation(Conversation $conversation, User $currentUser): array
    {
        $other = $conversation->getOtherParticipant($currentUser);
        $lastMessage = $conversation->messages()->with('sender:id,name')->latest()->first();
        if ($lastMessage && !$lastMessage->relationLoaded('sender')) {
            $lastMessage->load('sender:id,name');
        }
        $unreadCount = $conversation->messages()
            ->where('sender_id', '!=', $currentUser->id)
            ->whereNull('read_at')
            ->count();

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'listing_id' => $conversation->listing_id,
            'listing' => $conversation->listing ? [
                'id' => $conversation->listing->id,
                'reference_number' => $conversation->listing->reference_number ?? null,
                'title' => $conversation->listing->area?->name ?? null,
                'price' => $conversation->listing->price,
                'location' => $this->buildListingLocation($conversation->listing),
            ] : null,
            'other_user' => $other ? [
                'id' => $other->id,
                'name' => $other->name,
                'email' => $other->email,
                'avatar' => $other->avatar_url ?? null,
            ] : null,
            'last_message' => $lastMessage ? [
                'id' => $lastMessage->id,
                'message' => Str::limit($this->cleanSystemContextText($lastMessage->message), 60),
                'sender_id' => $lastMessage->sender_id,
                'sender_name' => $lastMessage->sender?->name,
                'is_from_me' => $lastMessage->sender_id === $currentUser->id,
                'created_at' => $lastMessage->created_at->toIso8601String(),
            ] : null,
            'unread_count' => $unreadCount,
            'updated_at' => $conversation->updated_at->toIso8601String(),
        ];
    }

    private function cleanSystemContextText(?string $text): string
    {
        $value = trim((string) $text);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/^\[PropertyContext(?::\d+)?\]\s*/i', '', $value) ?? $value;
        $value = preg_replace('/^\[Property(?::\d+)?\]\s*/i', '', $value) ?? $value;

        return trim($value);
    }

    private function buildListingLocation($listing): ?string
    {
        if (!$listing) return null;

        $projectName = $listing->project?->title ?? $listing->project?->name ?? null;
        $areaTitle = $listing->area?->area_title ?? $listing->area?->title ?? $listing->area?->name ?? null;

        $parts = array_values(array_filter([
            $projectName,
            $areaTitle,
        ]));

        if (empty($parts)) return null;

        return implode(' - ', $parts);
    }
}
