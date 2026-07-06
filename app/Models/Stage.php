<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
class Stage extends Model
{
    use HasFactory;
    use LogsActivity;
    protected $casts = [
        'auto_revert' => 'boolean',
        'revert_after_hours' => 'integer',
        'notify_before_minutes' => 'integer',
          'notification_times' => 'array', 
    ];
    public function getActivitylogOptions(): LogOptions
    {
        // use $listing->activities
        return LogOptions::defaults()
            ->logOnlyDirty() 
            ->logAll()       
            ->useLogName('stage');
    }
    //
    use HasFactory;
    protected $guarded=[];
     public function leads()
    {
        $leads= $this->hasMany(Lead::class);
        
             $user=auth()->user();
             if ($user->hasAnyRole([ 'super_admin']) || $user->id==33 || $user->id 30) {
             }
            elseif ($user->hasAnyRole(['manager', 'team_lead','admin'])) {
                $subordinatesIds = $user->getAllSubordinatesIds();
                
                $leads = $leads->where(function($query) use ($subordinatesIds, $user) {
                    $query->whereIn('responsible_person_id',array_merge( $subordinatesIds,[$user->id]))
                          ->orWhereIn('added_by', $subordinatesIds);
                                });
            }
            else {
                $leads = $leads->where(function($query) use ($user) {
                            $query->where('responsible_person_id', $user->id)
                                ->orWhere('added_by', $user->id);
                        });
            }
            return $leads->orderBy('updated_at','desc');
    }
            public function deals()
        {
            return $this->hasMany(Deal::class);
        }
        
        public function isLeadStage()
        {
            return $this->stage_type === 'lead';
        }
        
        public function isDealStage()
        {
            return $this->stage_type === 'deal';
        }
        
        public function scopeLeadStages($query)
        {
            return $query->where('stage_type', 'lead');
        }
        
        public function scopeDealStages($query)
        {
            return $query->where('stage_type', 'deal');
        }
        
        public function scopeOfDealType($query, $type)
        {
            return $query->where('stage_type', 'deal')->where('deal_type', $type);
        }

        public function revertToStage()
        {
            return $this->belongsTo(Stage::class, 'revert_to_stage_id');
        }
}
