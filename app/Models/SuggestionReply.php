<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\Brand\Concerns\BrandOwned;

class SuggestionReply extends Model
{
    use BrandOwned;

    protected $fillable = [
        'suggestion_id',
        'user_id',
        'content',
    ];

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(Suggestion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
