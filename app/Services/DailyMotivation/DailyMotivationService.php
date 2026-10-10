<?php

namespace App\Services\DailyMotivation;

use App\Models\MotivationAssignment;
use App\Models\MotivationMessage;
use App\Models\MotivationSetting;
use App\Models\MotivationUserState;
use App\Models\User;
use App\Notifications\DailyMotivationNotification;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DailyMotivationService
{
    public const SETTINGS_CACHE = 'daily_motivation.settings';

    public const ENABLED_CACHE = 'daily_motivation.enabled_numbers';

    public function timezone(): string
    {
        return (string) config('daily_motivation.timezone', 'Asia/Dubai');
    }

    public function cycleLength(): int
    {
        return (int) config('daily_motivation.cycle_length', MotivationRotation::CYCLE);
    }

    public function today(): Carbon
    {
        return now($this->timezone())->startOfDay();
    }

    public function forgetCaches(): void
    {
        Cache::forget(self::SETTINGS_CACHE);
        Cache::forget(self::ENABLED_CACHE);
    }

    /**
     * @return array{is_enabled: bool, anchor_date: ?string}
     */
    public function cachedSettings(): array
    {
        return Cache::remember(self::SETTINGS_CACHE, 3600, function () {
            $row = $this->ensureSettings();

            return [
                'is_enabled' => (bool) $row->is_enabled,
                'anchor_date' => $row->anchor_date?->toDateString(),
            ];
        });
    }

    public function ensureSettings(): MotivationSetting
    {
        return MotivationSetting::query()->firstOrCreate(
            ['id' => 1],
            ['is_enabled' => false]
        );
    }

    /**
     * @return array<int, int>
     */
    public function enabledNumbers(): array
    {
        return Cache::remember(self::ENABLED_CACHE, 3600, function () {
            return MotivationMessage::query()
                ->where('is_enabled', true)
                ->orderBy('number')
                ->pluck('number')
                ->map(fn ($number) => (int) $number)
                ->all();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function presentFor(User $user): array
    {
        $cached = $this->cachedSettings();
        $today = $this->today();

        if (! $cached['is_enabled']) {
            return $this->emptyPayload('disabled', $today);
        }

        if (! $this->isActiveSales($user)) {
            return $this->emptyPayload('enabled', $today, false);
        }

        if (! $this->released()) {
            return $this->emptyPayload('enabled', $today);
        }

        $settings = $this->ensureSettings();
        if (! $settings->is_enabled || $settings->anchor_date === null) {
            return $this->emptyPayload($settings->is_enabled ? 'enabled' : 'disabled', $today);
        }

        $assignment = $this->ensureAssignment($user, $today, $settings);
        $personal = $this->personalIndex($settings, $today, $assignment ? $this->stateFor($user) : null);
        $canonical = (int) $assignment->message_number;
        $displayNumber = MotivationRotation::resolveDisplay($canonical, $this->enabledNumbers(), $this->cycleLength());
        $message = $displayNumber
            ? MotivationMessage::query()->where('number', $displayNumber)->first()
            : null;

        $position = ($personal % $this->cycleLength()) + 1;

        return [
            'delivery' => 'enabled',
            'eligible' => true,
            'auto_open' => $message !== null && $assignment->dismissed_at === null,
            'dismissed' => $assignment->dismissed_at !== null,
            'assigned_on' => $assignment->assigned_on->toDateString(),
            'date_label' => $today->format('l, j F'),
            'progress' => [
                'position' => $position,
                'cycle_length' => $this->cycleLength(),
            ],
            'message' => $message ? $this->messagePayload($message, $canonical, $position, false) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dismiss(User $user): array
    {
        $cached = $this->cachedSettings();
        if (! $cached['is_enabled'] || ($user->status ?? null) !== 'active') {
            return $this->presentFor($user);
        }

        $today = $this->today();
        $assignment = MotivationAssignment::query()
            ->where('user_id', $user->id)
            ->where('assigned_on', $today->toDateString())
            ->first();

        if ($assignment && $assignment->dismissed_at === null) {
            $assignment->forceFill(['dismissed_at' => now()])->save();
        }

        return $this->presentFor($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(int $number, bool $test = true): array
    {
        $message = MotivationMessage::query()->where('number', $number)->first();
        if (! $message) {
            throw new RuntimeException('Message '.$number.' is not in the collection.');
        }

        $today = $this->today();

        return [
            'delivery' => 'test',
            'eligible' => true,
            'auto_open' => true,
            'dismissed' => false,
            'assigned_on' => $today->toDateString(),
            'date_label' => $today->format('l, j F'),
            'progress' => [
                'position' => $number,
                'cycle_length' => $this->cycleLength(),
            ],
            'message' => $this->messagePayload($message, $number, $number, $test),
        ];
    }

    public function setDelivery(bool $enabled, int $actorId): MotivationSetting
    {
        $settings = $this->ensureSettings();

        if ($enabled) {
            $total = MotivationMessage::query()->count();
            $enabledCount = MotivationMessage::query()->where('is_enabled', true)->count();
            if ($total < $this->cycleLength() || $enabledCount < 1) {
                throw new RuntimeException(
                    'Import all '.$this->cycleLength().' messages, with at least one enabled, before turning Daily Motivation on.'
                );
            }
        }

        $settings->is_enabled = $enabled;
        $settings->updated_by = $actorId;
        if ($enabled && $settings->anchor_date === null) {
            $settings->anchor_date = $this->today()->toDateString();
        }
        $settings->save();
        $this->forgetCaches();

        return $settings->fresh();
    }

    /**
     * One English notification per active user for today. A second run the same
     * day does not send again. Stopped delivery and any time before 9:00 AM Dubai send nothing.
     *
     * @return array{sent: int, status: string}
     */
    public function deliverToday(): array
    {
        $cached = $this->cachedSettings();
        if (! $cached['is_enabled']) {
            return ['sent' => 0, 'status' => 'stopped'];
        }

        $today = $this->today();
        if (! $this->released()) {
            return ['sent' => 0, 'status' => 'waiting'];
        }

        $this->syncMissingActiveOffsets();
        $sent = 0;

        $this->activeSalesQuery()
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$sent, $today) {
                foreach ($users as $user) {
                    if ($this->notifiedToday($user, $today)) {
                        continue;
                    }

                    $payload = $this->presentFor($user);
                    $message = $payload['message'] ?? null;
                    if (! is_array($message) || trim((string) ($message['body_en'] ?? '')) === '') {
                        continue;
                    }

                    $user->notify(new DailyMotivationNotification(
                        (int) $message['number'],
                        (string) $message['body_en'],
                    ));
                    $sent++;
                }
            });

        return ['sent' => $sent, 'status' => 'sent'];
    }

    public function released(): bool
    {
        $sendAt = (string) config('daily_motivation.send_time', '09:00');

        return now($this->timezone())->format('H:i') >= $sendAt;
    }

    private function isActiveSales(User $user): bool
    {
        return ($user->status ?? null) === 'active' && $user->hasRole('sales');
    }

    private function activeSalesQuery()
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'sales')->where('guard_name', 'api');
            });
    }

    private function notifiedToday(User $user, Carbon $today): bool
    {
        return $user->notifications()
            ->where('type', DailyMotivationNotification::class)
            ->where('created_at', '>=', $today->copy()->utc())
            ->where('created_at', '<=', $today->copy()->endOfDay()->utc())
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $settings = $this->ensureSettings();
        $today = $this->today();
        $this->syncMissingActiveOffsets();

        $totalMessages = MotivationMessage::query()->count();
        $enabledMessages = MotivationMessage::query()->where('is_enabled', true)->count();
        $salesPeople = $this->activeSalesQuery()
            ->orderBy('name')
            ->get(['id', 'name']);
        $activeUsers = $salesPeople->count();

        $states = MotivationUserState::query()
            ->join('users', 'users.id', '=', 'motivation_user_states.user_id')
            ->where('users.status', 'active')
            ->get([
                'motivation_user_states.user_id',
                'motivation_user_states.rotation_offset',
                'motivation_user_states.offset_shared',
                'motivation_user_states.cycle_reset_day_index',
            ]);

        $anchor = $this->anchorDay($settings);
        $dayIndex = $anchor ? MotivationRotation::dayIndex($anchor, $today) : 0;

        $counts = [];
        foreach ($states as $state) {
            $personal = MotivationRotation::personalIndex($dayIndex, $state->cycle_reset_day_index);
            $number = MotivationRotation::messageNumber((int) $state->rotation_offset, $personal, $this->cycleLength());
            $counts[$number] = ($counts[$number] ?? 0) + 1;
        }

        $usersOnSharedMessages = 0;
        $sharedNumbers = [];
        foreach ($counts as $number => $count) {
            if ($count > 1) {
                $usersOnSharedMessages += $count;
                $sharedNumbers[] = [
                    'number' => (int) $number,
                    'users' => $count,
                ];
            }
        }

        $missingSlots = max(0, $activeUsers - $states->count());
        $reservedSlots = MotivationUserState::query()->whereNotNull('unique_slot')->count();
        $fullyUnique = $activeUsers > 0
            && $activeUsers <= $this->cycleLength()
            && $usersOnSharedMessages === 0
            && $missingSlots === 0
            && $states->where('offset_shared', true)->count() === 0;

        return [
            'delivery' => $settings->is_enabled ? 'enabled' : 'disabled',
            'is_enabled' => (bool) $settings->is_enabled,
            'anchor_date' => $settings->anchor_date?->toDateString(),
            'date_label' => $today->format('l, j F'),
            'day_index' => $dayIndex,
            'preview_basis' => $settings->anchor_date ? 'today' : 'opening_day',
            'messages' => [
                'total' => $totalMessages,
                'enabled' => $enabledMessages,
                'expected' => $this->cycleLength(),
                'ready' => $totalMessages >= $this->cycleLength() && $enabledMessages > 0,
            ],
            'active_users' => $activeUsers,
            'sales' => $salesPeople->map(fn (User $person) => [
                'id' => $person->id,
                'name' => $person->name,
            ])->values()->all(),
            'slots' => [
                'reserved' => $reservedSlots,
                'available' => max(0, $this->cycleLength() - $reservedSlots),
                'active_with_slot' => $states->count(),
                'active_sharing_slot' => $states->where('offset_shared', true)->count(),
            ],
            'distribution' => [
                'distinct_messages' => count($counts),
                'fully_unique' => $fullyUnique,
                'users_on_shared_messages' => $usersOnSharedMessages,
                'shared_numbers' => $sharedNumbers,
                'note' => $this->distributionNote($activeUsers, $fullyUnique, $usersOnSharedMessages),
            ],
            'rules' => [
                'inactive' => 'Inactive and blocked accounts are not given a new message. Their slot and cycle place stay stored. When the account is active again, they continue from today in that same cycle.',
                'missed_days' => 'A day away does not rewind the cycle. The next visit shows the message for today, and earlier unseen days are not replayed.',
                'over_capacity' => 'When active people outnumber the '.$this->cycleLength().' messages, some colleagues necessarily share a message that day.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function messagesForAdmin(): array
    {
        $today = $this->today()->toDateString();
        $enabled = $this->enabledNumbers();
        $canonicals = MotivationAssignment::query()
            ->where('assigned_on', $today)
            ->pluck('message_number');

        $inUse = [];
        foreach ($canonicals as $number) {
            $number = (int) $number;
            $inUse[$number] = true;
            $display = MotivationRotation::resolveDisplay($number, $enabled, $this->cycleLength());
            if ($display) {
                $inUse[$display] = true;
            }
        }

        return MotivationMessage::query()
            ->orderBy('number')
            ->get()
            ->map(function (MotivationMessage $message) use ($inUse) {
                return [
                    'id' => $message->id,
                    'number' => $message->number,
                    'body_en' => $message->body_en,
                    'body_ar' => $message->body_ar,
                    'subtitle_en' => $message->subtitle_en,
                    'subtitle_ar' => $message->subtitle_ar,
                    'is_enabled' => (bool) $message->is_enabled,
                    'in_use_today' => isset($inUse[$message->number]),
                ];
            })
            ->all();
    }

    public function updateMessage(MotivationMessage $message, array $attributes): MotivationMessage
    {
        $message->fill([
            'body_en' => $attributes['body_en'],
            'body_ar' => $attributes['body_ar'],
            'subtitle_en' => $attributes['subtitle_en'] ?? null,
            'subtitle_ar' => $attributes['subtitle_ar'] ?? null,
            'is_enabled' => array_key_exists('is_enabled', $attributes)
                ? (bool) $attributes['is_enabled']
                : $message->is_enabled,
        ])->save();

        $this->forgetCaches();

        return $message->fresh();
    }

    public function setMessageEnabled(MotivationMessage $message, bool $enabled): MotivationMessage
    {
        $message->forceFill(['is_enabled' => $enabled])->save();
        $this->forgetCaches();

        return $message->fresh();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lookupUsers(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $settings = $this->ensureSettings();
        $today = $this->today();
        $anchor = $this->anchorDay($settings);
        $dayIndex = $anchor ? MotivationRotation::dayIndex($anchor, $today) : 0;

        return User::query()
            ->where(function ($query) use ($like, $term) {
                $query->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
                if (ctype_digit($term)) {
                    $query->orWhere('id', (int) $term);
                }
            })
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email', 'status'])
            ->map(function (User $user) use ($dayIndex) {
                $state = MotivationUserState::query()->where('user_id', $user->id)->first();
                $number = null;
                if ($state) {
                    $personal = MotivationRotation::personalIndex($dayIndex, $state->cycle_reset_day_index);
                    $number = MotivationRotation::messageNumber((int) $state->rotation_offset, $personal, $this->cycleLength());
                }

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status,
                    'has_slot' => $state !== null,
                    'offset_shared' => (bool) ($state->offset_shared ?? false),
                    'message_number' => $number,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function resetUser(User $user): array
    {
        $state = $this->stateFor($user);
        if (! $state && ($user->status ?? null) === 'active') {
            $state = MotivationUserState::query()->where('user_id', $user->id)->first();
        }
        if (! $state) {
            throw new RuntimeException('This person does not have a rotation place yet.');
        }

        $settings = $this->ensureSettings();
        $today = $this->today();
        $anchor = $this->anchorDay($settings);
        $dayIndex = $anchor
            ? MotivationRotation::dayIndex($anchor, $today)
            : 0;

        DB::transaction(function () use ($state, $user, $today, $dayIndex) {
            $locked = MotivationUserState::query()->whereKey($state->id)->lockForUpdate()->first();
            $locked->forceFill(['cycle_reset_day_index' => $dayIndex])->save();
            MotivationAssignment::query()
                ->where('user_id', $user->id)
                ->where('assigned_on', $today->toDateString())
                ->delete();
        });

        $fresh = $state->fresh();
        $personal = MotivationRotation::personalIndex($dayIndex, $fresh->cycle_reset_day_index);
        $number = MotivationRotation::messageNumber((int) $fresh->rotation_offset, $personal, $this->cycleLength());

        $others = 0;
        if (($user->status ?? null) === 'active') {
            $others = MotivationUserState::query()
                ->join('users', 'users.id', '=', 'motivation_user_states.user_id')
                ->where('users.status', 'active')
                ->where('motivation_user_states.user_id', '!=', $user->id)
                ->get(['rotation_offset', 'cycle_reset_day_index'])
                ->filter(function ($row) use ($dayIndex, $number) {
                    $personal = MotivationRotation::personalIndex($dayIndex, $row->cycle_reset_day_index);
                    $theirNumber = MotivationRotation::messageNumber((int) $row->rotation_offset, $personal, $this->cycleLength());

                    return $theirNumber === $number;
                })
                ->count();
        }

        return [
            'user_id' => $user->id,
            'message_number' => $number,
            'shares_today_with' => $others,
        ];
    }

    public function syncMissingActiveOffsets(): int
    {
        $inserted = 0;

        DB::transaction(function () use (&$inserted) {
            $this->ensureSettings();
            MotivationSetting::query()->whereKey(1)->lockForUpdate()->first();

            $activeIds = $this->activeSalesQuery()
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($activeIds === []) {
                return;
            }

            $existing = MotivationUserState::query()
                ->whereIn('user_id', $activeIds)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $have = array_flip($existing);
            $missing = array_values(array_filter($activeIds, fn ($id) => ! isset($have[$id])));
            if ($missing === []) {
                return;
            }

            $taken = MotivationUserState::query()
                ->whereNotNull('unique_slot')
                ->pluck('unique_slot')
                ->map(fn ($slot) => (int) $slot)
                ->all();

            $plan = MotivationRotation::assignSlots($missing, $taken, $this->cycleLength());
            $now = now();

            foreach ($plan as $userId => $choice) {
                try {
                    MotivationUserState::query()->create([
                        'user_id' => $userId,
                        'rotation_offset' => $choice['slot'],
                        'offset_shared' => $choice['shared'],
                        'unique_slot' => $choice['shared'] ? null : $choice['slot'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $inserted++;
                } catch (UniqueConstraintViolationException) {
                    $existingState = MotivationUserState::query()->where('user_id', $userId)->first();
                    if ($existingState) {
                        continue;
                    }
                    MotivationUserState::query()->create([
                        'user_id' => $userId,
                        'rotation_offset' => $choice['slot'],
                        'offset_shared' => true,
                        'unique_slot' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $inserted++;
                }
            }
        });

        return $inserted;
    }

    public function stateFor(User $user): ?MotivationUserState
    {
        $state = MotivationUserState::query()->where('user_id', $user->id)->first();
        if ($state || ($user->status ?? null) !== 'active') {
            return $state;
        }

        $this->syncMissingActiveOffsets();

        return MotivationUserState::query()->where('user_id', $user->id)->first();
    }

    /**
     * @return array<int, string>
     */
    public function workbookCandidates(?string $preferred = null): array
    {
        $name = (string) config('daily_motivation.workbook_name');
        $paths = [];
        if ($preferred) {
            $paths[] = $preferred;
            $paths[] = base_path($preferred);
        }
        $paths[] = base_path($name);
        $paths[] = storage_path('app/'.$name);
        $paths[] = storage_path('app/motivation/'.$name);
        $paths[] = database_path('data/'.$name);

        return array_values(array_unique($paths));
    }

    public function findWorkbook(?string $preferred = null): ?string
    {
        foreach ($this->workbookCandidates($preferred) as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function ensureAssignment(User $user, Carbon $today, MotivationSetting $settings): MotivationAssignment
    {
        $state = $this->stateFor($user);
        if (! $state) {
            throw new RuntimeException('Unable to place this user in the rotation.');
        }

        $date = $today->toDateString();

        return DB::transaction(function () use ($user, $today, $settings, $date) {
            $locked = MotivationUserState::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            $existing = MotivationAssignment::query()
                ->where('user_id', $user->id)
                ->where('assigned_on', $date)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            if (! $locked) {
                throw new RuntimeException('Unable to place this user in the rotation.');
            }

            $anchor = Carbon::parse($settings->anchor_date->toDateString(), $this->timezone())->startOfDay();
            $dayIndex = MotivationRotation::dayIndex($anchor, $today);
            $personal = MotivationRotation::personalIndex($dayIndex, $locked?->cycle_reset_day_index);
            $number = MotivationRotation::messageNumber(
                (int) $locked->rotation_offset,
                $personal,
                $this->cycleLength()
            );

            try {
                return MotivationAssignment::query()->create([
                    'user_id' => $user->id,
                    'assigned_on' => $date,
                    'message_number' => $number,
                ]);
            } catch (UniqueConstraintViolationException) {
                return MotivationAssignment::query()
                    ->where('user_id', $user->id)
                    ->where('assigned_on', $date)
                    ->firstOrFail();
            }
        });
    }

    private function personalIndex(MotivationSetting $settings, Carbon $today, ?MotivationUserState $state): int
    {
        $anchor = $this->anchorDay($settings);
        if (! $anchor || ! $state) {
            return 0;
        }

        $dayIndex = MotivationRotation::dayIndex($anchor, $today);

        return MotivationRotation::personalIndex($dayIndex, $state->cycle_reset_day_index);
    }

    private function anchorDay(MotivationSetting $settings): ?Carbon
    {
        if (! $settings->anchor_date) {
            return null;
        }

        return Carbon::parse($settings->anchor_date->toDateString(), $this->timezone())->startOfDay();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(string $delivery, Carbon $today, bool $eligible = true): array
    {
        return [
            'delivery' => $delivery,
            'eligible' => $delivery === 'enabled' ? $eligible : false,
            'auto_open' => false,
            'dismissed' => false,
            'assigned_on' => null,
            'date_label' => $today->format('l, j F'),
            'progress' => null,
            'message' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(MotivationMessage $message, int $canonical, int $position, bool $test): array
    {
        return [
            'id' => $message->id,
            'number' => (int) $message->number,
            'canonical_number' => $canonical,
            'position' => $position,
            'cycle_length' => $this->cycleLength(),
            'body_en' => $message->body_en,
            'body_ar' => $message->body_ar,
            'subtitle_en' => $message->subtitle_en,
            'subtitle_ar' => $message->subtitle_ar,
            'substituted' => (int) $message->number !== $canonical,
            'test' => $test,
        ];
    }

    private function distributionNote(int $activeUsers, bool $fullyUnique, int $usersOnSharedMessages): string
    {
        $cycle = $this->cycleLength();

        if ($activeUsers === 0) {
            return 'There are no active users to receive a message.';
        }

        if ($activeUsers > $cycle) {
            $extra = $activeUsers - $cycle;

            return $activeUsers.' active users and '.$cycle.' messages. At least '.$extra.' people share a message with someone else today. The cycle still moves forward for each person.';
        }

        if ($fullyUnique) {
            return 'Every active user has a different message today.';
        }

        if ($usersOnSharedMessages > 0) {
            return $usersOnSharedMessages.' active users share today\'s message with someone else. Private slots stay reserved for inactive colleagues so a returning person keeps their place. An explicit reset can also land two people on the same message.';
        }

        return 'Rotation slots are still being prepared for every active user.';
    }
}
