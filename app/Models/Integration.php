<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'form_id',
        'form_name',
        'meta_account_id',
        'access_token',
        'meta_app_id',
        'platform',
        'active',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'active' => 'boolean',
    ];

    protected $hidden = [
        'access_token', // Never expose raw token in JSON
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
