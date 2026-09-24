<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
use App\Helpers\LeadHistoryHelper;
use App\Events\LeadUpdated;
use App\Models\KanbanSetting;
use App\Jobs\ProcessLeadAutoAssignmentJob;
use App\Jobs\ProcessLeadIntelligenceJob;
use App\Models\LeadScoringSetting;
use App\Traits\AltCRMLeadTrait;
use DB;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    // 
    use HasFactory; use AltCRMLeadTrait; use SoftDeletes;
    protected $guarded=[];
    public const INTELLIGENCE_FIELDS = [
        'score',
        'priority',
        'intent',
        'next_action',
        'last_scored_at',
        'score_breakdown',
    ];
      protected $casts = [
        'date_of_birth' => 'date',
        'available_to_everyone' => 'boolean',
        'last_stage_change_at' => 'datetime',
        'first_contacted_at' => 'datetime',
        'last_sla_escalation_at' => 'datetime',
        'assignment_hold' => 'boolean',
        'revert'=>'datetime',
        'converted_at'=>'datetime',
        'last_scored_at' => 'datetime',
        'score_breakdown' => 'array',
        'extra_client_requirements' => 'array',
         'whatsapp_qualification' => 'array', 
           'notification_times_sent' => 'array', 
         'archived_at' => 'datetime',
        'no_answer_count' => 'integer',
        'lead_pool_visits' => 'integer',

    ];
    /** أعمدة تغييرها لا يُعتبر "تفاعل" من اليوزر */
protected const NON_ENGAGEMENT_FIELDS = [
    'last_engagement_at',
    'notification_times_sent',
    'notified_revert',
    'revert',
    'updated_at',
    'stage_id',
    'last_stage_change_at',
    'score',
    'priority',
    'intent',
    'next_action',
    'last_scored_at',
    'score_breakdown',
    'bitrix24_last_activity_at',
    'bitrix24_data',
    'field_mappings_data',
    'raw_meta_data',
];
    protected static function booted()
    {
        
            static::creating(function ($lead) {
                if ($lead->responsible_person_id && !$lead->initial_responsible_person_id) {
                    $lead->initial_responsible_person_id = $lead->responsible_person_id;
                }
            });
            static::created(function ($lead) {
                $settings = LeadScoringSetting::resolved();
                $automation = $settings['automation_flags'] ?? [];
                if (($automation['on_create'] ?? true) === true) {
                    ProcessLeadIntelligenceJob::dispatch($lead->id);
                }

                ProcessLeadAutoAssignmentJob::dispatch($lead->id)->afterCommit();
            //       if ($lead->shouldSyncToAltCRM()) {
            //     $lead->sendToAltCRM();
            // }
            });
            static::updated(function ($lead) {
                $intelligenceOnlyKeys = array_merge(self::INTELLIGENCE_FIELDS, ['updated_at']);

                $changedKeys = array_keys($lead->getChanges());
                $nonIntelligenceChanges = array_diff($changedKeys, $intelligenceOnlyKeys);

                $settings = LeadScoringSetting::resolved();
                $automation = $settings['automation_flags'] ?? [];
                if (!empty($nonIntelligenceChanges) && (($automation['on_update'] ?? true) === true)) {
                    ProcessLeadIntelligenceJob::dispatch($lead->id);
                }
                $touched = array_diff(array_keys($lead->getChanges()), self::NON_ENGAGEMENT_FIELDS);

                if (!empty($touched)) {
                    // كتابة مباشرة على الـ DB — بعيد تماماً عن دورة الحفظ الحالية
                    static::withoutEvents(function () use ($lead) {
                        DB::table('leads')
                            ->where('id', $lead->id)
                            ->update([
                                'last_engagement_at'      => now(),
                                'notification_times_sent' => json_encode([]),
                                'notified_revert'         => 0,
                            ]);
                    });

                    $lead->setAttribute('last_engagement_at', now());
                }
                    \Log::info('HOOK_FIRED', ['id' => $lead->id, 'changes' => array_keys($lead->getChanges())]);

            });
            static::updating(function ($lead) {
                    if (! $lead->isDirty('interaction_result')) {
                        return;
                    }

                    if ($lead->interaction_result === 'no_answer') {
                        $lead->no_answer_count = (int) $lead->getOriginal('no_answer_count') + 1;
                    } elseif ($lead->interaction_result === 'answered') {
                        $lead->no_answer_count = 0;
                    }
                });
        // static::updating(function ($lead) {
        //     if (
        //         $lead->isDirty('responsible_person_id') &&
        //         !$lead->initial_responsible_person_id
        //     ) {
        //         $lead->initial_responsible_person_id = $lead->getOriginal('responsible_person_id');
        //     }
        // });
    }

        public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
          public function last_activity_user()
    {
        return $this->belongsTo(User::class, 'bitrix24_last_activity_by_id');
    }
    

    public function histories()
    {
        if (auth()->check() && auth()->user()->hasAnyRole(['admin', 'super_admin'])) {
            return $this->hasMany(LeadHistory::class)->withTrashed()->latest();
        }
        return $this->hasMany(LeadHistory::class)->latest();
    }
    public function createdHistory()
    {
        return $this->hasOne(LeadHistory::class)
            ->where('changes->action', 'created')
            ->oldest(); 
    }

      public function integration()
    {
        return $this->belongsTo(Integration::class, 'integration_id');
    }
  

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public function responsiblePerson()
    {
        return $this->belongsTo(User::class, 'responsible_person_id');
    }
    public function initialResponsiblePerson()
{
    return $this->belongsTo(User::class, 'initial_responsible_person_id');
}


 
    public function participants()
    {
        return $this->hasMany(LeadParticipant::class);
    }

     public function observers()
    {
        return $this->hasMany(LeadObserver::class);
    }

    public function observingUsers()
    {
        return $this->belongsToMany(User::class, 'lead_observers')
                    ->withTimestamps();
    }
 public function comments()
{
    return $this->visibleEngagement(LeadComment::class);
}

