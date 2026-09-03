<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    protected $fillable = [
        'user_id', 'evaluator_id', 'milestone_months', 'period_number',
        'designation_name_snapshot', 'status', 'submitted_at', 'pdf_path',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    protected $appends = ['pdf_url'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function answers()
    {
        return $this->hasMany(EvaluationAnswer::class);
    }

    public function getPdfUrlAttribute(): ?string
    {
        return $this->pdf_path ? asset('storage/' . $this->pdf_path) : null;
    }
}
