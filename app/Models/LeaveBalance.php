<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    protected $fillable = ['user_id', 'leave_type_id', 'total_days', 'used_days', 'remaining_days', 'year'];
    
    protected $casts = [
        'total_days' => 'decimal:2',
        'used_days' => 'decimal:2',
        'remaining_days' => 'decimal:2',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
    
    public function hasEnoughBalance($days)
    {
        return $this->remaining_days >= $days;
    }
    
    public function deductBalance($days)
    {
        $this->used_days += $days;
        $this->remaining_days = $this->total_days - $this->used_days;
        $this->save();
    }
    
    public function addBalance($days)
    {
        $this->used_days -= $days;
        $this->remaining_days = $this->total_days - $this->used_days;
        $this->save();
    }
}