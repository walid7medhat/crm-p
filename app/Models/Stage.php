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
          'status_revert_rules' => 'array',
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
             if ($user->hasAnyRole([ 'super_admin']) || $user->id==33 || $user->id ==30) {
             }
            elseif ($user->hasAnyRole(['manager', 'team_lead','admin', 'branch_admin']) || $user->seesBranchLeads()) {
                $subordinatesIds = $user->leadScopeUserIds();

                // Same rule as the lead list / kanban: current responsible person only (a lead
                // reassigned to another branch leaves this count), + Shared Leads shared with
                // the viewer's branch and branch_admin's unassigned New Leads.
                $user->applyLeadResponsibleScope($leads, array_merge($subordinatesIds, [$user->id]));
            }
            else {
                $leads = $leads->where('responsible_person_id', $user->id);
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
