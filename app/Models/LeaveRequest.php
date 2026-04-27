<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $table = 'leave_requests';
    
    protected $fillable = [
        'user_id', 'leave_type_id', 'parent_id', 'hr_id', 'start_date', 'end_date',
        'days', 'is_half_day', 'half_day_type', 'reason', 'attachment', 'status',
        'parent_rejection_reason', 'hr_rejection_reason', 'parent_approved_at',
        'hr_approved_at', 'rejected_at'
    ];
    
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_half_day' => 'boolean',
        'parent_approved_at' => 'datetime',
        'hr_approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
    
    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }
    
    public function hr()
    {
        return $this->belongsTo(User::class, 'hr_id');
    }
    
    // Helper methods
    public function isPendingParent()
    {
        return $this->status === 'pending_parent';
    }
    
    public function isPendingHr()
    {
        return $this->status === 'pending_hr';
    }
    
    public function isApproved()
    {
        return $this->status === 'approved';
    }
    
    public function isRejected()
    {
        return $this->status === 'rejected';
    }
    
    public function approveByParent()
    {
        $this->status = 'pending_hr';
        $this->parent_approved_at = now();
        $this->save();
    }
    
    public function approveByHr()
    {
        $this->status = 'approved';
        $this->hr_approved_at = now();
        $this->save();
        
        // Deduct from balance
        $balance = LeaveBalance::where('user_id', $this->user_id)
            ->where('leave_type_id', $this->leave_type_id)
            ->where('year', date('Y'))
            ->first();
        
        if ($balance) {
            $balance->deductBalance($this->days);
        }
    }
    
    public function rejectByParent($reason)
    {
        $this->status = 'rejected';
        $this->parent_rejection_reason = $reason;
        $this->rejected_at = now();
        $this->save();
    }
    
    public function rejectByHr($reason)
    {
        $this->status = 'rejected';
        $this->hr_rejection_reason = $reason;
        $this->rejected_at = now();
        $this->save();
    }
    
    public function cancel()
    {
        if ($this->status === 'pending_parent' || $this->status === 'pending_hr') {
            $this->status = 'cancelled';
            $this->save();
            return true;
        }
        return false;
    }
    
    public function canEdit()
    {
        return $this->status === 'pending_parent';
    }
}