public function activities()
{
    return $this->visibleEngagement(LeadActivity::class);
}

protected static array $subordinateIdsCache = [];

/** id اليوزر + كل اللي تحته في الـ parent_id chain */
public static function subordinateIds(User $user): array
{
    if (isset(static::$subordinateIdsCache[$user->id])) {
        return static::$subordinateIdsCache[$user->id];
    }

    $ids = [$user->id];
    $frontier = [$user->id];

    while (! empty($frontier)) {
        $frontier = User::whereIn('parent_id', $frontier)
            ->pluck('id')
            ->diff($ids)
            ->values()
            ->all();
        $ids = array_merge($ids, $frontier);
    }

    return static::$subordinateIdsCache[$user->id] = $ids;
}

/** هل اليوزر ده مانجر للمسؤول الحالي (ومش هو المسؤول نفسه)؟ */
public function isManagedBy(User $user): bool
{
    return $this->responsible_person_id
        && (int) $this->responsible_person_id !== (int) $user->id
        && in_array((int) $this->responsible_person_id, static::subordinateIds($user), true);
}

public function visibleEngagement(string $model, string $ownerColumn = 'user_id')
{
    $relation = $this->hasMany($model);
    $user     = auth()->user();

    if (!$user) {
        return $relation->whereRaw('1 = 0');
    }

    if ($user->hasAnyRole(['admin', 'super_admin'])) {
        return $relation->withTrashed()->latest();
    }

    $allowed = array_values(array_unique(array_map(
        'intval',
        static::subordinateIds($user)
    )));

    if (empty($allowed)) {
        return $relation->whereRaw('1 = 0');
    }

    $table   = $relation->getRelated()->getTable();       // lead_comments
    $leadKey = $relation->getQualifiedForeignKeyName();   // lead_comments.lead_id
    $ids     = implode(',', $allowed);

    // صاحب الليد لحظة كتابة الكومنت:
    //  - old_person_id بتاع أول "assigned" حصل بعد الكومنت
    //  - أو responsible_person_id الحالي لو مفيش إسناد بعده
    $ownerAtCommentTime = sprintf(
        "coalesce(
            nullif(
                json_unquote(json_extract((
                    select h.changes
                      from lead_histories h
                     where h.lead_id = %s
                       and json_unquote(json_extract(h.changes, '$.action')) = 'assigned'
                       and h.created_at > %s.created_at
                     order by h.created_at asc, h.id asc
                     limit 1
                ), '$.old_person_id')),
                'null'
            ),
            (select l.responsible_person_id from leads l where l.id = %s)
        )",
        $leadKey,
        $table,
        $leadKey
    );

    return $relation
        ->where(function ($q) use ($table, $allowed, $ownerColumn, $ownerAtCommentTime, $ids) {
            // 1) اللي كتبه هو أو حد من تحته — يفضل معاه حتى لو الليد اتاخد منه
            $q->whereIn("{$table}.{$ownerColumn}", $allowed);

            // 2) أي حد كتبه (أدمن / بارنت) والليد كان في إيده وقتها
            $q->orWhereRaw("{$ownerAtCommentTime} in ({$ids})");
        })
        ->latest();
}


    public function commentsWithTrashed()
{
    return $this->hasMany(LeadComment::class)->withTrashed();
}

