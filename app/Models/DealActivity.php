<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasActivityReminders;

class DealActivity extends Model
{
    //
       use HasFactory; use HasActivityReminders;
    protected $guarded=[];
       protected $casts = [
        'reminder_date' => 'datetime',
        'is_completed' => 'boolean',
        'reminders' => 'array',
        'next_reminder_at' => 'datetime',
        'last_reminded_at' => 'datetime',
    ];
    


    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('is_completed', false);
    }

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('reminder_date', '>', now())->pending();
    }

    public function scopeOverdue($query)
    {
        return $query->where('reminder_date', '<', now())->pending();
    }
}
