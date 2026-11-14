<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\Listing;
use App\Models\Stage;
use App\Models\User;
use App\Support\Brand\BrandIsolation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * User History page: everything one user did, in plain words.
 *
 * Lead and deal changes live in lead_histories (a deal row has deal_id set);
 * listing and other model changes live in activity_log (Spatie). Both are merged
 * into one timeline, paged by date.
 */
class UserHistoryController extends Controller
{
    private const TYPES = ['all', 'lead', 'deal', 'listing', 'other'];

    /** Actions that only record someone opening a record, not changing it. */
    private const VIEW_ACTIONS = ['view', 'open_lead', 'open_deal', 'open_listing'];

    /** Columns that change on every save and say nothing to the reader. */
    private const NOISE_FIELDS = ['updated_at', 'created_at', 'deleted_at', 'from_lead_pool', 'brand_key', 'remember_token', 'password', 'last_login_at', 'last_seen_at'];

    /** *_id fields shown as a name instead of a number. */
    private const USER_FIELDS = ['responsible_person_id', 'added_by', 'created_by', 'updated_by', 'assigned_to', 'agent_id', 'merged_by', 'approved_by', 'rejected_by', 'deleted_by'];
    private const STAGE_FIELDS = ['stage_id', 'stage'];
    private const AREA_FIELDS = ['area_id'];

    private const ACTION_LABELS = [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'assigned' => 'Reassigned',
        'stage_changed' => 'Changed stage',
        'comment_added' => 'Added a comment',
        'comment_updated' => 'Edited a comment',
        'comment_deleted' => 'Deleted a comment',
        'activity_created' => 'Added an activity',
        'activity_updated' => 'Edited an activity',
        'activity_deleted' => 'Deleted an activity',
        'merged' => 'Merged leads',
        'merged_into' => 'Merged into another lead',
        'revert' => 'Reverted a change',
        'view' => 'Viewed',
    ];

    public function filters(): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $users = BrandIsolation::constrain(User::query(), 'users')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['users' => $users]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $request->validate([
            'user_id' => 'required|integer',
            'type' => 'nullable|in:' . implode(',', self::TYPES),
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'include_views' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $user = BrandIsolation::findUserInCurrentBrand($request->input('user_id'));
        abort_unless($user, 404, 'User not found');

        $type = $request->input('type', 'all');
        $includeViews = $request->boolean('include_views');
        [$from, $to] = $this->dateRange($request);

        // Page over ids only, then load the rows on this page.
        $parts = [];
        if (in_array($type, ['all', 'lead', 'deal'], true)) {
            $parts[] = $this->historyIdsQuery($user->id, $type, $from, $to, $includeViews);
        }
        if (in_array($type, ['all', 'listing', 'other'], true)) {
            $parts[] = $this->activityIdsQuery($user, $type, $from, $to);
        }

        $union = array_shift($parts);
        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        $page = DB::query()->fromSub($union, 't')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 25));

        $rows = collect($page->items());
        $historyIds = $rows->where('src', 'h')->pluck('id')->all();
        $activityIds = $rows->where('src', 'a')->pluck('id')->all();

        $histories = LeadHistory::whereIn('id', $historyIds)->get()->keyBy('id');
        $activities = Activity::whereIn('id', $activityIds)->with('subject')->get()->keyBy('id');

        // Deal rows also carry the deal's lead_id, so the client's name loads in the same query.
        $deals = Deal::withoutGlobalScopes()
            ->whereIn('id', $histories->pluck('deal_id')->filter()->unique())
            ->get(['id', 'deal_name', 'lead_id'])
            ->keyBy('id');
        $leadNames = $this->leadNames(
            $histories->pluck('lead_id')->merge($deals->pluck('lead_id'))->filter()->unique()->values()->all()
        );
        $dealNames = $deals->map(function ($d) use ($leadNames) {
            $deal = trim((string) $d->deal_name) !== '' ? $d->deal_name : 'Deal #' . $d->id;
            $client = $d->lead_id ? ($leadNames[$d->lead_id] ?? null) : null;

            return $client ? "{$deal} — {$client}" : $deal;
        });

        $items = $rows->map(function ($row) use ($histories, $activities, $leadNames, $dealNames) {
            return $row->src === 'h'
                ? $this->formatHistory($histories->get($row->id), $leadNames, $dealNames)
                : $this->formatActivity($activities->get($row->id));
        })->filter()->values()->all();