public function activitiesWithTrashed()
{
    return $this->hasMany(LeadActivity::class)->withTrashed();
}

    public function pendingActivities()
    {
        return $this->hasMany(LeadActivity::class)->pending();
    }
  // Auto revert logic
    public function shouldRevertToStageOne(): bool
    {
        if ($this->stage && $this->stage->order == 2 && $this->last_stage_change_at) {
           $revertHours = KanbanSetting::getRevertHours();
        
            $revertTime = Carbon::now()->subHours($revertHours);
            return $this->last_stage_change_at->lessThanOrEqualTo($revertTime);
        }

        return false;
    }

         public function revertToStageOne(): void
        {
            $stageOne = Stage::where('order', 1)->first();
            $response = $this->initial_responsible_person_id;
            $responseName = $this->initialResponsiblePerson?->name;
        
            $oldPerson = $this->responsiblePerson;
            $oldStage  = $this->stage;
        
            if (!$response) {
                $response = $this->responsiblePerson?->admin_parent?->id;
                $responseName=$this->responsiblePerson?->admin_parent?->name;
            }
        
            if ($stageOne) {
        
                $this->updateQuietly([
                    'stage_id' => $stageOne->id,
                    'last_stage_change_at' => now(),
                    // 'responsible_person_id' => $response,
                    'revert'=>now(),
                ]);
        
                $this->refresh();
        
                LeadHistoryHelper::log(
                    $this->id,
                    [
                        'action' => 'revert',
                        'old_person_id' => $oldPerson?->id,
                        'old_person' => $oldPerson?->name,
                        // 'new_person' => $responseName,
                        'old_stage'  => $oldStage?->name,
                        'new_stage'  => $this->stage?->name
                    ]
                );
        
                $changes = [
                    'old_stage'  => $oldStage?->name,
                    'new_stage'  => $this->stage?->name,
                    'old_person_id' => $oldPerson?->id,
                    // 'new_person' =>$responseName,
                ];
        
                broadcast(new LeadUpdated($this, 'revert', null, $changes));
            }
        }


    public function scopeNeedsRevert($query)
    {
          $revertHours = KanbanSetting::getRevertHours();
        
        return $query->whereHas('stage', function($q) {
            $q->where('order', 2);
        })->where('last_stage_change_at', '<=', Carbon::now()->subHours($revertHours));
    }
     
     public function getDuplicateLeadsAttribute()
        {
            return Lead::where('id', '!=', $this->id)
            ->whereNotNull('work_phone')
               ->where('work_phone', $this->work_phone)
                ->get();
        }

        public function convertedToDeal()
        {
            return $this->belongsTo(Deal::class, 'converted_to_deal_id');
        }
        
        public function isConverted()
        {
            return !is_null($this->converted_to_deal_id);
        }
        
        public function getDealAttribute()
        {
            return $this->convertedToDeal;
        }
    public function assignmentLogs()
    {
        return $this->hasMany(LeadAssignmentLog::class);
    }

    public function getComputedAssignmentScoreAttribute(): float
    {
        if ($this->score !== null) {
            return (float) min(100, max(0, (int) $this->score));
        }

        $budget = (float) ($this->budget ?? 0);
        $budgetPart = $budget > 0 ? min(45, log(1 + ($budget / 50000)) * 12) : 0.0;
        $sourcePart = $this->lead_source ? 22.0 : 0.0;
        $priority = strtolower((string) ($this->priority ?? ''));
        $priorityPart = match (true) {
            str_contains($priority, 'hot') => 28.0,
            str_contains($priority, 'warm') => 16.0,
            $priority !== '' => 10.0,
            default => 6.0,
        };

        return round(min(100, $budgetPart + $sourcePart + $priorityPart), 1);
    }

        public function area()
        {
            return $this->belongsTo(Area::class);
        }
        
        public function propertyType()
        {
            return $this->belongsTo(PropertyType::class);
        }

         /** الليد وصلت للحد الأقصى من زيارات Lead Pool → أرشيف */
