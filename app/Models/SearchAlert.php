<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class SearchAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'filters',
        'is_active'
    ];

    protected $casts = [
        'filters' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
