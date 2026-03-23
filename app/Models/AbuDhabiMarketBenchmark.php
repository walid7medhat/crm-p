<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbuDhabiMarketBenchmark extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'avg_roi_percent' => 'float',
            'avg_vacancy_percent' => 'float',
            'avg_risk_score' => 'float',
        ];
    }
}
