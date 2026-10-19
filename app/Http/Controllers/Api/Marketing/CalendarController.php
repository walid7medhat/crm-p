<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Models\MarketingCalendarPost;
use App\Models\MarketingTask;
use App\Models\MarketingTaskLink;
use App\Services\Marketing\MarketingHistoryRecorder;
use App\Services\Marketing\MarketingPresenter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Services\Marketing\MarketingLinkClassifier;
use Illuminate\Support\Facades\DB;
/** Posting calendar: what goes out on which platform on which day, and whether it was posted. */
class CalendarController extends MarketingBaseController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:200'],
            'platform' => ['nullable', Rule::in(MarketingCalendarPost::PLATFORMS)],
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from']) : now()->startOfWeek();
        $to = isset($validated['to']) ? Carbon::parse($validated['to']) : $from->copy()->addDays(13);
        // Keep responses bounded.
        if ($from->diffInDays($to) > 92) {
            $to = $from->copy()->addDays(92);
        }

        $query = MarketingCalendarPost::with(['link', 'poster:id,name', 'task:id,category'])
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('scheduled_date')->orderBy('id');

        if ($search = trim($validated['search'] ?? '')) {
            $query->where('title', 'like', '%' . $search . '%');
        }
        if ($platform = $validated['platform'] ?? null) {
            $query->where('platform', $platform);
        }

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'posts' => $query->get()->map(fn ($p) => MarketingPresenter::post($p))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
        {
            $validated = $request->validate([
                'task_id' => ['nullable', 'integer', 'exists:marketing_tasks,id'],
                'link_id' => ['nullable', 'integer', 'exists:marketing_task_links,id'],
                'link_url' => ['nullable', 'url', 'max:2048'],
                'link_label' => ['nullable', 'string', 'max:255'],
                'title' => ['nullable', 'string', 'max:255'],
                'platforms' => ['required', 'array', 'min:1'],
                'platforms.*' => [Rule::in(MarketingCalendarPost::PLATFORMS)],
                'scheduled_date' => ['required', 'date'],
            ]);

            $task = isset($validated['task_id']) ? MarketingTask::find($validated['task_id']) : null;
            $link = isset($validated['link_id']) ? MarketingTaskLink::find($validated['link_id']) : null;
            $linkUrl = trim($validated['link_url'] ?? '') ?: null;
            $linkLabel = trim($validated['link_label'] ?? '') ?: null;

            if ($link && (!$task || $link->task_id !== $task->id)) {
                return response()->json(['message' => 'That link does not belong to this card.'], 422);
            }

            $posts = DB::transaction(function () use ($validated, $task, &$link, $linkUrl, $linkLabel) {
                // A newly typed link: with a card it is saved on the card (and shows in its Links tab);
                // without a card it is kept on the post itself.
                if ($linkUrl && $task) {
                    $label = $linkLabel ?: MarketingLinkClassifier::defaultLabel($linkUrl);
                    $link = $task->links()->create([
                        'url' => $linkUrl,
                        'label' => $label,
                        'kind' => MarketingLinkClassifier::kind($linkUrl, $label),
                        'added_by' => Auth::id(),
                    ]);
                }

                $title = trim($validated['title'] ?? '') ?: ($link?->label ?? $linkLabel ?? $task?->title);
                if (!$title) {
                    abort(response()->json(['message' => 'Add a title or pick a card.', 'errors' => ['title' => ['Title is required.']]], 422));
                }

                return collect(array_unique($validated['platforms']))->map(fn ($platform) => MarketingCalendarPost::create([
                    'task_id' => $task?->id,
                    'link_id' => $link?->id,
                    'link_url' => ($linkUrl && !$task) ? $linkUrl : null,
                    'link_label' => ($linkUrl && !$task) ? $linkLabel : null,
                    'title' => $title,
                    'platform' => $platform,
                    'scheduled_date' => $validated['scheduled_date'],
                    'created_by' => Auth::id(),
                ]));
            });

            if ($task) {
                MarketingHistoryRecorder::record($task, 'scheduled', [[
                    'field' => 'calendar',
                    'label' => 'Scheduled',
                    'old' => null,
                    'new' => Carbon::parse($validated['scheduled_date'])->format('d M Y') . ' · '
                        . implode(', ', array_map(fn ($p) => MarketingHistoryRecorder::humanize($p), $validated['platforms'])),
                ]]);
            }

            return response()->json([
                'posts' => $posts->map(fn ($p) => MarketingPresenter::post($p->load(['link', 'task:id,category'])))->values(),
            ], 201);
        }

    public function update(Request $request, MarketingCalendarPost $post): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'platform' => ['sometimes', Rule::in(MarketingCalendarPost::PLATFORMS)],
            'scheduled_date' => ['sometimes', 'date'],
        ]);

        $post->update($validated);

        return response()->json(['post' => MarketingPresenter::post($post->fresh(['link', 'poster:id,name', 'task:id,category']))]);
    }

    public function togglePosted(Request $request, MarketingCalendarPost $post): JsonResponse
    {
        $validated = $request->validate(['is_posted' => ['required', 'boolean']]);

        $post->update([
            'is_posted' => $validated['is_posted'],
            'posted_by' => $validated['is_posted'] ? Auth::id() : null,
            'posted_at' => $validated['is_posted'] ? now() : null,
        ]);

        if ($post->task) {
            MarketingHistoryRecorder::record($post->task, $validated['is_posted'] ? 'posted' : 'unposted', [[
                'field' => 'calendar',
                'label' => MarketingHistoryRecorder::humanize($post->platform),
                'old' => null,
                'new' => $post->title,
            ]]);
        }

        return response()->json(['post' => MarketingPresenter::post($post->fresh(['link', 'poster:id,name', 'task:id,category']))]);
    }

    /** Creator of the post or an admin. */
    public function destroy(MarketingCalendarPost $post): JsonResponse
    {
        if ($post->created_by !== Auth::id() && !$this->isAdmin()) {
            return response()->json(['message' => 'Only the person who scheduled this post or an admin can remove it.'], 403);
        }

        $post->delete();

        return response()->json(['message' => 'Post removed.']);
    }
}
