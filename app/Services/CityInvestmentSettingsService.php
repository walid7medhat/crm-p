<?php

namespace App\Services;

use App\Models\CityInvestmentSetting;
use App\Models\Investment;

class CityInvestmentSettingsService
{
    private const DEFAULTS = [
        'city' => 'Dubai',
        'purchasePrice' => 600000,
        'downPaymentPercent' => 25,
        'loanInterestPercent' => 5.5,
        'loanYears' => 20,
        'holdYears' => 5,
        'vacancyPercent' => 5,
        'occupancyPercent' => 92,
        'monthlyRent' => 3200,
        'monthlyExpenses' => 1100,
        'annualAppreciationPercent' => 4.5,
        'yearlyRentGrowthPercent' => 2.5,
        'yearlyExpenseGrowthPercent' => 2.2,
        'whatIf' => [
            'rentDeltaPercent' => 0,
            'expenseDeltaPercent' => 0,
            'appreciationDeltaPercent' => 0,
        ],
    ];

    public function all()
    {
        return CityInvestmentSetting::query()->orderBy('city')->get();
    }

    public function getByCity(string $city): ?CityInvestmentSetting
    {
        return CityInvestmentSetting::query()->where('city', $city)->first();
    }

    public function updateCity(string $city, array $payload): CityInvestmentSetting
    {
        return CityInvestmentSetting::query()->updateOrCreate(
            ['city' => $city],
            [
                'purchase_price_min' => $payload['purchase_price_min'] ?? null,
                'purchase_price_max' => $payload['purchase_price_max'] ?? null,
                'down_payment_percent' => $payload['down_payment_percent'],
                'loan_interest_percent' => $payload['loan_interest_percent'],
                'hold_years' => $payload['hold_years'],
                'vacancy_rate_percent' => $payload['vacancy_rate_percent'],
                'is_default' => (bool)($payload['is_default'] ?? false),
            ]
        );
    }

    public function getCalculationConfig(array $input, ?Investment $investment = null): array
    {
        $city = (string)($input['city'] ?? self::DEFAULTS['city']);
        $row = $this->getByCity($city);
        $defaults = array_replace_recursive(self::DEFAULTS, [
            'city' => $city,
            'purchasePrice' => $row?->purchase_price_min ?? self::DEFAULTS['purchasePrice'],
            'downPaymentPercent' => $row?->down_payment_percent ?? self::DEFAULTS['downPaymentPercent'],
            'loanInterestPercent' => $row?->loan_interest_percent ?? self::DEFAULTS['loanInterestPercent'],
            'holdYears' => $row?->hold_years ?? self::DEFAULTS['holdYears'],
            'vacancyPercent' => $row?->vacancy_rate_percent ?? self::DEFAULTS['vacancyPercent'],
        ]);

        $useCityDefaults = filter_var($input['useCityDefaults'] ?? false, FILTER_VALIDATE_BOOL);
        $merged = array_replace_recursive($defaults, $input);

        if ($useCityDefaults) {
            $allowed = ['monthlyRent', 'monthlyExpenses', 'occupancyPercent', 'loanYears', 'annualAppreciationPercent', 'yearlyRentGrowthPercent', 'yearlyExpenseGrowthPercent', 'whatIf'];
            $clean = $defaults;
            foreach ($allowed as $key) {
                if (array_key_exists($key, $input)) {
                    $clean[$key] = $input[$key];
                }
            }
            $merged = array_replace_recursive($clean, ['city' => $city, 'useCityDefaults' => true]);
        }

        if ($investment && !$useCityDefaults) {
            $overrideMap = $investment->overrides()->pluck('overridden_value', 'field_name')->all();
            foreach ($overrideMap as $key => $value) {
                if (array_key_exists($key, $merged)) {
                    $merged[$key] = (float)$value;
                }
            }
        }

        if ($row) {
            $merged['purchasePrice'] = max((float)$row->purchase_price_min, min((float)($merged['purchasePrice'] ?? 0), (float)$row->purchase_price_max));
        }

        return $merged;
    }
}
