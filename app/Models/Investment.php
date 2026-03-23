<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Investment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'input_payload' => 'array',
            'latest_calculation_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scenarioVersions(): HasMany
    {
        return $this->hasMany(InvestmentScenarioVersion::class);
    }

    public function currentScenarioVersions(): HasMany
    {
        return $this->scenarioVersions()->where('is_current', true)->latest('updated_at');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(InvestmentOverride::class);
    }
}
