<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Applicant extends Model
{
    protected $table = 'applicants';
    
    protected $fillable = [
        'job_id', 'full_name', 'email', 'phone', 'nationality', 'date_of_birth',
        'gender', 'visa_status', 'notice_period_days', 'total_experience_years',
        'experience_in_uae_years', 'current_salary', 'expected_salary',
        'resume_path', 'cover_letter_path', 'additional_notes', 'answers',
        'status', 'rejection_reason', 'applied_at'
    ];
    
    protected $casts = [
        'date_of_birth' => 'date',
        'answers' => 'array',
        'applied_at' => 'datetime',
        'current_salary' => 'decimal:2',
        'expected_salary' => 'decimal:2',
    ];
    
    public function job()
    {
        return $this->belongsTo(Job::class);
    }
    
    public function interviews()
    {
        return $this->hasMany(Interview::class);
    }
    
    public function currentInterview()
    {
        return $this->hasOne(Interview::class)->where('status', 'scheduled')->latest();
    }
    
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
    
    public function scopeShortlisted($query)
    {
        return $query->where('status', 'shortlisted');
    }
}