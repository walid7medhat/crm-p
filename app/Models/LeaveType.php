<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = ['name', 'slug', 'payment_type', 'default_days', 'requires_attachment', 'is_active', 'sort_order'];
    
    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
    
    public function balances()
    {
        return $this->hasMany(LeaveBalance::class);
    }
}