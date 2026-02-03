<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class LeadActivity extends Model
{
    //
       use HasFactory;
    protected $guarded=[];
       protected $casts = [
        'reminder_date' => 'datetime',
        'is_completed' => 'boolean',
        'reminders' => 'array',
        'next_reminder_at' => 'datetime',
        'last_reminded_at' => 'datetime',
    ];
    


    public function lead()
    {
        return $this->belongsTo(Lead::class);
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
    public function calculateNextReminder()
    {
        if (empty($this->reminders)) {
            $this->next_reminder_at = null;
            return;
        }
    
        $times = collect($this->reminders)
            ->map(fn ($minutes) =>
                Carbon::parse($this->reminder_date)->subMinutes($minutes)
            )
            ->filter(fn ($time) => $time->isFuture())
            ->sort()
            ->values();
    
        $this->next_reminder_at = $times->first();
    }
}
