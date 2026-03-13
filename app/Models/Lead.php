<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
use App\Helpers\LeadHistoryHelper;
use App\Events\LeadUpdated;

class Lead extends Model
{
    // 
    use HasFactory;
    protected $guarded=[];
      protected $casts = [
        'date_of_birth' => 'date',
        'available_to_everyone' => 'boolean',
        'last_stage_change_at' => 'datetime',
        'revert'=>'datetime',
        'converted_at'=>'datetime'

    ];
    protected static function booted()
    {
            static::creating(function ($lead) {
                if ($lead->responsible_person_id && !$lead->initial_responsible_person_id) {
                    $lead->initial_responsible_person_id = $lead->responsible_person_id;
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

    public function histories()
    {
        return $this->hasMany(LeadHistory::class)->latest();
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
        return $this->hasMany(LeadComment::class)->latest();
    }
       public function activities()
    {
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
            $oneHourAgo = Carbon::now()->subHour();
            return $this->last_stage_change_at->lessThanOrEqualTo($oneHourAgo);
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
        return $query->whereHas('stage', function($q) {
            $q->where('order', 2);
        })->where('last_stage_change_at', '<=', Carbon::now()->subHour());
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
}
