<?php

namespace App\Services\SalesAction;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadComment;
use App\Models\Stage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Rule-based next actions for leads a user is allowed to work.
 *
 * Lead Pool is excluded (stage name, kanban order 9, and the stage id the
 * conversation lock already treats as the pool). Recommendations are computed
 * on read from current rows. Nothing here writes leads, stages, activities,
 * or deals, and it does not read comment text.
 */
class SalesActionCenterService
{
    public const INACTIVE_DAYS = 7;

    public const NO_ANSWER_REVIEW_AT = 2;

    public const DEADLINE_WARNING_HOURS = 24;

    private const PER_PAGE = 20;

    /** @var array<int, int>|null */
    private ?array $poolStageIds = null;

    /** @var Collection<int, Stage>|null */
    private ?Collection $leadStages = null;

    public function center(User $subject, ?string $category = null, ?string $search = null, int $page = 1): array
    {
        $evaluatedAt = now()->toIso8601String();
        $categories = $this->categoryDefinitions();
        $summary = [];

        foreach ($categories as $definition) {
            $summary[] = [
                'key' => $definition['key'],
                'title' => $definition['title'],
                'description' => $definition['description'],
                'count' => $this->categoryQuery($subject, $definition['key'], $search)->count(),
            ];
        }

        $recommendations = [];
        if ($category && $this->definition($category)) {
            $recommendations = $this->recommendationsFor($subject, $category, $search, max(1, $page), $evaluatedAt);
        }

        return [
            'evaluated_at' => $evaluatedAt,
            'timezone' => config('app.timezone'),
            'inactive_days' => self::INACTIVE_DAYS,
            'categories' => $summary,
            'recommendations' => $recommendations,
            'page' => max(1, $page),
            'per_page' => self::PER_PAGE,
        ];
    }

