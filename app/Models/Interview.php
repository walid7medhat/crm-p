<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Interview extends Model
{
    protected $table = 'interviews';
    
    protected $fillable = [
        'applicant_id', 'job_id', 'interviewer_id', 'scheduled_at', 'end_time',
        'type', 'location', 'meeting_link', 'feedback', 'rating', 'status'
    ];
    
    protected $casts = [
        'scheduled_at' => 'datetime',
        'end_time' => 'datetime',
    ];
    
    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }
    
    public function job()
    {
        return $this->belongsTo(Job::class);
    }
    
    public function interviewer()
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }
    
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }
    
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}