        $items = $this->resolveIdNames($items);

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name],
            'data' => $items,
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    // ---------------------------------------------------------------- queries

    private function historyIdsQuery(int $userId, string $type, ?Carbon $from, ?Carbon $to, bool $includeViews)
    {
        $q = DB::table('lead_histories')
            ->selectRaw("'h' as src, id, created_at")
            ->where('user_id', $userId)
            ->whereNull('deleted_at');

        if ($type === 'lead') {
            $q->whereNull('deal_id');
        } elseif ($type === 'deal') {
            $q->whereNotNull('deal_id');
        }

        if (! $includeViews) {
            $q->where(function ($w) {
                $w->whereNull('changes->action')->orWhereNotIn('changes->action', self::VIEW_ACTIONS);
            });
        }

        return $this->between($q, $from, $to);
    }

    private function activityIdsQuery(User $user, string $type, ?Carbon $from, ?Carbon $to)
    {
        $listing = (new Listing)->getMorphClass();

        $q = DB::table('activity_log')
            ->selectRaw("'a' as src, id, created_at")
            ->where('causer_type', $user->getMorphClass())
            ->where('causer_id', $user->id);

        if ($type === 'listing') {
            $q->where('subject_type', $listing);
        } elseif ($type === 'other') {
            $q->where(fn ($w) => $w->whereNull('subject_type')->orWhere('subject_type', '!=', $listing));
        }

        return $this->between($q, $from, $to);
    }

    private function between($q, ?Carbon $from, ?Carbon $to)
    {
        if ($from) {
            $q->where('created_at', '>=', $from);
        }
        if ($to) {
            $q->where('created_at', '<=', $to);
        }

        return $q;
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function dateRange(Request $request): array
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : null;
        $to = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : null;

        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    // ------------------------------------------------------------- formatting

    private function formatHistory(?LeadHistory $h, $leadNames, $dealNames): ?array
    {
        if (! $h) {
            return null;
        }

        $changes = is_array($h->changes) ? $h->changes : [];
        $action = (string) ($changes['action'] ?? 'updated');
        $isDeal = (bool) $h->deal_id;

        return [
            'key' => 'h-' . $h->id,
            'type' => $isDeal ? 'deal' : 'lead',
            'record_id' => $isDeal ? $h->deal_id : $h->lead_id,
            'record_name' => $isDeal
                ? ($dealNames[$h->deal_id] ?? 'Deal #' . $h->deal_id)
                : ($leadNames[$h->lead_id] ?? 'Lead #' . $h->lead_id),
            'action' => $this->actionLabel($action),
            'action_key' => $action,
            'changes' => $this->historyChanges($changes),
            'created_at' => $h->created_at?->toIso8601String(),
        ];
    }

    /**
     * lead_histories.changes comes in a few shapes:
     *  - {action: updated, fields: {phone: {old, new}}}
     *  - {action: stage_changed, old_stage, new_stage} / {action: assigned, old_person, new_person}
     *  - anything else: extra keys are details (comment text, merge ids…)
     */
    private function historyChanges(array $changes): array
    {
        $out = [];

        // Lead edits use "fields"; deal edits nest them under "changes"
        // ({stage: {old,new}, basic: {field: value}, parties: {...}, properties: 'updated'}).
        foreach (['fields', 'changes'] as $bag) {
            if (! isset($changes[$bag]) || ! is_array($changes[$bag])) {
                continue;
            }
            foreach ($changes[$bag] as $field => $value) {
                if ($this->isNoise($field)) {
                    continue;
                }
                if (is_array($value) && (array_key_exists('old', $value) || array_key_exists('new', $value))) {
                    $out[] = $this->change($field, $value['old'] ?? null, $value['new'] ?? null);
                } elseif ($field === 'basic' && is_array($value)) {
                    foreach ($value as $f => $v) {
                        $out[] = $this->change($f, null, $v);
                    }
                } else {
                    $out[] = $this->change($field, null, $value, true);
                }
            }
        }

        // user_id is the person who did it — already the subject of this page.
        $rest = array_diff_key($changes, array_flip(['action', 'fields', 'changes', 'user_id']));

        // Pair old_X with new_X; prefer the readable one over its *_id twin.
        foreach ($rest as $key => $value) {
            if (! str_starts_with($key, 'old_')) {
                continue;
            }
            $base = substr($key, 4);
            if (! array_key_exists('new_' . $base, $rest)) {
                continue;
            }
            if (str_ends_with($base, '_id') && array_key_exists('old_' . substr($base, 0, -3), $rest)) {
                unset($rest[$key], $rest['new_' . $base]);
                continue;
            }
            $out[] = $this->change($base, $value, $rest['new_' . $base]);
            unset($rest[$key], $rest['new_' . $base]);
        }

        foreach ($rest as $key => $value) {
            // Bare ids (comment_id, old_person_id…) mean nothing to the reader.
            if ($this->isNoise($key) || $this->isInternalId($key)) {
                continue;
            }
            $out[] = $this->change($key, null, $value, true);
        }

        return $out;
    }

    private function formatActivity(?Activity $a): ?array
    {
        if (! $a) {
            return null;
        }

        $props = $a->properties?->toArray() ?? [];
        $new = is_array($props['attributes'] ?? null) ? $props['attributes'] : [];
        $old = is_array($props['old'] ?? null) ? $props['old'] : [];
        $isListing = $a->subject_type === (new Listing)->getMorphClass();
        $model = $a->subject_type ? class_basename($a->subject_type) : 'System';

        $changes = [];
        foreach (array_unique(array_merge(array_keys($new), array_keys($old))) as $field) {
            if ($this->isNoise($field) || $field === 'id') {
                continue;
            }
            $oldVal = $old[$field] ?? null;
            $newVal = $new[$field] ?? null;
            if (json_encode($oldVal) === json_encode($newVal)) {
                continue; // unchanged, or empty on create
            }
            $changes[] = $this->change($field, $oldVal, $newVal);
        }

        $event = (string) ($a->event ?: $a->description ?: 'updated');

        return [
            'key' => 'a-' . $a->id,
            'type' => $isListing ? 'listing' : 'other',
            'model' => $model,
            'record_id' => $a->subject_id,
            'record_name' => $this->subjectName($a, $model),
            'action' => $this->actionLabel($event),
            'action_key' => $event,
            'changes' => $changes,
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }

    private function change(string $field, $old, $new, bool $detail = false): array
    {
        return [
            'field' => $field,
            'label' => $this->fieldLabel($field),
            'old' => $this->plain($old),
            'new' => $this->plain($new),
            'detail' => $detail,
        ];
    }

    private function subjectName(Activity $a, string $model): string
    {
        $s = $a->subject;
        if (! $s) {
            return $model . ' #' . $a->subject_id;
        }
        if ($s instanceof Listing) {
            return $s->reference_number ?: 'Listing #' . $s->id;
        }

        return $s->name ?? $s->title ?? $s->deal_name ?? $s->full_name ?? $s->email ?? ($model . ' #' . $a->subject_id);
    }

    private function leadNames(array $ids)
    {
        if (! $ids) {
            return collect();
        }

        return Lead::withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->get(['id', 'first_name', 'second_name', 'last_name', 'lead_name', 'company_name', 'lead_number'])
            ->mapWithKeys(function ($l) {
                // Bitrix imports often only fill lead_name; placeholders like "-" count as empty.
                $clean = fn ($v) => trim((string) $v, " \t\n\r\0\x0B-.");
                $name = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
                    $clean($l->first_name), $clean($l->second_name), $clean($l->last_name),
                ]))));
                $name = $name !== '' ? $name : ($clean($l->lead_name) ?: $clean($l->company_name));
                $label = $name !== '' ? $name : 'Lead #' . $l->id;

                return [$l->id => $l->lead_number ? "{$label} ({$l->lead_number})" : $label];
            });
    }

    /** Swap user / stage / area ids for their names, in one query per table. */
    private function resolveIdNames(array $items): array
    {
        $maps = ['user' => self::USER_FIELDS, 'stage' => self::STAGE_FIELDS, 'area' => self::AREA_FIELDS];
        $ids = ['user' => [], 'stage' => [], 'area' => []];

        foreach ($items as $item) {
            foreach ($item['changes'] as $c) {
                foreach ($maps as $kind => $fields) {
                    if (in_array($c['field'], $fields, true)) {
                        foreach ([$c['old'], $c['new']] as $v) {
                            if (is_numeric($v)) {
                                $ids[$kind][] = (int) $v;
                            }
                        }
                    }
                }
            }
        }

        $names = [
            'user' => $ids['user'] ? User::whereIn('id', array_unique($ids['user']))->pluck('name', 'id') : collect(),
            'stage' => $ids['stage'] ? Stage::whereIn('id', array_unique($ids['stage']))->pluck('name', 'id') : collect(),
            'area' => $ids['area'] ? Area::whereIn('id', array_unique($ids['area']))->pluck('name', 'id') : collect(),
        ];

        foreach ($items as &$item) {
            foreach ($item['changes'] as &$c) {
                foreach ($maps as $kind => $fields) {
                    if (in_array($c['field'], $fields, true)) {
                        foreach (['old', 'new'] as $side) {
                            if (is_numeric($c[$side]) && isset($names[$kind][(int) $c[$side]])) {
                                $c[$side] = $names[$kind][(int) $c[$side]];
                            }
                        }
                    }
                }
            }
            unset($c);
        }
        unset($item);

        return $items;
    }

    private function isNoise(string $field): bool
    {
        return in_array($field, self::NOISE_FIELDS, true);
    }

    /** An *_id we can't turn into a name. */
    private function isInternalId(string $field): bool
    {
        return (str_ends_with($field, '_id') || str_ends_with($field, '_ids'))
            && ! in_array($field, [...self::USER_FIELDS, ...self::STAGE_FIELDS, ...self::AREA_FIELDS], true);
    }

    private function fieldLabel(string $field): string
    {
        $f = preg_replace('/_id$/', '', $field);

        return Str::of($f)->replace(['_', '.'], ' ')->squish()->ucfirst()->toString();
    }

    private function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? Str::of($action)->replace('_', ' ')->squish()->ucfirst()->toString();
    }

    /** Anything → short readable text (or null when empty). */
    private function plain($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            $flat = array_filter($value, fn ($v) => ! is_array($v));
            if (count($flat) === count($value) && array_is_list($value)) {
                return implode(', ', $value);
            }

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
            try {
                return Carbon::parse($value)->format('Y-m-d H:i');
            } catch (\Throwable) {
                return $value;
            }
        }
        if (is_string($value)) {
            $text = trim(strip_tags($value));

            return $text === '' ? null : $text;
        }

        return $value;
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);
    }
}
