<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationSection extends Model
{
    protected $fillable = ['title', 'question_type', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function questions()
    {
        return $this->hasMany(EvaluationQuestion::class)->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
