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
class Lead extends Model
{
    // 
    use HasFactory; use AltCRMLeadTrait;
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
        if (auth()->check() && auth()->user()->hasAnyRole(['admin', 'super_admin'])) {
            return $this->hasMany(LeadComment::class)->withTrashed();
        }
            return $this->hasMany(LeadComment::class)->latest();
    }
       public function activities()
    {
        if (auth()->check() && auth()->user()->hasAnyRole(['admin', 'super_admin'])) {
            return $this->hasMany(LeadActivity::class)->withTrashed();
        }
            return $this->hasMany(LeadActivity::class)->latest();
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

          public function getRevertTargetStage()
    {
        if ($this->stage && $this->stage->revert_to_stage_id) {
            return $this->stage->revertToStage;
        }

        // إذا لم يتم تحديد مرحلة، نرجع المرحلة السابقة (للتوافق القديم)
        return $this->getPreviousStage();
    }

    /**
     * التحقق من الحاجة للرجوع التلقائي
     */
    public function shouldAutoRevert(): bool
    {
        if (!$this->stage || !$this->stage->auto_revert || !$this->last_stage_change_at) {
            return false;
        }

        $hours = $this->stage->revert_after_hours ?? 0;

        if ($hours <= 0) return false;

        return $this->last_stage_change_at
            ->addHours($hours)
            ->lessThanOrEqualTo(now());
    }

    /**
     * الحصول على المرحلة السابقة
     */
    public function getPreviousStage()
    {
        return \App\Models\Stage::where('order', '<', $this->stage->order)
            ->orderBy('order', 'desc')
            ->first();
    }

    /**
     * الرجوع إلى المرحلة المستهدفة
     */
    public function revertToPreviousStage(): void
    {
        $targetStage = $this->getRevertTargetStage();

        if (!$targetStage) return;

        // ✅ إذا كانت المرحلة المستهدفة هي المرحلة الأولى، استخدم المنطق القديم
        if ($targetStage->order == 1) {
            $this->revertToStageOne();
            return;
        }

        // 🔹 باقي الحالات (revert عادي)
        $oldStage = $this->stage;
        $oldPerson = $this->responsiblePerson;

        $this->updateQuietly([
            'stage_id' => $targetStage->id,
            'last_stage_change_at' => now(),
            'revert' => now(),
            'notified_revert' => false,
            'notification_times_sent' => [], // إعادة تعيين الإشعارات المرسلة
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

    /**
     * التحقق من الحاجة لإرسال إشعار في وقت محدد
     */
    public function shouldSendRevertNotificationAt($minutesBefore): bool
    {
        if (!$this->stage || !$this->stage->auto_revert || !$this->last_stage_change_at) {
            return false;
        }

        $hours = $this->stage->revert_after_hours ?? 0;

        if ($hours <= 0) return false;

        $revertTime = $this->last_stage_change_at->copy()->addHours($hours);
        $notifyTime = $revertTime->copy()->subMinutes($minutesBefore);

        // التحقق من أن الوقت الحالي هو وقت الإشعار (مع مراعاة التسامح)
        $now = now();
        $isNotificationTime = $now->between(
            $notifyTime->copy()->subMinutes(1),
            $notifyTime->copy()->addMinutes(15)
        );

        if (!$isNotificationTime) {
            return false;
        }

        // التحقق من عدم إرسال هذا الإشعار مسبقاً
        $sentTimes = $this->notification_times_sent ?? [];
        if (in_array($minutesBefore, $sentTimes)) {
            return false;
        }

        return true;
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
}
