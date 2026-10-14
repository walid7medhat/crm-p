<?php

namespace App\Services\MyDay;

use App\Models\InternalUpdate;
use App\Models\Lead;
use App\Models\LeadHistory;
use App\Models\Listing;
use App\Models\ListingComment;
use App\Models\MarketingCalendarPost;
use App\Models\MarketingTask;
use App\Models\MarketingTaskHistory;
use App\Services\Marketing\MarketingHistoryRecorder;
use App\Models\Stage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Personal actions for the Daily Message card.
 * A row counts only when the signed-in user is the one who did it.
 */
class MyDayService
{
    public const TIMEZONE = 'Asia/Dubai';

    private const CLUSTER_SECONDS = 2;

    /** Stage moves of one marketing card within this window show as a single move. */
    private const MARKETING_MOVE_MERGE_SECONDS = 300;

    /** @var list<string> */
    private const LISTING_NOISE = [
        'created_at', 'updated_at', 'floor_plans_source', 'assigned_at', 'agent_id',
    ];

    /** @var array<int, string> */
    private array $stageNames = [];

    /** @var array<int, int|null> */
    private array $assignmentCutoff = [];

    /** @var list<int>|null */
    private ?array $listingTeamIds = null;

    public function forUser(User $user): array
    {
        $now = Carbon::now(self::TIMEZONE);
        $start = $now->copy()->startOfDay();
        $end = $now->copy()->endOfDay();
        $this->stageNames = Stage::query()->pluck('name', 'id')->all();
        $this->assignmentCutoff = [];
        $this->listingTeamIds = null;

        $rows = LeadHistory::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$start, $end])
            ->with([
                'lead:id,lead_name,stage_id,responsible_person_id,converted_to_deal_id,converted_at',
                'deal:id,deal_name,stage_id,responsible_person_id,lead_id',
                'deal.lead:id,lead_name,stage_id,responsible_person_id,converted_to_deal_id,converted_at',
            ])
            ->orderBy('id')
            ->limit(80)
            ->get()
            ->filter(fn (LeadHistory $row) => $this->allowed($user, $row));

        $convertedDealIds = $this->convertedDealIds($user, $start, $end);

        $actions = $this->toActions($rows, $user, $convertedDealIds)
            ->concat($this->listingActions($user, $start, $end))
            ->concat($this->marketingActions($user, $start, $end))
            ->sortByDesc('sort_at')
            ->take(20)
            ->map(function (array $action) {
                unset($action['sort_at']);

                return $action;
            })
            ->values()
            ->all();

        return [
            'date' => $start->toDateString(),
            'timezone' => self::TIMEZONE,
            'actions' => $actions,
        ];
    }

    /**
     * @param  array<int, int>  $convertedDealIds
     */
    private function toActions(Collection $rows, User $user, array $convertedDealIds): Collection
    {
        $consumed = [];
        $actions = collect();
        $groups = $rows->groupBy(fn (LeadHistory $row) => $row->deal_id ? 'deal:'.$row->deal_id : 'lead:'.$row->lead_id);

        foreach ($groups as $group) {
            $ordered = $group->sortBy('id')->values();
            foreach ($ordered as $row) {
                if (isset($consumed[$row->id])) {
                    continue;
                }
                $action = $row->changes['action'] ?? null;
                if ($action !== 'stage_changed') {
                    continue;
                }
                foreach ($ordered as $other) {
                    if ($other->id === $row->id || isset($consumed[$other->id])) {
                        continue;
                    }
                    if (abs($other->created_at->diffInSeconds($row->created_at)) > self::CLUSTER_SECONDS) {
                        continue;
                    }
                    $otherAction = $other->changes['action'] ?? null;
                    if (in_array($otherAction, ['updated', 'activity_created', 'assigned'], true)) {
                        $consumed[$other->id] = true;
                    }
                }
                $consumed[$row->id] = true;
                $actions->push($this->card(
                    $row,
                    'Stage',
                    'Moved from '.$this->stageLabel($row->changes['old_stage'] ?? null).' to '.$this->stageLabel($row->changes['new_stage'] ?? null)
                ));
            }

            foreach ($ordered as $row) {
                if (isset($consumed[$row->id])) {
                    continue;
                }
                $card = $this->cardFor($row, $convertedDealIds);
                if ($card === null) {
                    continue;
                }
                $actions->push($card);
            }
        }

        return $actions;
    }

    /**
     * @param  array<int, int>  $convertedDealIds
     * @return array<string, mixed>|null
     */
    private function cardFor(LeadHistory $row, array $convertedDealIds): ?array
    {
        $action = $row->changes['action'] ?? null;

        if ($action === 'created' && $row->deal_id && in_array((int) $row->deal_id, $convertedDealIds, true)) {
            return $this->card($row, 'Deal', 'You converted this lead into a deal');
        }

        if ($action === 'created' && $row->deal_id) {
            return $this->card($row, 'Deal', 'You created this deal');
        }

        if ($action === 'activity_created') {
            return $this->card($row, 'Follow-up', 'You scheduled a follow-up');
        }

        if ($action === 'comment_added') {
            return $this->card($row, 'Comment', 'You added a comment');
        }

        if ($action === 'stage_changed') {
            return $this->card(
                $row,
                'Stage',
                'Moved from '.$this->stageLabel($row->changes['old_stage'] ?? null).' to '.$this->stageLabel($row->changes['new_stage'] ?? null)
            );
        }

        if ($action === 'updated' && $this->updatedIsMeaningful($row->changes)) {
            return $this->card($row, 'Update', $this->updateLabel($row->changes, $this->recordName($row)));
        }

        return null;
    }

    private function card(LeadHistory $row, string $kind, string $detail): array
    {
        $title = $this->recordName($row);
        $leadId = $row->lead_id ? (int) $row->lead_id : ($row->deal?->lead_id ? (int) $row->deal->lead_id : null);

        return [
            'id' => 'history-'.$row->id,
            'label' => $kind === 'Stage'
                ? 'You moved '.$title.' from '.Str::after($detail, 'Moved from ')
                : (str_contains($detail, $title) ? $detail : $detail.' on '.$title),
            'title' => $title,
            'detail' => $detail,
            'kind' => $kind,
            'time' => $row->created_at?->timezone(self::TIMEZONE)->format('g:i A'),
            'lead_id' => $leadId,
            'deal_id' => $row->deal_id ? (int) $row->deal_id : null,
            'listing_id' => null,
            'marketing_task_id' => null,
            'marketing_category' => null,
            'sort_at' => $row->created_at?->getTimestamp() ?? 0,
        ];
    }

    /**
     * Marketing workspace: today's card history written by this user (create, edit, stage move,
     * link, approval, comment, schedule, posted) plus free calendar posts they marked as posted.
     * Shown only to users who can open the Marketing workspace.
     */
    private function marketingActions(User $user, Carbon $start, Carbon $end): Collection
    {
        if (! $this->canSeeMarketing($user)) {
            return collect();
        }

        $histories = MarketingTaskHistory::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$start, $end])
            ->with(['task' => fn ($q) => $q->withTrashed()->select('id', 'title', 'category')])
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        // Rows arrive newest first. Several edits to the same card in a row collapse into the
        // latest one, and back-to-back stage moves of a card (dragging it around) merge into a
        // single "moved from <first stage> to <last stage>".
        $seen = [];
        $moveGroup = [];   // task_id => ['index' => position in $actions, 'at' => oldest timestamp, 'new' => final stage]
        $actions = [];
        foreach ($histories as $history) {
            $task = $history->task;
            if (! $task) {
                continue;
            }
            $ts = $history->created_at->getTimestamp();

            if ($history->action === 'moved') {
                $change = collect($history->changes ?? [])->firstWhere('field', 'stage_id') ?? [];
                $group = $moveGroup[$task->id] ?? null;
                if ($group && $group['at'] - $ts <= self::MARKETING_MOVE_MERGE_SECONDS) {
                    // Older move in the same burst: extend the merged card's starting stage.
                    $old = $change['old'] ?? 'another stage';
                    $actions[$group['index']] = $this->marketingCard(
                        $actions[$group['index']]['id'],
                        $task->title,
                        $old === $group['new'] ? null : 'Moved from '.$old.' to '.$group['new'],
                        $actions[$group['index']]['_at'],
                        (int) $task->id,
                        $task->category
                    );
                    $moveGroup[$task->id]['at'] = $ts;
                    continue;
                }
                $moveGroup[$task->id] = ['index' => count($actions), 'at' => $ts, 'new' => $change['new'] ?? 'another stage'];
            } else {
                $bucket = $history->task_id.'|'.$history->action.'|'.intdiv($ts, 120);
                if (in_array($history->action, ['updated', 'link_added', 'commented'], true) && isset($seen[$bucket])) {
                    continue;
                }
                $seen[$bucket] = true;
            }

            $actions[] = $this->marketingCard(
                'marketing-history-'.$history->id,
                $task->title,
                $this->marketingDetail($history, $task),
                $history->created_at,
                (int) $task->id,
                $task->category
            );
        }

        // A burst that ended where it started (e.g. dragged away and back) is dropped.
        $actions = collect($actions)->filter(fn ($a) => $a['detail'] !== null);

        // Calendar posts not tied to a card have no card history — pick them up directly.
        $posts = MarketingCalendarPost::query()
            ->whereNull('task_id')
            ->where('posted_by', $user->id)
            ->whereBetween('posted_at', [$start, $end])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        foreach ($posts as $post) {
            $actions->push($this->marketingCard(
                'marketing-post-'.$post->id,
                $post->title,
                'You marked it posted on '.MarketingHistoryRecorder::humanize($post->platform),
                $post->posted_at,
                null,
                'calendar'
            ));
        }

        return $actions->map(function ($a) {
            unset($a['_at']);

            return $a;
        })->values();
    }

    private function marketingDetail(MarketingTaskHistory $history, MarketingTask $task): ?string
    {
        $changes = collect($history->changes ?? []);
        $first = $changes->first() ?? [];

        return match ($history->action) {
            'created' => 'You created this '.$task->category.' card',
            'moved' => 'Moved from '.($first['old'] ?? 'another stage').' to '.($first['new'] ?? 'another stage'),
            'updated' => $changes->isEmpty() ? null : 'You updated '.Str::lower($changes->pluck('label')->take(3)->implode(', ')),
            'link_added' => 'You added a link'.(! empty($first['new']) ? ' ('.$first['new'].')' : ''),
            'link_removed' => 'You removed a link',
            'approved' => 'You approved this design',
            'unapproved' => 'You removed the approval',
            'commented' => 'You added a comment',
            'scheduled' => 'You scheduled it for '.($first['new'] ?? 'the calendar'),
            'posted' => 'You marked it posted on '.($first['label'] ?? 'a platform'),
            'unposted' => 'You unmarked a post on '.($first['label'] ?? 'a platform'),
            'deleted' => 'You deleted this card',
            default => null,
        };
    }

    private function marketingCard(string $id, string $title, ?string $detail, ?Carbon $at, ?int $taskId, string $category): array
    {
        $kind = match ($category) {
            'production' => 'Production',
            'design' => 'Design',
            'content' => 'Content',
            default => 'Calendar',
        };

        $quoted = '“'.$title.'”';
        $label = match (true) {
            $detail === null => '',
            str_starts_with($detail, 'Moved from ') => 'You moved '.$quoted.' from '.Str::after($detail, 'Moved from '),
            // "You created this design card" → "You created design card “X”"
            str_contains($detail, 'this ') => Str::replaceFirst('this ', '', $detail).' '.$quoted,
            default => $detail.' on '.$quoted,
        };

        return [
            'id' => $id,
            '_at' => $at,
            'label' => $label,
            'title' => $title,
            'detail' => $detail,
            'kind' => $kind,
            'time' => $at?->timezone(self::TIMEZONE)->format('g:i A'),
            'lead_id' => null,
            'deal_id' => null,
            'listing_id' => null,
            'marketing_task_id' => $taskId,
            'marketing_category' => in_array($category, ['production', 'design', 'content'], true) ? $category : null,
            'sort_at' => $at?->getTimestamp() ?? 0,
        ];
    }

    private function canSeeMarketing(User $user): bool
    {
        if ($user->hasAnyRole(['super_admin'])) {
            return true;
        }
        try {
            return $user->hasPermissionTo('marketing');
        } catch (\Throwable) {
            // Permission not created yet on this install.
            return false;
        }
    }

    private function listingActions(User $user, Carbon $start, Carbon $end): Collection
    {
        $activities = Activity::query()
            ->where('log_name', 'listing')
            ->where('causer_id', $user->id)
            ->where('causer_type', $user->getMorphClass())
            ->where('subject_type', (new Listing)->getMorphClass())
            ->whereBetween('created_at', [$start, $end])
            ->with('subject')
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        $actions = $activities->map(function (Activity $activity) use ($user) {
            $listing = $activity->subject instanceof Listing ? $activity->subject : null;
            if ($listing && ! $this->canSeeListing($user, $listing)) {
                return null;
            }

            $properties = $activity->properties instanceof Collection
                ? $activity->properties->toArray()
                : (array) ($activity->properties ?? []);
            $attributes = is_array($properties['attributes'] ?? null) ? $properties['attributes'] : [];
            $detail = $this->listingDetail((string) ($activity->event ?: $activity->description), $attributes);
            if ($detail === null) {
                return null;
            }

            return $this->listingCard(
                'listing-activity-'.$activity->id,
                'Listing',
                $this->listingName($listing),
                $detail,
                $activity->created_at,
                $listing?->id
            );
        })->filter();

        $comments = ListingComment::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$start, $end])
            ->with('listing')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        foreach ($comments as $comment) {
            $listing = $comment->listing;
            if (! $listing || ! $this->canSeeListing($user, $listing)) {
                continue;
            }
            $actions->push($this->listingCard(
                'listing-comment-'.$comment->id,
                'Comment',
                $this->listingName($listing),
                'You added a comment',
                $comment->created_at,
                $listing->id
            ));
        }

        $notes = InternalUpdate::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$start, $end])
            ->with('listing')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        foreach ($notes as $note) {
            $listing = $note->listing;
            if (! $listing || ! $this->canSeeListing($user, $listing)) {
                continue;
            }
            $actions->push($this->listingCard(
                'listing-note-'.$note->id,
                'Note',
                $this->listingName($listing),
                'You added an internal note',
                $note->created_at,
                $listing->id
            ));
        }

        return $actions->filter()->values();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function listingDetail(string $event, array $attributes): ?string
    {
        if ($event === 'created') {
            return 'You created this listing';
        }
        if ($event === 'deleted') {
            return 'You deleted this listing';
        }
        if ($event !== 'updated') {
            return null;
        }

        $keys = array_values(array_diff(array_keys($attributes), self::LISTING_NOISE));
        if ($keys === []) {
            return null;
        }
        if ($keys === ['is_archived']) {
            return ! empty($attributes['is_archived']) ? 'You archived this listing' : 'You restored this listing';
        }
        if ($keys === ['approved']) {
            return ! empty($attributes['approved']) ? 'You approved this listing' : 'You rejected this listing';
        }
        if ($keys === ['price']) {
            return 'You updated the price';
        }
        if ($keys === ['is_active']) {
            return ! empty($attributes['is_active']) ? 'You activated this listing' : 'You deactivated this listing';
        }

        return 'You updated this listing';
    }

    private function listingName(?Listing $listing): string
    {
        if (! $listing) {
            return 'a listing';
        }
        $unit = trim((string) ($listing->unit_number ?? ''));
        if ($unit !== '') {
            return 'Unit '.$unit;
        }
        $reference = trim((string) ($listing->reference_number ?? ''));

        return $reference !== '' ? $reference : 'a listing';
    }

    private function listingCard(string $id, string $kind, string $title, string $detail, ?Carbon $at, ?int $listingId): array
    {
        return [
            'id' => $id,
            'label' => str_contains($detail, $title) ? $detail : $detail.' on '.$title,
            'title' => $title,
            'detail' => $detail,
            'kind' => $kind,
            'time' => $at?->timezone(self::TIMEZONE)->format('g:i A'),
            'lead_id' => null,
            'deal_id' => null,
            'listing_id' => $listingId,
            'marketing_task_id' => null,
            'marketing_category' => null,
            'sort_at' => $at?->getTimestamp() ?? 0,
        ];
    }

    private function canSeeListing(User $user, Listing $listing): bool
    {
        $public = in_array($listing->status, ['published', 'converted', 'rented'], true)
            && (bool) $listing->approved;
        if ($public) {
            return true;
        }
        if ((int) $user->id === (int) $listing->agent_id || (int) $user->id === (int) $listing->added_by) {
            return true;
        }
        if ($user->hasAnyRole(['super_admin', 'admin', 'branch_admin'])) {
            return true;
        }
        if ($user->hasRole('manager') && (int) $user->listing_team === 1) {
            return true;
        }
        if ($user->hasRole('team_lead') && $user->is_listing_team) {
            return in_array((int) $listing->agent_id, $this->listingTeamIds($user), true);
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function listingTeamIds(User $user): array
    {
        if ($this->listingTeamIds === null) {
            $this->listingTeamIds = User::query()
                ->where('id', $user->id)
                ->orWhere('parent_id', $user->id)
                ->orWhereHas('parent', function ($query) use ($user) {
                    $query->where('parent_id', $user->id);
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $this->listingTeamIds;
    }

    private function allowed(User $user, LeadHistory $row): bool
    {
        if ((int) $row->user_id !== (int) $user->id) {
            return false;
        }

        $changes = $row->changes ?? [];
        $action = $changes['action'] ?? null;
        if (in_array($action, [null, 'view', 'assigned'], true)) {
            return false;
        }

        $lead = $row->lead ?: $row->deal?->lead;
        if ($row->deal_id) {
            if (! $row->deal || ! $user->canViewDeal($row->deal)) {
                return false;
            }
        } elseif (! $row->lead || ! $user->canViewLead($row->lead)) {
            return false;
        }

        if ($lead && ! empty($changes['from_lead_pool']) && ! $this->seesPoolHistory($user, $lead)) {
            return false;
        }

        if ($lead && $this->usesSalesHistoryWindow($user, $lead)) {
            $cutoff = $this->assignmentCutoff((int) $lead->id);
            if ($cutoff && $row->id <= $cutoff) {
                return false;
            }
        }

        if ($action === 'updated' && ! $this->updatedIsMeaningful($changes)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, int>
     */
    private function convertedDealIds(User $user, Carbon $start, Carbon $end): array
    {
        $ids = LeadHistory::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('deal_id')
            ->where('changes->action', 'created')
            ->pluck('deal_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return [];
        }

        return Lead::query()
            ->whereIn('converted_to_deal_id', $ids)
            ->whereBetween('converted_at', [$start, $end])
            ->pluck('converted_to_deal_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function seesPoolHistory(User $user, Lead $lead): bool
    {
        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        return $user->hasRole('branch_admin')
            && $lead->responsible_person_id
            && in_array((int) $lead->responsible_person_id, $user->getBranchUserIds(), true);
    }

    private function usesSalesHistoryWindow(User $user, Lead $lead): bool
    {
        if ($this->seesPoolHistory($user, $lead)) {
            return false;
        }

        return ! $lead->isManagedBy($user);
    }

    private function assignmentCutoff(int $leadId): ?int
    {
        if (! array_key_exists($leadId, $this->assignmentCutoff)) {
            $id = LeadHistory::query()
                ->where('lead_id', $leadId)
                ->whereNull('deal_id')
                ->where('changes->action', 'assigned')
                ->max('id');
            $this->assignmentCutoff[$leadId] = $id ? (int) $id : null;
        }

        return $this->assignmentCutoff[$leadId];
    }

    private function updatedIsMeaningful(array $changes): bool
    {
        $fields = $changes['fields'] ?? [];
        if (! is_array($fields) || $fields === []) {
            return false;
        }

        return count(array_intersect(array_keys($fields), [
            'why_lost_lead', 'interaction_result', 'extra_client_requirements', 'status_lead', 'lost_reason',
        ])) > 0;
    }

    private function updateLabel(array $changes, string $name): string
    {
        $fields = array_keys($changes['fields'] ?? []);
        if (in_array('why_lost_lead', $fields, true) || in_array('lost_reason', $fields, true)) {
            return 'You marked '.$name.' lost';
        }
        if (in_array('interaction_result', $fields, true)) {
            return 'You recorded a contact result on '.$name;
        }
        if (in_array('extra_client_requirements', $fields, true)) {
            return 'You updated client requirements on '.$name;
        }

        return 'You updated '.$name;
    }

    private function stageLabel(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'another stage';
        }
        if (is_numeric($value) && isset($this->stageNames[(int) $value])) {
            return (string) $this->stageNames[(int) $value];
        }

        return (string) $value;
    }

    private function recordName(LeadHistory $row): string
    {
        if ($row->deal_id && ! $row->lead_id) {
            return $row->deal->deal_name ?? 'a deal';
        }
        if ($row->deal_id && ($row->changes['action'] ?? null) === 'created') {
            return $row->deal->lead->lead_name ?? $row->deal->deal_name ?? 'a deal';
        }

        return $row->lead->lead_name ?? $row->deal?->deal_name ?? 'a record';
    }
}
