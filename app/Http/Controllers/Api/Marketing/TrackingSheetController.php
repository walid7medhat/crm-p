<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Models\MarketingTrackingEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TrackingSheetController extends MarketingBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeTracking();

        $validated = $request->validate([
            'category' => ['required', Rule::in(MarketingTrackingEntry::CATEGORIES)],
            'sheet_group' => ['nullable', Rule::in(MarketingTrackingEntry::SHEET_GROUPS)],
            'region' => ['nullable', Rule::in(MarketingTrackingEntry::REGIONS)],
            'search' => ['nullable', 'string', 'max:200'],
            'published' => ['nullable', Rule::in(['1', '0', 'true', 'false'])],
        ]);

        $query = MarketingTrackingEntry::query()
            ->where('category', $validated['category'])
            ->when($validated['sheet_group'] ?? null, fn ($q, $g) => $q->where('sheet_group', $g))
            ->when($validated['region'] ?? null, fn ($q, $r) => $q->where('region', $r))
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($search = trim($validated['search'] ?? '')) {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('area', 'like', $like)
                    ->orWhere('developer', 'like', $like)
                    ->orWhere('done_by', 'like', $like)
                    ->orWhere('content_label', 'like', $like)
                    ->orWhere('page_label', 'like', $like)
                    ->orWhere('content_url', 'like', $like)
                    ->orWhere('page_url', 'like', $like);
            });
        }

        if (array_key_exists('published', $validated) && $validated['published'] !== null) {
            $published = filter_var($validated['published'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_published', $published);
        }

        $entries = $query->get()->map(fn (MarketingTrackingEntry $e) => $this->present($e))->values();

        $groups = MarketingTrackingEntry::query()
            ->where('category', $validated['category'])
            ->selectRaw('sheet_group, count(*) as total')
            ->groupBy('sheet_group')
            ->pluck('total', 'sheet_group');

        return response()->json([
            'entries' => $entries,
            'groups' => $groups,
            'total' => $entries->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeTracking();

        $data = $this->validated($request);
        $maxOrder = (int) MarketingTrackingEntry::where('category', $data['category'])
            ->where('sheet_group', $data['sheet_group'])
            ->max('sort_order');

        $entry = MarketingTrackingEntry::create([
            ...$data,
            'sort_order' => $maxOrder + 1,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['entry' => $this->present($entry)], 201);
    }

    public function update(Request $request, MarketingTrackingEntry $entry): JsonResponse
    {
        $this->authorizeTracking();

        $data = $this->validated($request, $entry);
        $entry->fill($data);
        $entry->updated_by = Auth::id();
        $entry->save();

        return response()->json(['entry' => $this->present($entry->fresh())]);
    }

    public function destroy(MarketingTrackingEntry $entry): JsonResponse
    {
        $this->authorizeTracking();
        $entry->delete();

        return response()->json(['ok' => true]);
    }

    protected function validated(Request $request, ?MarketingTrackingEntry $entry = null): array
    {
        $data = $request->validate([
            'category' => [$entry ? 'sometimes' : 'required', Rule::in(MarketingTrackingEntry::CATEGORIES)],
            'sheet_group' => ['nullable', Rule::in(MarketingTrackingEntry::SHEET_GROUPS)],
            'region' => ['nullable', 'string', Rule::in([...MarketingTrackingEntry::REGIONS, ''])],
            'name' => [$entry ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'area' => ['nullable', 'string', 'max:255'],
            'developer' => ['nullable', 'string', 'max:255'],
            'done_by' => ['nullable', 'string', 'max:255'],
            'is_published' => ['nullable', 'boolean'],
            'content_label' => ['nullable', 'string', 'max:255'],
            'content_url' => ['nullable', 'string', 'max:2048'],
            'page_label' => ['nullable', 'string', 'max:255'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'linkedin_label' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'string', 'max:2048'],
            'instagram_label' => ['nullable', 'string', 'max:255'],
            'instagram_url' => ['nullable', 'string', 'max:2048'],
        ]);

        if (empty($data['sheet_group'])) {
            $category = $data['category'] ?? $entry?->category;
            $data['sheet_group'] = $entry?->sheet_group
                ?: ($category === 'blogs' ? 'blogs' : 'projects');
        }

        if (array_key_exists('region', $data) && $data['region'] === '') {
            $data['region'] = null;
        }

        return $data;
    }

    protected function present(MarketingTrackingEntry $e): array
    {
        return [
            'id' => $e->id,
            'category' => $e->category,
            'sheet_group' => $e->sheet_group,
            'region' => $e->region,
            'name' => $e->name,
            'description' => $e->description,
            'area' => $e->area,
            'developer' => $e->developer,
            'done_by' => $e->done_by,
            'is_published' => $e->is_published,
            'content_label' => $e->content_label,
            'content_url' => $e->content_url,
            'page_label' => $e->page_label,
            'page_url' => $e->page_url,
            'linkedin_label' => $e->linkedin_label,
            'linkedin_url' => $e->linkedin_url,
            'instagram_label' => $e->instagram_label,
            'instagram_url' => $e->instagram_url,
            'sort_order' => $e->sort_order,
            'created_at' => optional($e->created_at)?->toIso8601String(),
            'updated_at' => optional($e->updated_at)?->toIso8601String(),
        ];
    }

    protected function authorizeTracking(): void
    {
        abort_unless(MarketingTrackingEntry::userCanAccess(Auth::user()), 403, 'You do not have access to Tracking Sheets.');
    }
}
