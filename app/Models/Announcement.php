<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    protected $table = 'announcements';
    
    protected $fillable = [
        'title', 'description', 'start_date', 'end_date',
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
    
 
    public function getTargetUsers()
    {
        $query = User::whereHas('employeeProfile');
        
        if ($this->branch_id && $this->department_id) {
            $query->whereHas('employeeProfile', function($q) {
                $q->where('company_branch_id', $this->branch_id)
                  ->where('department_id', $this->department_id);
            });
        }
        elseif ($this->branch_id) {
            $query->whereHas('employeeProfile', function($q) {
                $q->where('company_branch_id', $this->branch_id);
            });
        }
        elseif ($this->department_id) {
            $query->whereHas('employeeProfile', function($q) {
                $q->where('department_id', $this->department_id);
            });
        }

        return $query->get();
    }
  
    public function isTargetedUser($userId)
    {
        $user = User::find($userId);
        if (!$user || !$user->employeeProfile) {
            return false;
        }
        
        $employeeProfile = $user->employeeProfile;
        
        if (!$this->branch_id && !$this->department_id) {
            return true;
        }
        
        if ($this->branch_id && $this->department_id) {
            return $employeeProfile->company_branch_id == $this->branch_id && 
                   $employeeProfile->department_id == $this->department_id;
        }
        
        if ($this->branch_id) {
            return $employeeProfile->company_branch_id == $this->branch_id;
        }
        
        if ($this->department_id) {
            return $employeeProfile->department_id == $this->department_id;
        }
        
        return false;
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
    
  
    public function scopeForUser($query, $userId)
    {
        $user = User::find($userId);
        
        if (!$user || !$user->employeeProfile) {
            return $query;
        }
        
        $branchId = $user->employeeProfile->company_branch_id;
        $departmentId = $user->employeeProfile->department_id;
        
        return $query->where(function($q) use ($branchId, $departmentId) {
            $q->whereNull('branch_id')
              ->whereNull('department_id');
            
            if ($branchId) {
                $q->orWhere(function($q2) use ($branchId) {
                    $q2->where('branch_id', $branchId)
                       ->whereNull('department_id');
                });
            }
            
            if ($departmentId) {
                $q->orWhere(function($q2) use ($departmentId) {
                    $q2->whereNull('branch_id')
                       ->where('department_id', $departmentId);
                });
            }
            
            if ($branchId && $departmentId) {
                $q->orWhere(function($q2) use ($branchId, $departmentId) {
                    $q2->where('branch_id', $branchId)
                       ->where('department_id', $departmentId);
                });
            }
        })->whereDate('start_date', '<=', now()->toDateString())
          ->where(function($q) {
              $q->whereNull('end_date')
                ->orWhereDate('end_date', '>=', now()->toDateString());
          });
    }
}