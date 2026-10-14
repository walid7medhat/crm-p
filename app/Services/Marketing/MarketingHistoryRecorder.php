<?php

namespace App\Services\Marketing;

use App\Models\MarketingStage;
use App\Models\MarketingTask;
use App\Models\MarketingTaskHistory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Writes card history. Values are stored as readable text (stage / user names), so old
 * entries still read correctly after a stage or user is renamed or removed.
 */
class MarketingHistoryRecorder
{
    /** field => label */
    private const TRACKED = [
        'title' => 'Title',
        'type' => 'Type',
        'stage_id' => 'Stage',
        'assigned_to' => 'Assigned to',
        'shooting_by' => 'Shooting by',
        'editing_by' => 'Editing by',
        'deliverables' => 'Deliverables',
        'channels' => 'Channels',
        'tov' => 'TOV',
        'reference' => 'Reference',
        'notes' => 'Notes',
        'due_date' => 'Due date',
    ];

    private const USER_FIELDS = ['assigned_to', 'shooting_by', 'editing_by'];

    private const KEY_LABELS = [
        'youtube' => 'YouTube',
        'linkedin' => 'LinkedIn',
        'tiktok' => 'TikTok',
        'instagram_story' => 'Instagram Story',
        'unit_video' => 'Unit Video',
        'sales_story' => 'Sales Story',
    ];

    /** "sales_story" => "Sales Story", "youtube" => "YouTube". */
    public static function humanize(string $key): string
    {
        return self::KEY_LABELS[$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    public static function record(MarketingTask $task, string $action, array $changes = []): MarketingTaskHistory
    {
        $history = MarketingTaskHistory::create([
            'task_id' => $task->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'changes' => $changes ?: null,
            'created_at' => now(),
        ]);

        // Every recorded action also syncs open boards and notifies the people on the card.
        // Deferred until the surrounding DB transaction commits, so nobody is told about a
        // change that was rolled back.
        $snapshot = $task->replicate()->forceFill(['id' => $task->id]);
        \Illuminate\Support\Facades\DB::afterCommit(
            fn () => MarketingNotifier::dispatch($snapshot, $action, $changes)
        );

        return $history;
    }

    /** Snapshot of tracked fields, taken before an update. */
    public static function snapshot(MarketingTask $task): array
    {
        $snap = [];
        foreach (array_keys(self::TRACKED) as $field) {
            $snap[$field] = $task->{$field};
        }
        $snap['due_date'] = $task->due_date?->format('Y-m-d');

        return $snap;
    }

    /** [{field, label, old, new}] for every tracked field that changed. */
    public static function diff(array $before, MarketingTask $after): array
    {
        $afterSnap = self::snapshot($after);
        $changes = [];

        foreach (self::TRACKED as $field => $label) {
            $old = $before[$field] ?? null;
            $new = $afterSnap[$field] ?? null;
            if (self::normalize($old) === self::normalize($new)) {
                continue;
            }
            $changes[] = [
                'field' => $field,
                'label' => $label,
                'old' => self::display($field, $old),
                'new' => self::display($field, $new),
            ];
        }

        return $changes;
    }

    public static function stageChange(?int $fromId, int $toId): array
    {
        return [[
            'field' => 'stage_id',
            'label' => 'Stage',
            'old' => self::display('stage_id', $fromId),
            'new' => self::display('stage_id', $toId),
        ]];
    }

    /** Channels are a {key: bool} map, deliverables a list — compare only what is switched on. */
    private static function enabledList(array $value): array
    {
        $list = array_is_list($value) ? $value : array_keys(array_filter($value));
        sort($list);

        return $list;
    }

    private static function normalize($value): ?string
    {
        if (is_array($value)) {
            $list = self::enabledList($value);
            return $list ? json_encode($list) : null;
        }
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private static function display(string $field, $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }
        if (in_array($field, self::USER_FIELDS, true)) {
            return User::find($value)?->name ?? "#{$value}";
        }
        if ($field === 'stage_id') {
            return MarketingStage::withTrashed()->find($value)?->name ?? "#{$value}";
        }
        if (is_array($value)) {
            $labels = array_map(fn ($v) => self::humanize($v), self::enabledList($value));
            return $labels ? implode(', ', $labels) : null;
        }
        if ($field === 'type') {
            return ucfirst($value);
        }

        return (string) $value;
    }
}
