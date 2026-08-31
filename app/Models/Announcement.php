<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    protected $table = 'announcements';
    
    protected $fillable = [
        'title', 'description', 'start_date', 'time', 'end_date',
        'branch_id', 'department_id', 'created_by'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];
    
    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(CompanyBranch::class, 'branch_id');
    }
    
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function views(): HasMany
    {
        return $this->hasMany(AnnouncementView::class);
    }
    
   
    public function isActive(): bool
    {
        $today = now()->toDateString();
        
        if ($this->start_date > $today) {
            return false;
        }
        
        if ($this->end_date && $this->end_date < $today) {
            return false;
        }
        
        return true;
    }
    
 
    /** Users in this announcement's exact branch AND department. */
    public function getTargetUsers()
    {
        if (!$this->branch_id || !$this->department_id) {
            return collect();
        }

        return User::whereHas('employeeProfile', function ($q) {
            $q->where('company_branch_id', $this->branch_id)
              ->where('department_id', $this->department_id);
        })->get();
    }

    public function isTargetedUser($userId)
    {
        $user = User::find($userId);
        if (!$user || !$user->employeeProfile) {
            return false;
        }

        if (!$this->branch_id || !$this->department_id) {
            return false;
        }

        return $user->employeeProfile->company_branch_id == $this->branch_id
            && $user->employeeProfile->department_id == $this->department_id;
    }
    
   
    public function isViewedByUser($userId): bool
    {
        return $this->views()->where('user_id', $userId)->exists();
    }
    
    
    public function markAsViewed($userId): void
    {
        if (!$this->isViewedByUser($userId)) {
            $this->views()->create(['user_id' => $userId]);
        }
    }
   
    public function getViewCount(): int
    {
        return $this->views()->count();
    }
    
  
    /**
     * Restrict to announcements whose scheduled date+time has already
     * arrived. A date-only announcement (no time set) is due as soon as
     * its start_date arrives; a timed one waits until that time of day.
     */
    public function scopeDue($query)
    {
        $now = now();
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');

        return $query->where(function ($q) use ($today, $currentTime) {
            $q->whereDate('start_date', '<', $today)
              ->orWhere(function ($q2) use ($currentTime) {
                  $q2->whereNull('time')->orWhere('time', '<=', $currentTime);
              });
        });
    }

    /**
     * Restrict to announcements targeted at this user's exact branch AND
     * department. A user with no employee profile (or no branch/department
     * on it) sees none — there is no "global" announcement anymore.
     */
    public function scopeForUser($query, $userId)
    {
        $user = User::find($userId);
        $branchId = $user?->employeeProfile?->company_branch_id;
        $departmentId = $user?->employeeProfile?->department_id;

        if (!$branchId || !$departmentId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('branch_id', $branchId)
            ->where('department_id', $departmentId)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhereDate('end_date', '>=', now()->toDateString());
            });
    }
}