    /**
     * @return array<int, array{key: string, title: string, description: string}>
     */
    private function categoryDefinitions(): array
    {
        $categories = [
            [
                'key' => 'urgent_overdue',
                'title' => 'Urgent / Overdue',
                'description' => 'A pending follow-up reminder is already past due.',
            ],
            [
                'key' => 'assigned_needs_first_contact',
                'title' => 'Assigned — Needs First Contact',
                'description' => 'Assigned, with no saved activity and no recorded call result.',
            ],
            [
                'key' => 'contacted_follow_up_due',
                'title' => 'Contacted — Follow-up Due',
                'description' => 'Contacted, with a pending reminder still due today.',
            ],
            [
                'key' => 'contacted_repeated_no_answer',
                'title' => 'Contacted — Repeated No Answer',
                'description' => 'Contacted, with at least two recorded no-answer results.',
            ],
            [
                'key' => 'no_answer_call_again',
                'title' => 'No Answer — Call Again',
                'description' => 'The last saved call result is no answer, and this is not already the repeated-contact review.',
            ],
            [
                'key' => 'answered_gone_quiet',
                'title' => 'Answered — No Recent Action',
                'description' => 'The client answered, then nobody worked the lead for '.self::INACTIVE_DAYS.' days.',
            ],
            [
                'key' => 'qualified_missing_requirements',
                'title' => 'Qualified — Missing Requirements',
                'description' => 'Qualified, without the area, type, bedrooms, or budget used for matching.',
            ],
            [
                'key' => 'qualified_ready_to_match',
                'title' => 'Qualified — Ready to Match Properties',
                'description' => 'Qualified, and the saved requirement is enough to match listings.',
            ],
            [
                'key' => 'converted_deal_missing',
                'title' => 'Converted — Deal Missing',
                'description' => 'In Converted, with no deal linked from conversion or the deals table.',
            ],
            [
                'key' => 'inactive',
                'title' => 'Inactive Leads',
                'description' => 'No recorded human engagement for '.self::INACTIVE_DAYS.' days.',
            ],
        ];

        if ($this->leadStages()->contains(fn (Stage $stage) => (bool) $stage->auto_revert)) {
            $categories[] = [
                'key' => 'approaching_deadline',
                'title' => 'Approaching a Configured Deadline',
                'description' => 'A stage deadline is inside '.self::DEADLINE_WARNING_HOURS.' hours. This does not revert the lead.',
            ];
        }

        return $categories;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recommendationsFor(User $subject, string $category, ?string $search, int $page, string $evaluatedAt): array
    {
        $query = $this->categoryQuery($subject, $category, $search);
        $this->applySort($query, $category);

        $leads = $query
            ->with([
                'stage:id,name,order,auto_revert,revert_after_hours,status_revert_rules,notification_times',
                'area:id,name',
                'propertyType:id,name',
            ])
            ->forPage($page, self::PER_PAGE)
            ->get();

        $leadIds = $leads->pluck('id')->all();
        $activities = $this->activitiesFor($leadIds, $category);
        $notes = $this->latestNotes($leadIds);

        return $leads->map(function (Lead $lead) use ($category, $activities, $notes, $evaluatedAt) {
            return $this->present(
                $lead,
                $category,
                $activities->get($lead->id),
                $evaluatedAt,
                $notes[$lead->id] ?? null
            );
        })->all();
    }

    private function categoryQuery(User $subject, string $category, ?string $search): Builder
    {
        $query = $this->baseQuery($subject, $search);

        return match ($category) {
            'urgent_overdue' => $this->whereOverdue($query),
            'assigned_needs_first_contact' => $this->whereNeedsFirstContact($this->excludeOverdue($query)),
            'contacted_follow_up_due' => $this->whereDueToday($this->excludeOverdue($query)),
            'contacted_repeated_no_answer' => $this->whereRepeatedNoAnswer($this->excludeDueToday($this->excludeOverdue($query))),
            'no_answer_call_again' => $this->whereNoAnswerCallAgain($this->excludeDueToday($this->excludeOverdue($query))),
            'answered_gone_quiet' => $this->whereAnsweredGoneQuiet($this->excludeDueToday($this->excludeOverdue($query))),
            'qualified_missing_requirements' => $this->whereQualified($this->excludeDueToday($this->excludeOverdue($query)), false),
            'qualified_ready_to_match' => $this->whereQualified($this->excludeDueToday($this->excludeOverdue($query)), true),
            'converted_deal_missing' => $this->whereConvertedMissingDeal($this->excludeDueToday($this->excludeOverdue($query))),
            'inactive' => $this->whereInactive($this->excludeActionableWork($query)),
            'approaching_deadline' => $this->whereApproachingDeadline($this->excludeActionableWork($query)),
            default => $query->whereRaw('1 = 0'),
        };
    }

    private function baseQuery(User $subject, ?string $search): Builder
    {
        $query = Lead::query()->whereNull('leads.archived_at');

        $poolIds = $this->poolStageIds();
        if ($poolIds !== []) {
            $query->where(function (Builder $inner) use ($poolIds) {
                $inner->whereNull('leads.stage_id')
                    ->orWhereNotIn('leads.stage_id', $poolIds);
            });
        }

        $this->applyVisibility($query, $subject);

        $search = trim((string) $search);
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $inner) use ($like) {
                $inner->where('leads.lead_name', 'like', $like)
                    ->orWhere('leads.lead_number', 'like', $like)
                    ->orWhere('leads.work_phone', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * Sales agents see leads they are responsible for. Managers, team leads,
     * and branch admins keep the existing team or branch scope. Super admin
     * (and the two existing full-visibility user ids) see every non-pool lead.
     */
    private function applyVisibility(Builder $query, User $user): void
    {
        if ($user->hasRole('super_admin') || in_array((int) $user->id, [30, 33], true)) {
            return;
        }

        if ($user->hasRole('sales') && ! $user->hasAnyRole(['admin', 'branch_admin', 'manager', 'team_lead'])) {
            $query->where('leads.responsible_person_id', $user->id);

            return;
        }

        $user->applyLeadResponsibleScope($query, $user->leadScopeUserIds());
    }

    private function whereOverdue(Builder $query): Builder
    {
        return $query->whereExists(function ($sub) {
            $this->pendingActivity($sub)->where('la.reminder_date', '<', now());
        });
    }

    private function whereDueToday(Builder $query): Builder
    {
        return $query
            ->whereIn('leads.stage_id', $this->stageIdsForOrders([3]))
            ->whereExists(function ($sub) {
                $this->pendingActivity($sub)
                    ->where('la.reminder_date', '>=', now())
                    ->where('la.reminder_date', '<=', now()->endOfDay());
            });
    }

    private function whereNeedsFirstContact(Builder $query): Builder
    {
        return $query
            ->whereIn('leads.stage_id', $this->stageIdsForOrders([2]))
            ->whereNull('leads.first_contacted_at')
            ->where(function (Builder $inner) {
                $inner->whereNull('leads.interaction_result')
                    ->orWhere('leads.interaction_result', '');
            })
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('lead_activities as la')
                    ->whereColumn('la.lead_id', 'leads.id')
                    ->whereNull('la.deleted_at');
            });
    }

    private function whereRepeatedNoAnswer(Builder $query): Builder
    {
        return $query
            ->whereIn('leads.stage_id', $this->stageIdsForOrders([3]))
            ->where('leads.interaction_result', 'no_answer')
            ->where('leads.no_answer_count', '>=', self::NO_ANSWER_REVIEW_AT);
    }

    private function whereNoAnswerCallAgain(Builder $query): Builder
    {
        $contacted = $this->stageIdsForOrders([3]);

        return $query
            ->whereIn('leads.stage_id', $this->stageIdsForOrders([1, 2, 3, 4, 7]))
            ->where('leads.interaction_result', 'no_answer')
            ->where(function (Builder $inner) use ($contacted) {
                $inner->whereNotIn('leads.stage_id', $contacted)
                    ->orWhere('leads.no_answer_count', '<', self::NO_ANSWER_REVIEW_AT);
            });
    }

    private function whereAnsweredGoneQuiet(Builder $query): Builder
    {
        $cutoff = now()->subDays(self::INACTIVE_DAYS);

        return $query
            ->whereIn('leads.stage_id', $this->stageIdsForOrders([1, 2, 3, 4, 7]))
            ->where('leads.interaction_result', 'answered')
            ->whereRaw('COALESCE(leads.last_engagement_at, leads.last_stage_change_at, leads.created_at) < ?', [$cutoff]);
    }

    private function whereQualified(Builder $query, bool $ready): Builder
    {
        $query->whereIn('leads.stage_id', $this->stageIdsForOrders([4]));
        $this->applyRequirementPresence($query, $ready);

        return $query;
    }

    private function whereConvertedMissingDeal(Builder $query): Builder
    {
        return $query
            ->whereIn('leads.stage_id', $this->stageIdsForOrders([6]))
            ->whereNull('leads.converted_to_deal_id')
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('deals')
                    ->whereColumn('deals.lead_id', 'leads.id')
                    ->whereNull('deals.deleted_at');
            });
    }

    private function whereInactive(Builder $query): Builder
    {
        $cutoff = now()->subDays(self::INACTIVE_DAYS);

        return $query
            ->whereIn('leads.stage_id', $this->stageIdsForOrders([1, 2, 3, 4, 7]))
            ->where(function (Builder $inner) use ($cutoff) {
                $inner->where('leads.last_engagement_at', '<', $cutoff)
                    ->orWhere(function (Builder $missing) use ($cutoff) {
                        $missing->whereNull('leads.last_engagement_at')
                            ->whereRaw('COALESCE(leads.last_stage_change_at, leads.created_at) < ?', [$cutoff]);
                    });
            });
    }

    private function whereApproachingDeadline(Builder $query): Builder
    {
        $stages = $this->leadStages()->filter(function (Stage $stage) {
            return (bool) $stage->auto_revert && (int) $stage->revert_after_hours > 0;
        });

        if ($stages->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $outer) use ($stages) {
            foreach ($stages as $stage) {
                $outer->orWhere(function (Builder $inner) use ($stage) {
                    $inner->where('leads.stage_id', $stage->id)
                        ->where(function (Builder $owner) {
                            $owner->whereNull('leads.added_by')
                                ->orWhereColumn('leads.added_by', '!=', 'leads.responsible_person_id');
                        })
                        ->whereRaw(
                            'DATE_ADD(GREATEST(IFNULL(leads.last_engagement_at, ?), IFNULL(leads.last_stage_change_at, ?)), INTERVAL ? HOUR) BETWEEN ? AND ?',
                            ['1970-01-01 00:00:00', '1970-01-01 00:00:00', (int) $stage->revert_after_hours, now(), now()->addHours(self::DEADLINE_WARNING_HOURS)]
                        );
                });
            }
        });
    }

    private function excludeOverdue(Builder $query): Builder
    {
        return $query->whereNotExists(function ($sub) {
            $this->pendingActivity($sub)->where('la.reminder_date', '<', now());
        });
    }

    private function excludeDueToday(Builder $query): Builder
    {
        return $query->whereNotExists(function ($sub) {
            $this->pendingActivity($sub)
                ->where('la.reminder_date', '>=', now())
                ->where('la.reminder_date', '<=', now()->endOfDay());
        });
    }

    private function pendingActivity($sub)
    {
        return $sub->selectRaw('1')
            ->from('lead_activities as la')
            ->whereColumn('la.lead_id', 'leads.id')
            ->whereNull('la.deleted_at')
            ->where('la.is_completed', false);
    }

    private function applyRequirementPresence(Builder $query, bool $present): void
    {
        $method = $present ? 'where' : 'whereNot';
        $query->{$method}(function (Builder $inner) {
            $inner->whereNotNull('leads.area_id')
                ->orWhereNotNull('leads.property_type_id')
                ->orWhereNotNull('leads.bedrooms')
                ->orWhereRaw('IFNULL(leads.budget_from, 0) > 0')
                ->orWhereRaw('IFNULL(leads.budget_to, 0) > 0')
                ->orWhereRaw('IFNULL(leads.budget, 0) > 0')
                ->orWhereRaw("IFNULL(CAST(leads.extra_client_requirements AS CHAR), '') REGEXP '\"(area_id|property_type_id|bedrooms)\"[[:space:]]*:[[:space:]]*[0-9]'")
                ->orWhereRaw("IFNULL(CAST(leads.extra_client_requirements AS CHAR), '') REGEXP '\"(budget_from|budget_to)\"[[:space:]]*:[[:space:]]*[1-9]'");
        });
    }

    private function applySort(Builder $query, string $category): void
    {
        if ($category === 'urgent_overdue') {
            $query->orderBy(
                LeadActivity::query()
                    ->select('reminder_date')
                    ->whereColumn('lead_id', 'leads.id')
                    ->whereNull('deleted_at')
                    ->where('is_completed', false)
                    ->where('reminder_date', '<', now())
                    ->orderBy('reminder_date')
                    ->limit(1)
            );

            return;
        }

        if ($category === 'assigned_needs_first_contact') {
            $query->orderByRaw('COALESCE(leads.last_stage_change_at, leads.created_at) asc');

            return;
        }

        $query->orderByRaw('COALESCE(leads.last_engagement_at, leads.last_stage_change_at, leads.created_at) asc');
    }

    /**
     * @param  array<int, int>  $leadIds
     */
    private function activitiesFor(array $leadIds, string $category): Collection
    {
        if ($leadIds === [] || ! in_array($category, ['urgent_overdue', 'contacted_follow_up_due'], true)) {
            return collect();
        }

        $query = LeadActivity::query()
            ->whereIn('lead_id', $leadIds)
            ->where('is_completed', false);

        if ($category === 'urgent_overdue') {
            $query->where('reminder_date', '<', now())->orderBy('reminder_date');
        } else {
            $query->where('reminder_date', '>=', now())
                ->where('reminder_date', '<=', now()->endOfDay())
                ->orderBy('reminder_date');
        }

        return $query->get()->groupBy('lead_id');
    }

    /**
     * @param  Collection<int, LeadActivity>|null  $activities
     * @return array<string, mixed>
     */
    /**
     * @param  array<int, int>  $leadIds
     * @return array<int, string>
     */
    private function latestNotes(array $leadIds): array
    {
        if ($leadIds === []) {
            return [];
        }

        $latestIds = LeadComment::query()
            ->selectRaw('MAX(id) as id')
            ->whereIn('lead_id', $leadIds)
            ->groupBy('lead_id')
            ->pluck('id');

        if ($latestIds->isEmpty()) {
            return [];
        }

        return LeadComment::query()
            ->whereIn('id', $latestIds)
            ->get(['lead_id', 'comment'])
            ->mapWithKeys(fn (LeadComment $comment) => [(int) $comment->lead_id => trim((string) $comment->comment)])
            ->all();
    }

    private function present(Lead $lead, string $category, ?Collection $activities, string $evaluatedAt, ?string $note = null): array
    {
        $activity = $activities?->first();
        $definition = $this->actionDefinition($category, $lead, $activity);
        $deadline = $this->deadlineNote($lead);

        $explanation = $definition['explanation'];
        if ($category === 'assigned_needs_first_contact' && $deadline) {
            $explanation .= ' '.$deadline;
        }

        return [
            'key' => $definition['key'],
            'category' => $category,
            'title' => $definition['title'],
            'explanation' => $explanation,
            'lead_id' => (int) $lead->id,
            'lead_name' => $lead->lead_name,
            'lead_number' => $lead->lead_number,
            'stage_name' => $lead->stage?->name,
            'priority' => $definition['priority'],
            'urgency' => $definition['urgency'],
            'evidence' => $definition['evidence'],
            'next_step' => $definition['next_step'],
            'target' => $definition['target'],
            'rules_considered' => $this->rulesConsidered($category),
            'card' => $this->cardSnapshot($lead, $note, $activity, $category),
            'ai' => null,
            'evaluated_at' => $evaluatedAt,
            'deadline_note' => $deadline,
        ];
    }

    private function cardSnapshot(Lead $lead, ?string $note, ?LeadActivity $activity, string $category): array
    {
        $savedNote = $note !== null && $note !== '' ? mb_substr($note, 0, 180) : null;

        return [
            'name' => $lead->lead_name,
            'need' => $this->needLine($lead),
            'budget' => $this->budgetLine($lead),
            'note' => $savedNote ?: ($activity?->title ?: null),
            'reason' => $this->reasonLine($category, $lead),
            'phone' => $lead->work_phone ?: $lead->work_phone_2,
            'email' => $lead->email,
            'whatsapp' => $lead->whatsapp_number ?: $lead->work_phone ?: $lead->work_phone_2,
        ];
    }

    private function reasonLine(string $category, Lead $lead): ?string
    {
        $quietDays = $this->quietDays($lead);

        return match ($category) {
            'no_answer_call_again', 'contacted_repeated_no_answer' => 'No answer'
                .((int) $lead->no_answer_count > 0 ? ' · '.(int) $lead->no_answer_count.' recorded' : '')
                .'. Call again.',
            'answered_gone_quiet' => 'Answered, then no action for '.$quietDays.' day'.($quietDays === 1 ? '' : 's').'.',
            'qualified_missing_requirements' => 'Qualified, and area, type, bedrooms, and budget are not saved.',
            'qualified_ready_to_match' => 'Qualified. Match properties from the saved requirement.',
            'converted_deal_missing' => 'Converted, and no deal has been created.',
            'inactive' => 'No action for '.$quietDays.' day'.($quietDays === 1 ? '' : 's').'.',
            default => null,
        };
    }

    private function quietDays(Lead $lead): int
    {
        $anchor = $lead->last_engagement_at ?: $lead->last_stage_change_at ?: $lead->created_at;
        if (! $anchor) {
            return self::INACTIVE_DAYS;
        }
        $moment = $anchor instanceof Carbon ? $anchor : Carbon::parse($anchor);

        return max(1, (int) $moment->diffInDays(now()));
    }

    private function needLine(Lead $lead): ?string
    {
        $bits = [];
        if ($lead->bedrooms !== null && $lead->bedrooms !== '') {
            $bits[] = (string) $lead->bedrooms === '0' ? 'Studio' : $lead->bedrooms.'-bedroom';
        }
        if ($lead->propertyType?->name) {
            $bits[] = $lead->propertyType->name;
        }
        $line = trim(implode(' ', $bits));
        if ($lead->area?->name) {
            $line = trim($line.' in '.$lead->area->name);
        }
        if ($lead->lead_type && $line !== '') {
            $line = ucfirst((string) $lead->lead_type).': '.$line;
        }

        return $line !== '' ? $line : null;
    }

    private function budgetLine(Lead $lead): ?string
    {
        $currency = $lead->currency ?: 'AED';
        $format = fn ($amount) => $currency.' '.number_format((float) $amount, 0);
        $from = (float) $lead->budget_from;
        $to = (float) $lead->budget_to;

        if ($from > 0 && $to > 0) {
            return $format($from).' – '.$format($to);
        }
        if ($to > 0) {
            return $format($to);
        }
        if ($from > 0) {
            return $format($from);
        }
        if ((float) $lead->budget > 0) {
            return $format($lead->budget);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function actionDefinition(string $category, Lead $lead, ?LeadActivity $activity): array
    {
        $leadTarget = [
            'type' => 'lead',
            'lead_id' => (int) $lead->id,
            'section' => 'details',
            'activity_id' => null,
            'deal_id' => null,
        ];

        return match ($category) {
            'urgent_overdue' => [
                'key' => 'complete_overdue_follow_up',
                'title' => 'Complete overdue follow-up',
                'priority' => 1,
                'urgency' => 'high',
                'explanation' => $activity
                    ? 'A pending activity is overdue (due '.$activity->reminder_date?->timezone(config('app.timezone'))->format('j M, g:i A').').'
                    : 'A pending activity is overdue.',
                'next_step' => 'Open the activity and record its outcome. Completing it uses the existing activity toggle.',
                'target' => array_merge($leadTarget, [
                    'section' => 'activity',
                    'activity_id' => $activity?->id,
                ]),
                'evidence' => $this->activityEvidence($activity, 'reminder_date is in the past and is_completed is false'),
            ],
            'contacted_follow_up_due' => [
                'key' => 'follow_up_today',
                'title' => 'Follow up today',
                'priority' => 2,
                'urgency' => 'high',
                'explanation' => $activity
                    ? 'A pending follow-up is still due today ('.$activity->reminder_date?->timezone(config('app.timezone'))->format('g:i A').').'
                    : 'A pending follow-up is still due today.',
                'next_step' => 'Open the activity. Use the existing call button on the lead if you reach the client.',
                'target' => array_merge($leadTarget, [
                    'section' => 'activity',
                    'activity_id' => $activity?->id,
                ]),
                'evidence' => $this->activityEvidence($activity, 'reminder_date is later today and is_completed is false'),
            ],
            'contacted_repeated_no_answer' => [
                'key' => 'review_contact_strategy',
                'title' => 'Review contact strategy',
                'priority' => 3,
                'urgency' => 'medium',
                'explanation' => 'This contacted lead has '.(int) $lead->no_answer_count.' recorded no-answer results. The existing pool rule uses 3. This list does not change the stage.',
                'next_step' => 'Call again and save the result. Two or more no-answers means try a different time.',
                'target' => $leadTarget,
                'evidence' => [[
                    'source' => 'leads',
                    'fields' => ['stage.order', 'interaction_result', 'no_answer_count'],
                    'value' => [
                        'stage' => $lead->stage?->name,
                        'interaction_result' => $lead->interaction_result,
                        'no_answer_count' => (int) $lead->no_answer_count,
                    ],
                ]],
            ],
            'no_answer_call_again' => [
                'key' => 'call_again_no_answer',
                'title' => 'Call again',
                'priority' => 3,
                'urgency' => 'high',
                'explanation' => 'The last saved call result is no answer'
                    .((int) $lead->no_answer_count > 0 ? ' ('.(int) $lead->no_answer_count.' recorded)' : '')
                    .'. This list does not change the stage.',
                'next_step' => 'Call again and save the result on the lead.',
                'target' => $leadTarget,
                'evidence' => [[
                    'source' => 'leads',
                    'fields' => ['interaction_result', 'no_answer_count'],
                    'value' => [
                        'interaction_result' => $lead->interaction_result,
                        'no_answer_count' => (int) $lead->no_answer_count,
                    ],
                ]],
            ],
            'answered_gone_quiet' => [
                'key' => 'call_again_after_silence',
                'title' => 'Call again',
                'priority' => 4,
                'urgency' => 'medium',
                'explanation' => 'The client answered, then no engagement was saved for '.$this->quietDays($lead).' days. updated_at is not used.',
                'next_step' => 'Call again and save an activity or the call result.',
                'target' => $leadTarget,
                'evidence' => [[
                    'source' => 'leads',
                    'fields' => ['interaction_result', 'last_engagement_at'],
                    'value' => [
                        'interaction_result' => $lead->interaction_result,
                        'last_engagement_at' => optional($lead->last_engagement_at)->toIso8601String(),
                    ],
                ]],
            ],
            'assigned_needs_first_contact' => [
                'key' => 'call_now',
                'title' => 'Call now',
                'priority' => 4,
                'urgency' => $this->waitingHours($lead) >= 4 ? 'high' : 'medium',
                'explanation' => 'Assigned with no saved activity, no first-contact time, and no call result. Waiting '.$this->waitingLabel($lead).'. Opening the lead does not record a call.',
                'next_step' => 'Open the lead and use the existing phone button, then save the call result.',
                'target' => $leadTarget,
                'evidence' => [[
                    'source' => 'leads',
                    'fields' => ['stage.order', 'first_contacted_at', 'interaction_result'],
                    'value' => [
                        'stage' => $lead->stage?->name,
                        'first_contacted_at' => null,
                        'interaction_result' => $lead->interaction_result,
                        'activities' => 0,
                    ],
                ]],
            ],
            'qualified_missing_requirements' => [
                'key' => 'add_requirements',
                'title' => 'Add client requirements',
                'priority' => 5,
                'urgency' => 'medium',
                'explanation' => 'Qualified, and area, property type, bedrooms, and budget are all empty on the lead and its saved requirement rows.',
                'next_step' => 'Open the client requirement section and save what the client asked for.',
                'target' => array_merge($leadTarget, ['section' => 'requirements']),
                'evidence' => [[
                    'source' => 'leads',
                    'fields' => ['area_id', 'property_type_id', 'bedrooms', 'budget_from', 'budget_to', 'budget', 'extra_client_requirements'],
                    'value' => 'No matching criterion is saved.',
                ]],
            ],
            'qualified_ready_to_match' => [
                'key' => 'match_properties',
                'title' => 'Match properties',
                'priority' => 6,
                'urgency' => 'medium',
                'explanation' => 'Qualified, and a saved area, type, bedroom count, or budget is enough for the existing matching list.',
                'next_step' => 'Open matching properties. This does not invent requirements.',
                'target' => array_merge($leadTarget, ['section' => 'matching']),
                'evidence' => [[
                    'source' => 'leads',
                    'fields' => ['area_id', 'property_type_id', 'bedrooms', 'budget_from', 'budget_to', 'budget'],
                    'value' => [
                        'area_id' => $lead->area_id,
                        'property_type_id' => $lead->property_type_id,
                        'bedrooms' => $lead->bedrooms,
                        'budget_from' => $lead->budget_from,
                        'budget_to' => $lead->budget_to,
                    ],
                ]],
            ],
            'converted_deal_missing' => [
                'key' => 'create_deal',
                'title' => 'Create the deal',
                'priority' => 7,
                'urgency' => 'high',
                'explanation' => 'The lead is Converted, converted_to_deal_id is empty, and no deal row points at this lead.',
                'next_step' => 'Open the existing create-deal form. Nothing is created until you confirm it there.',
                'target' => array_merge($leadTarget, ['section' => 'create_deal']),
                'evidence' => [[
                    'source' => 'leads',
                    'fields' => ['stage.order', 'converted_to_deal_id'],
                    'value' => ['converted_to_deal_id' => null, 'deals' => 0],
                ]],
            ],
            'approaching_deadline' => [
                'key' => 'review_before_deadline',
                'title' => 'Review before deadline',
                'priority' => 8,
                'urgency' => 'high',
                'explanation' => $this->deadlineNote($lead) ?: 'A configured stage deadline is inside '.self::DEADLINE_WARNING_HOURS.' hours.',
                'next_step' => 'Open the lead and work it. Automatic reversion is not run from here.',
                'target' => $leadTarget,
                'evidence' => [[
                    'source' => 'stages',
                    'fields' => ['auto_revert', 'revert_after_hours', 'last_engagement_at', 'last_stage_change_at'],
                    'value' => $this->deadlineNote($lead),
                ]],
            ],
            default => [
                'key' => 'reengage_lead',
                'title' => 'Re-engage lead',
                'priority' => 9,
                'urgency' => 'low',
                'explanation' => 'No human engagement is recorded in the last '.self::INACTIVE_DAYS.' days. updated_at is not used.',
                'next_step' => 'Open the lead and make contact, then save an activity or call result.',
                'target' => $leadTarget,
                'evidence' => [[
                    'source' => 'leads',
                    'fields' => ['last_engagement_at', 'last_stage_change_at', 'created_at'],
                    'value' => [
                        'last_engagement_at' => optional($lead->last_engagement_at)->toIso8601String(),
                        'last_stage_change_at' => optional($lead->last_stage_change_at)->toIso8601String(),
                    ],
                ]],
            ],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activityEvidence(?LeadActivity $activity, string $fact): array
    {
        if (! $activity) {
            return [[
                'source' => 'lead_activities',
                'fields' => ['reminder_date', 'is_completed'],
                'value' => $fact,
            ]];
        }

        return [[
            'source' => 'lead_activities',
            'activity_id' => (int) $activity->id,
            'fields' => ['title', 'reminder_date', 'is_completed'],
            'value' => [
                'title' => $activity->title,
                'reminder_date' => optional($activity->reminder_date)->toIso8601String(),
                'is_completed' => (bool) $activity->is_completed,
                'fact' => $fact,
            ],
        ]];
    }

    /**
     * @return array<int, array{key: string, matched: bool, reason: string}>
     */
    private function rulesConsidered(string $matchedCategory): array
    {
        $order = [
            'urgent_overdue' => 'A pending reminder is already overdue.',
            'contacted_follow_up_due' => 'A pending reminder is still due today.',
            'contacted_repeated_no_answer' => 'Contacted with two or more recorded no-answers.',
            'no_answer_call_again' => 'Last saved result is no answer.',
            'answered_gone_quiet' => 'Answered, then no action for '.self::INACTIVE_DAYS.' days.',
            'assigned_needs_first_contact' => 'Assigned with no activity and no call result.',
            'qualified_missing_requirements' => 'Qualified without matchable requirements.',
            'qualified_ready_to_match' => 'Qualified with matchable requirements.',
            'converted_deal_missing' => 'Converted with no associated deal.',
            'approaching_deadline' => 'Inside the configured deadline window.',
            'inactive' => 'No human engagement in '.self::INACTIVE_DAYS.' days.',
        ];

        $seenMatch = false;
        $rows = [];
        foreach ($order as $key => $reason) {
            if (! $this->definition($key) && $key === 'approaching_deadline') {
                continue;
            }
            $matched = $key === $matchedCategory;
            if ($matched) {
                $seenMatch = true;
            }
            $rows[] = [
                'key' => $key,
                'matched' => $matched,
                'reason' => $matched
                    ? $reason
                    : ($seenMatch
                        ? 'Not selected. A higher-priority rule already matched this lead.'
                        : 'Not matched. '.$reason),
            ];
        }

        return $rows;
    }

    private function definition(string $key): ?array
    {
        foreach ($this->categoryDefinitions() as $definition) {
            if ($definition['key'] === $key) {
                return $definition;
            }
        }

        return null;
    }

    private function deadlineNote(Lead $lead): ?string
    {
        $due = $lead->revertDueAt();
        if (! $due instanceof Carbon) {
            return null;
        }
        if ($due->lessThanOrEqualTo(now()) || $due->greaterThan(now()->addHours(self::DEADLINE_WARNING_HOURS))) {
            return null;
        }

        return 'Configured deadline is '.$due->timezone(config('app.timezone'))->format('j M, g:i A').'. Automatic reversion is not run from this screen.';
    }

    private function waitingHours(Lead $lead): int
    {
        $anchor = $lead->last_stage_change_at ?: $lead->created_at;

        return $anchor ? (int) $anchor->diffInHours(now()) : 0;
    }

    private function waitingLabel(Lead $lead): string
    {
        $hours = $this->waitingHours($lead);
        if ($hours < 1) {
            return 'less than an hour';
        }
        if ($hours < 48) {
            return $hours.' hour'.($hours === 1 ? '' : 's');
        }

        $days = intdiv($hours, 24);

        return $days.' day'.($days === 1 ? '' : 's');
    }

    /**
     * @param  array<int, int>  $orders
     * @return array<int, int>
     */
    private function stageIdsForOrders(array $orders): array
    {
        $ids = $this->leadStages()
            ->filter(fn (Stage $stage) => in_array((int) $stage->order, $orders, true))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $ids === [] ? [-1] : $ids;
    }

    /**
     * @return array<int, int>
     */
    private function poolStageIds(): array
    {
        if ($this->poolStageIds !== null) {
            return $this->poolStageIds;
        }

        $this->poolStageIds = $this->leadStages()
            ->filter(function (Stage $stage) {
                $name = strtolower(trim((string) $stage->name));

                return $name === 'lead pool'
                    || $name === 'leadpool'
                    || (int) $stage->order === 9
                    || (int) $stage->id === 10;
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->poolStageIds;
    }

    /**
     * @return Collection<int, Stage>
     */
    private function leadStages(): Collection
    {
        return $this->leadStages ??= Stage::query()
            ->where('stage_type', 'lead')
            ->get(['id', 'name', 'order', 'auto_revert', 'revert_after_hours', 'status_revert_rules']);
    }

    /**
     * The inactive/deadline query must not use a loose orWhere that escapes
     * the caller's visibility constraints. Higher-priority stages are removed
     * only when they still match their own rule.
     */
    private function excludeActionableWork(Builder $query): Builder
    {
        $query = $this->excludeDueToday($this->excludeOverdue($query));

        $assigned = $this->stageIdsForOrders([2]);
        $contacted = $this->stageIdsForOrders([3]);
        $working = $this->stageIdsForOrders([1, 2, 3, 4, 7]);
        $qualified = $this->stageIdsForOrders([4]);
        $converted = $this->stageIdsForOrders([6]);
        $quietBefore = now()->subDays(self::INACTIVE_DAYS);

        return $query->where(function (Builder $inner) use ($assigned, $contacted, $working, $qualified, $converted, $quietBefore) {
            $inner->where(function (Builder $notAssignedGap) use ($assigned) {
                $notAssignedGap->whereNotIn('leads.stage_id', $assigned)
                    ->orWhereNotNull('leads.first_contacted_at')
                    ->orWhere(function (Builder $result) {
                        $result->whereNotNull('leads.interaction_result')
                            ->where('leads.interaction_result', '!=', '');
                    })
                    ->orWhereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('lead_activities as la')
                            ->whereColumn('la.lead_id', 'leads.id')
                            ->whereNull('la.deleted_at');
                    });
            })->where(function (Builder $notNoAnswer) use ($contacted, $working) {
                $notNoAnswer->where(function (Builder $notRepeated) use ($contacted) {
                    $notRepeated->whereNotIn('leads.stage_id', $contacted)
                        ->orWhere('leads.interaction_result', '!=', 'no_answer')
                        ->orWhereNull('leads.interaction_result')
                        ->orWhere('leads.no_answer_count', '<', self::NO_ANSWER_REVIEW_AT);
                })->where(function (Builder $notOtherNoAnswer) use ($working) {
                    $notOtherNoAnswer->whereNotIn('leads.stage_id', $working)
                        ->orWhere('leads.interaction_result', '!=', 'no_answer')
                        ->orWhereNull('leads.interaction_result');
                });
            })->where(function (Builder $notQuietAnswer) use ($working, $quietBefore) {
                $notQuietAnswer->whereNotIn('leads.stage_id', $working)
                    ->orWhere('leads.interaction_result', '!=', 'answered')
                    ->orWhereNull('leads.interaction_result')
                    ->orWhereRaw('COALESCE(leads.last_engagement_at, leads.last_stage_change_at, leads.created_at) >= ?', [$quietBefore]);
            })->where(function (Builder $notQualified) use ($qualified) {
                $notQualified->whereNotIn('leads.stage_id', $qualified);
            })->where(function (Builder $notConvertedGap) use ($converted) {
                $notConvertedGap->whereNotIn('leads.stage_id', $converted)
                    ->orWhereNotNull('leads.converted_to_deal_id')
                    ->orWhereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('deals')
                            ->whereColumn('deals.lead_id', 'leads.id')
                            ->whereNull('deals.deleted_at');
                    });
            });
        });
    }
}
