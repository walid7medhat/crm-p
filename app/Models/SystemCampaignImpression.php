<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemCampaignImpression extends Model
{
    protected $fillable = [
        'system_campaign_id',
        'user_id',
        'shown_at',
        'dismissed_at',
    ];

    protected $casts = [
        'shown_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(SystemCampaign::class, 'system_campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
