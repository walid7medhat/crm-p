<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Job extends Model
{
    protected $table = 'job_openings';
    
    protected $fillable = [
        'title', 'slug', 'description', 'requirements', 'skills',
        'department_id', 'branch_id', 'hiring_manager_id', 'job_type',
        'status', 'openings', 'posted_date', 'closing_date',
        'custom_questions', 'required_documents'
    ];
    
    protected $casts = [
        'skills' => 'array',
        'custom_questions' => 'array',
        'required_documents' => 'array',
        'posted_date' => 'date',
        'closing_date' => 'date',
    ];
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($job) {
            $job->slug = Str::slug($job->title) . '-' . uniqid();
        });
    }
    
    public function department()
    {
        return $this->belongsTo(Department::class);
    }
    
    public function branch()
    {
        return $this->belongsTo(CompanyBranch::class, 'branch_id');
    }
    
    public function hiringManager()
    {
        return $this->belongsTo(User::class, 'hiring_manager_id');
    }
    
    public function applicants()
    {
        return $this->hasMany(Applicant::class);
    }
    
    public function interviews()
    {
        return $this->hasManyThrough(Interview::class, Applicant::class);
    }
    
    public function scopeOpen($query)
    {
        return $query->where('status', 'open')
            ->where(function($q) {
                $q->whereNull('closing_date')
                  ->orWhere('closing_date', '>=', now());
            });
    }
    
    public function isOpen()
    {
        return $this->status === 'open' && 
               (!$this->closing_date || $this->closing_date >= now());
    }
    public function getRequiredQuestions()
    {
        if (!$this->custom_questions) {
            return [];
        }
        
        return array_filter($this->custom_questions, function($q) {
            return isset($q['required']) && $q['required'] === true;
        });
    }
    
    public function getQuestionsByType($type)
    {
        if (!$this->custom_questions) {
            return [];
        }
        
        return array_filter($this->custom_questions, function($q) use ($type) {
            return ($q['type'] ?? 'text') === $type;
        });
    }
    
    // جلب كل الأسئلة مع تنسيقها
    public function getFormattedQuestions()
    {
        if (!$this->custom_questions) {
            return [];
        }
        
        $formatted = [];
        foreach ($this->custom_questions as $q) {
            $formatted[] = [
                'question' => $q['question'],
                'type' => $q['type'] ?? 'text',
                'required' => $q['required'] ?? false,
                'options' => $q['options'] ?? null,
                'placeholder' => $q['placeholder'] ?? null,
                'min' => $q['min'] ?? null,
                'max' => $q['max'] ?? null,
            ];
        }
        
        return $formatted;
    }
}