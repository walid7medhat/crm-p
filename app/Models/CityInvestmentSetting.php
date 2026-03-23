<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CityInvestmentSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'purchase_price_min' => 'float',
            'purchase_price_max' => 'float',
            'down_payment_percent' => 'float',
            'loan_interest_percent' => 'float',
            'vacancy_rate_percent' => 'float',
            'hold_years' => 'integer',
            'is_default' => 'boolean',
        ];
    }
}
