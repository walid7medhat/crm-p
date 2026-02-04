<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
class Lead extends Model
{
    // 
    use HasFactory;
    protected $guarded=[];
      protected $casts = [
        'date_of_birth' => 'date',
        'available_to_everyone' => 'boolean',
        'last_stage_change_at' => 'datetime'

    ];

        public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function histories()
    {
        return $this->hasMany(LeadHistory::class)->latest();
    }

    
  

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public function responsiblePerson()
    {
        return $this->belongsTo(User::class, 'responsible_person_id');
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
        
        if ($stageOne) {
            $this->update([
                'stage_id' => $stageOne->id,
                'last_stage_change_at' => now()
            ]);
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


}