public function shouldArchive(): bool
{
    return (int) $this->lead_pool_visits >= 3 && $this->hitNoAnswerLimit();
}

/** 3 محاولات no_answer في Contacted */
public function hitNoAnswerLimit(): bool
{
    return (int) ($this->stage?->order ?? 0) === 3
        && $this->interaction_result === 'no_answer'
        && (int) $this->no_answer_count >= 3;
}

public function getRevertTargetStage()
{
    // Contacted + 3 محاولات no_answer → Lead Pool
    if ($this->hitNoAnswerLimit()) {
        // إلا لو راح Lead Pool 3 مرات قبل كده → أرشيف (بيتعامل في revertToPreviousStage)
        return Stage::where('stage_type', 'lead')->where('order', 9)->first();
    }

    $configured = $this->stage?->revertToStage;
    if (
        $configured
        && (int) $configured->order < (int) ($this->stage?->order ?? 0)
    ) {
        return $configured;
    }

    return $this->getPreviousStage();
}

   public function shouldAutoRevert(): bool
{
    $due = $this->revertDueAt();
    return $due !== null && $due->lessThanOrEqualTo(now());
}

public function shouldSendRevertNotificationAt($minutesBefore): bool
{
    $due = $this->revertDueAt();
    if (!$due) return false;

    if (in_array($minutesBefore, $this->notification_times_sent ?? [], false)) {
        return false;
    }

    return now()->greaterThanOrEqualTo($due->copy()->subMinutes($minutesBefore));
}

    /**
     * الحصول على المرحلة السابقة
     */
    public function getPreviousStage()
    {
        if (!$this->stage) {
            return null;
        }

        $type = $this->stage->stage_type ?: 'lead';
        $candidates = \App\Models\Stage::where('stage_type', $type)
            ->where('order', '<', $this->stage->order)
            ->orderByDesc('order')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $previousOrder = $candidates->first()->order;
        $sameOrder = $candidates->where('order', $previousOrder);

        if ($sameOrder->count() === 1) {
            return $sameOrder->first();
        }

        $currentId = (int) $this->stage->id;

        return $sameOrder
            ->sortBy(fn ($stage) => abs((int) $stage->id - $currentId))
            ->first();
    }

    /**
     * الرجوع إلى المرحلة المستهدفة
     */
    public function revertToPreviousStage(): void
    {
       if ($this->shouldArchive()) {
                $this->archiveLead();
                return;
            }

            $targetStage = $this->getRevertTargetStage();
            if (!$targetStage) return;

            if ($targetStage->order == 1) {
                $this->revertToStageOne();
                return;
            }

            $oldStage   = $this->stage;
            $oldPerson  = $this->responsiblePerson;
            $isLeadPool = (int) $targetStage->order === 9;

            $this->updateQuietly([
                'stage_id'                => $targetStage->id,
                'last_stage_change_at'    => now(),
                'last_engagement_at'      => null,
                'revert'                  => now(),
                'notified_revert'         => false,
                'notification_times_sent' => [],
                'lead_pool_visits'        => $isLeadPool
                    ? ((int) $this->lead_pool_visits + 1)
                    : (int) $this->lead_pool_visits,
            ]);

        $this->refresh();

        LeadHistoryHelper::log(
            $this->id,
            [
                'action' => 'revert',
                'old_stage' => $oldStage?->name,
                'new_stage' => $this->stage?->name,
                'old_person_id' => $oldPerson?->id,
                'target_stage_id' => $targetStage->id,
            ]
        );

        $changes = [
            'old_stage' => $oldStage?->name,
            'new_stage' => $this->stage?->name,
            'old_person_id' => $oldPerson?->id,
        ];

        broadcast(new LeadUpdated($this, 'revert', null, $changes));
    }

    public function archiveLead(): void
    {
        $oldStage = $this->stage;

        $this->updateQuietly(['archived_at' => now()]);

        LeadHistoryHelper::log($this->id, [
            'action'           => 'archived',
            'old_stage'        => $oldStage?->name,
            'lead_pool_visits' => (int) $this->lead_pool_visits,
            'no_answer_count'  => (int) $this->no_answer_count,
        ]);

        broadcast(new LeadUpdated($this, 'deleted', null, ['reason' => 'archived']));

        $this->delete();
    }
    /**
     * تسجيل إرسال إشعار
     */
    public function markNotificationSent($minutesBefore): void
    {
        $sentTimes = $this->notification_times_sent ?? [];
        if (!in_array($minutesBefore, $sentTimes)) {
            $sentTimes[] = $minutesBefore;
            $this->updateQuietly([
                'notification_times_sent' => $sentTimes
            ]);
        }
    }

    /**
     * التحقق من وجود إشعارات متبقية
     */
    public function hasPendingNotifications(): bool
    {
        if (!$this->stage || !$this->stage->auto_revert) {
            return false;
        }

        $notificationTimes = $this->stage->notification_times ?? [30, 15, 5];
        $sentTimes = $this->notification_times_sent ?? [];

        foreach ($notificationTimes as $time) {
            if (!in_array($time, $sentTimes)) {
                $revertTime = $this->last_stage_change_at->copy()->addHours($this->stage->revert_after_hours ?? 0);
                $notifyTime = $revertTime->copy()->subMinutes($time);
                
                // إذا كان وقت الإشعار لم يمر بعد
                if ($notifyTime->greaterThan(now())) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * الحصول على الرسالة المخصصة للإشعار
     */
    public function getRevertNotificationMessage(): ?string
    {
        if ($this->stage && $this->stage->revert_notification_message) {
            return $this->stage->revert_notification_message;
        }

        return null;
    }

    /** ساعات الريفرت للّيد ده، أو null لو مفيش ريفرت */
public function revertHours(): ?int
{
    if ($this->added_by && (int) $this->added_by === (int) $this->responsible_person_id) {
        return null;
    }

    $stage = $this->stage;
    if (!$stage || !$stage->auto_revert) {
        return null;
    }

    $rules = $stage->status_revert_rules;
    if (is_array($rules) && $rules) {
        $rule = $rules[strtolower(trim((string) $this->status_lead))] ?? null;
        return $rule ? (int) $rule['hours'] : null;
    }

    return (int) $stage->revert_after_hours ?: null;
}

public function revertAnchorAt(): ?Carbon
{
    $dates = collect([$this->last_stage_change_at, $this->last_engagement_at])
        ->filter()
        ->map(fn ($d) => $d instanceof Carbon ? $d : Carbon::parse($d));

    return $dates->isEmpty() ? null : $dates->max();
}
public function revertDueAt(): ?Carbon
{
    $hours  = $this->revertHours();
    $anchor = $this->revertAnchorAt();

    return ($hours && $anchor) ? $anchor->copy()->addHours($hours) : null;
}

public function revertNotifyMinutes(): array
{
    $stage = $this->stage;

    // Qualified: مواعيد محسوبة من every_hours
    $rules = $stage?->status_revert_rules;
    if (is_array($rules) && $rules) {
        $rule = $rules[strtolower(trim((string) $this->status_lead))] ?? null;
        if (!$rule) return [];

        $total = (int) $rule['hours'];
        $step  = (int) ($rule['every_hours'] ?: $total);
        $out   = [];

        for ($h = $step; $h < $total; $h += $step) {
            $out[] = ($total - $h) * 60;
        }
        return $out;
    }

    // باقي المراحل: من notification_times زي ما هي
    $times = $stage?->notification_times;
    return is_array($times) && $times ? $times : [30];
}

public function revertCountdownLabel(int $minutes): string
{
    if ($minutes >= 1440) return (int) floor($minutes / 1440) . ' day(s)';
    if ($minutes >= 60)   return (int) floor($minutes / 60) . ' hour(s)';
    return $minutes . ' minute(s)';
}

public function touchEngagement(): void
{
    $this->updateQuietly([
        'last_engagement_at'      => now(),
        'notification_times_sent' => [],
        'notified_revert'         => false,
    ]);
}
}
