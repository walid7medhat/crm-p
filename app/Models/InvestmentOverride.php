<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentOverride extends Model
{
    use \App\Support\Brand\Concerns\BrandOwned;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'overridden_value' => 'float',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}
