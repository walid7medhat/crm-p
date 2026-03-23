<?php

namespace App\Services;

use App\Models\Investment;

class InvestmentCalculationService
{
    public function __construct(
        private readonly CityInvestmentSettingsService $citySettingsService,
        private readonly AbuDhabiBenchmarkService $benchmarkService
    )
    {
    }

    public function calculate(array $input, ?Investment $investment = null): array
    {
        $normalized = $this->normalizeInput($this->citySettingsService->getCalculationConfig($input, $investment));

        $purchasePrice = $normalized['purchasePrice'];
        $downPaymentPercent = $normalized['downPaymentPercent'];
        $loanInterestPercent = $normalized['loanInterestPercent'];
        $loanYears = $normalized['loanTermYears'];
        $monthlyRent = $normalized['annualRent'] ? ($normalized['annualRent'] / 12) : $normalized['monthlyRent'];
        $area = $normalized['area'];
        $propertyType = $normalized['propertyType'];
        $occupancyPercent = $normalized['occupancyPercent'];
        $vacancyPercent = $normalized['vacancyPercent'];
        $monthlyExpenses = $normalized['monthlyExpenses'];
        $operatingExpensesPercent = $normalized['operatingExpensesPercent'];
        $annualAppreciationPercent = $normalized['annualAppreciationPercent'];
        $yearlyRentGrowthPercent = $normalized['yearlyRentGrowthPercent'];
        $yearlyExpenseGrowthPercent = $normalized['yearlyExpenseGrowthPercent'];
        $holdYears = $normalized['holdYears'];
        $rentDelta = $normalized['whatIf']['rentDeltaPercent'];
        $expenseDelta = $normalized['whatIf']['expenseDeltaPercent'];
        $appreciationDelta = $normalized['whatIf']['appreciationDeltaPercent'];
        $priceDelta = $normalized['whatIf']['priceDeltaPercent'];

        $purchasePrice = $purchasePrice * (1 + $priceDelta / 100);

        // Step 1: Equity & Loan
        $equity = $purchasePrice * ($downPaymentPercent / 100);
        $loanPrincipal = $purchasePrice - $equity;
        $monthlyRate = $loanInterestPercent / 100 / 12;
        $numberOfPayments = max(1, $loanYears * 12);
        $monthlyMortgagePayment = $monthlyRate > 0
            ? ($loanPrincipal * $monthlyRate) / (1 - pow(1 + $monthlyRate, -$numberOfPayments))
            : $loanPrincipal / $numberOfPayments;

        // Step 2: Rental Income
        $adjustedMonthlyRent = $monthlyRent * (1 + $rentDelta / 100);
        $effectiveOccupancyPercent = max(0, min(100, $occupancyPercent - $vacancyPercent));
        $effectiveMonthlyIncome = $adjustedMonthlyRent * ($effectiveOccupancyPercent / 100);
        $grossRentAnnual = $adjustedMonthlyRent * 12;
        $effectiveRentAnnual = $effectiveMonthlyIncome * 12;
        $vacancyLossAnnual = $grossRentAnnual - $effectiveRentAnnual;

        // Step 3: Expenses (percent-first, fallback to monthly amount if explicitly provided)
        $operatingExpensesAnnualFromPercent = $effectiveRentAnnual * ($operatingExpensesPercent / 100);
        $adjustedMonthlyExpenses = $monthlyExpenses * (1 + $expenseDelta / 100);
        $operatingExpensesAnnual = $monthlyExpenses > 0
            ? ($adjustedMonthlyExpenses * 12)
            : $operatingExpensesAnnualFromPercent;

        // Step 4: Debt Service (amortized)
        $annualDebtService = $monthlyMortgagePayment * 12;
        $noiAnnual = $effectiveRentAnnual - $operatingExpensesAnnual;
        $dscr = $annualDebtService > 0 ? ($noiAnnual / $annualDebtService) : 0;

        // Step 5: Net Cashflow
        $annualCashFlow = $effectiveRentAnnual - $operatingExpensesAnnual - $annualDebtService;

        $appreciationPercent = max(2.0, min(6.0, $annualAppreciationPercent + $appreciationDelta));
        $futureValue = $purchasePrice * pow(1 + $appreciationPercent / 100, $holdYears);
        $capitalGain = $futureValue - $purchasePrice;

        // Step 6/7/8 totals over hold
        $totalCashFlow = $annualCashFlow * $holdYears;
        $totalNetRentalIncomeHold = $noiAnnual * $holdYears;
        $totalProfit = $totalCashFlow + $capitalGain;

        // ROI definitions (explicit and consistent)
        $cashRoiPercent = $equity > 0 ? ($annualCashFlow / $equity) * 100 : 0; // annual cash-on-cash
        $leveragedRoiPercent = $equity > 0 ? ($totalProfit / $equity) * 100 : 0; // hold-period total return on equity
        $totalRoiPercent = ($purchasePrice > 0 && $holdYears > 0)
            ? ((($totalNetRentalIncomeHold + $capitalGain) / $purchasePrice) * 100) / $holdYears
            : 0; // annualized unlevered ROI
        $expenseRatio = $effectiveRentAnnual > 0 ? ($operatingExpensesAnnual / $effectiveRentAnnual) * 100 : 0;
        $breakEvenYears = $annualCashFlow > 0 ? ($equity / $annualCashFlow) : null;
        $loanBurdenPercent = $effectiveRentAnnual > 0 ? ($annualDebtService / $effectiveRentAnnual) * 100 : 0;
        $ltvPercent = $purchasePrice > 0 ? ($loanPrincipal / $purchasePrice) * 100 : 0;
        $annualYieldPercent = $purchasePrice > 0 ? (($effectiveRentAnnual - $operatingExpensesAnnual) / $purchasePrice) * 100 : 0;

        $annualProjection = [];
        $yearlyRent = $monthlyRent * 12;
        $yearlyExpenses = $monthlyExpenses * 12;
        $cashFlows = [-$equity];

        for ($year = 1; $year <= $holdYears; $year++) {
            $adjustedRentYearly = $yearlyRent * ($effectiveOccupancyPercent / 100) * (1 + $rentDelta / 100);
            $adjustedExpYearly = $yearlyExpenses > 0
                ? ($yearlyExpenses * (1 + $expenseDelta / 100))
                : ($adjustedRentYearly * ($operatingExpensesPercent / 100));
            $cashFlow = $adjustedRentYearly - $adjustedExpYearly - $annualDebtService;
            $roi = $equity > 0 ? (($cashFlow * $year) / $equity) * 100 : 0;

            $annualProjection[] = [
                'year' => $year,
                'rent' => round($adjustedRentYearly, 2),
                'expenses' => round($adjustedExpYearly, 2),
                'cashFlow' => round($cashFlow, 2),
                'roi' => round($roi, 2),
            ];
            $cashFlows[] = $cashFlow;
            $yearlyRent *= 1 + $yearlyRentGrowthPercent / 100;
            $yearlyExpenses *= 1 + $yearlyExpenseGrowthPercent / 100;
        }

        $cashFlows[count($cashFlows) - 1] += $capitalGain;
        $irrPercent = $this->computeIrrPercent($cashFlows);

        $risk = $this->calculateRisk($annualCashFlow, $vacancyPercent, $dscr, $ltvPercent);
        $scenarioRows = $this->buildScenarios($totalRoiPercent, $annualCashFlow, $risk['label']);
        $bestScenarioName = collect($scenarioRows)->sortByDesc('roi')->first()['name'] ?? null;
        $benchmark = $this->benchmarkService->evaluate($annualYieldPercent, $area, $propertyType);
        $riskFlags = $this->buildRiskFlags($annualCashFlow, $vacancyPercent, $dscr, $ltvPercent, $loanBurdenPercent);
        $dealScore = $this->calculateDealScore(
            $totalRoiPercent,
            $annualCashFlow,
            $ltvPercent,
            $dscr,
            $benchmark['area']['label']
        );

        $aiInsights = [];
        if ($expenseRatio > 40) {
            $aiInsights[] = [
                'type' => 'warning',
                'title' => 'Expense Pressure',
                'message' => 'High expenses reduce ROI by ' . number_format($expenseRatio - 40, 1) . '%',
            ];
        }
        if ($effectiveOccupancyPercent < 90) {
            $aiInsights[] = [
                'type' => 'info',
                'title' => 'Occupancy Opportunity',
                'message' => 'Lower vacancy can significantly improve annual cash flow and valuation.',
            ];
        }
        if ($annualCashFlow < 0) {
            $aiInsights[] = [
                'type' => 'critical',
                'title' => 'Negative Cash Flow',
                'message' => 'Revisit financing structure or operating costs to avoid liquidity risk.',
            ];
        }
        if (!empty($riskFlags)) {
            foreach ($riskFlags as $warning) {
                $aiInsights[] = [
                    'type' => 'critical',
                    'title' => 'Risk Signal',
                    'message' => $warning['reason'],
                ];
            }
        }
        if (empty($aiInsights)) {
            $aiInsights[] = [
                'type' => 'positive',
                'title' => 'Healthy Investment Profile',
                'message' => 'Current assumptions indicate strong unit economics and resilient returns.',
            ];
        }

        $metricCards = [
            [
                'key' => 'totalROI',
                'label' => 'Total ROI (Annualized)',
                'rawValue' => round($totalRoiPercent, 2),
                'valueType' => 'percent',
                'value' => $this->formatPercent($totalRoiPercent),
                'sub' => 'Annualized (net rental + appreciation) / purchase price',
                'trend' => $totalRoiPercent >= 12 ? 'up' : 'down',
            ],
            [
                'key' => 'cashROI',
                'label' => 'Cash ROI (Annual)',
                'rawValue' => round($cashRoiPercent, 2),
                'valueType' => 'percent',
                'value' => $this->formatPercent($cashRoiPercent),
                'sub' => 'Annual net cashflow / equity',
                'trend' => $cashRoiPercent >= 8 ? 'up' : 'down',
            ],
            [
                'key' => 'leveragedROI',
                'label' => 'Leveraged ROI (Hold)',
                'rawValue' => round($leveragedRoiPercent, 2),
                'valueType' => 'percent',
                'value' => $this->formatPercent($leveragedRoiPercent),
                'sub' => 'Total profit / equity (leverage sensitive)',
                'trend' => $leveragedRoiPercent >= 14 ? 'up' : 'down',
                'warning' => $ltvPercent > 75 || $dscr < 1.0,
            ],
            [
                'key' => 'annualCashFlow',
                'label' => 'Net Cashflow (Annual)',
                'rawValue' => round($annualCashFlow, 2),
                'valueType' => 'currency',
                'value' => $this->formatCurrency($annualCashFlow),
                'sub' => 'Effective rent - OpEx - Debt service',
                'trend' => $annualCashFlow >= 0 ? 'up' : 'down',
            ],
            [
                'key' => 'totalProfit',
                'label' => 'Total Profit',
                'rawValue' => round($totalProfit, 2),
                'valueType' => 'currency',
                'value' => $this->formatCurrency($totalProfit),
                'sub' => $holdYears . '-year horizon',
                'trend' => $totalProfit >= 0 ? 'up' : 'down',
            ],
        ];

        return [
            'input' => $normalized,
            'cash_roi' => round($cashRoiPercent, 2),
            'total_roi' => round($totalRoiPercent, 2),
            'leveraged_roi' => round($leveragedRoiPercent, 2),
            'net_cashflow_annual' => round($annualCashFlow, 2),
            'total_profit' => round($totalProfit, 2),
            'break_even_years' => $breakEvenYears !== null ? round($breakEvenYears, 2) : null,
            'risk_notes' => !empty($riskFlags)
                ? implode(' | ', collect($riskFlags)->pluck('reason')->values()->all())
                : 'Assumptions-based model. Validate with market comps and financing terms.',
            'roi' => [
                'cash_roi' => round($cashRoiPercent, 2),
                'leveraged_roi' => round($leveragedRoiPercent, 2),
                'total_roi' => round($totalRoiPercent, 2),
            ],
            'cashflow' => [
                'monthly' => round($annualCashFlow / 12, 2),
                'annual' => round($annualCashFlow, 2),
                'breakdown' => [
                    'rent' => round($grossRentAnnual, 2),
                    'vacancy_loss' => round($vacancyLossAnnual, 2),
                    'expenses' => round($operatingExpensesAnnual, 2),
                    'debt_service' => round($annualDebtService, 2),
                ],
            ],
            'hold_period' => $holdYears,
            'deal_score' => [
                'score' => $dealScore['score'],
                'label' => $dealScore['label'],
            ],
            'benchmark' => [
                'status' => $benchmark['overall']['label'],
                'area' => $benchmark['area_key'],
                'city' => 'Abu Dhabi',
            ],
            'risk_flags' => $riskFlags,
            'ai_insights' => [
                'city' => 'Abu Dhabi',
                'area' => $benchmark['area_key'],
                'property_type' => $benchmark['property_type_key'],
                'summary' => $this->buildAiSummary($dealScore['label'], $riskFlags, $benchmark['overall']['label']),
            ],
            'derived' => [
                'equity' => round($equity, 2),
                'loanAmount' => round($loanPrincipal, 2),
                'grossRentAnnual' => round($grossRentAnnual, 2),
                'effectiveRentAnnual' => round($effectiveRentAnnual, 2),
                'annualIncome' => round($effectiveRentAnnual, 2),
                'operatingExpensesAnnual' => round($operatingExpensesAnnual, 2),
                'annualExpenses' => round($operatingExpensesAnnual, 2),
                'annualDebtService' => round($annualDebtService, 2),
                'annualMortgage' => round($annualDebtService, 2),
                'annualCashFlow' => round($annualCashFlow, 2),
                'capitalGain' => round($capitalGain, 2),
                'appreciationGain' => round($capitalGain, 2),
                'totalProfit' => round($totalProfit, 2),
                'totalNetRentalIncomeHold' => round($totalNetRentalIncomeHold, 2),
                'cashROI' => round($cashRoiPercent, 2),
                'totalROI' => round($totalRoiPercent, 2),
                'leveragedROI' => round($leveragedRoiPercent, 2),
                'netROI' => round($totalRoiPercent, 2),
                'grossROI' => round($cashRoiPercent, 2),
                'expenseRatio' => round($expenseRatio, 2),
                'effectiveOccupancyPercent' => round($effectiveOccupancyPercent, 2),
                'irrPercent' => round($irrPercent, 2),
                'breakEvenYears' => $breakEvenYears !== null ? round($breakEvenYears, 2) : null,
                'dscr' => round($dscr, 3),
                'ltvPercent' => round($ltvPercent, 2),
                'loanBurdenPercent' => round($loanBurdenPercent, 2),
                'annualYieldPercent' => round($annualYieldPercent, 2),
            ],
            'risk' => $risk,
            'metricCards' => $metricCards,
            'annualProjection' => $annualProjection,
            'scenarioRows' => $scenarioRows,
            'bestScenarioName' => $bestScenarioName,
            'aiInsights' => $aiInsights,
            'chartSeries' => [
                'cashFlow' => [[
                    'name' => 'Cash Flow',
                    'data' => collect($annualProjection)->pluck('cashFlow')->map(fn($v) => round($v))->all(),
                ]],
                'roiCompare' => [[
                    'name' => 'ROI %',
                    'data' => collect($scenarioRows)->pluck('roi')->map(fn($v) => round($v, 2))->all(),
                ]],
                'costBreakdown' => [max(0, round($operatingExpensesAnnual)), max(0, round($annualDebtService))],
            ],
            'benchmark_detail' => $benchmark,
            'dealScore' => [
                'value' => $dealScore['score'],
                'label' => $dealScore['label'],
            ],
            'modelWarnings' => collect($riskFlags)->pluck('reason')->values()->all(),
            'assumptionsDisclaimer' => 'Assumptions based model. ROI types are shown separately (cash, total, leveraged).',
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    private function normalizeInput(array $input): array
    {
        return [
            'city' => 'Abu Dhabi',
            'area' => in_array(($input['area'] ?? ''), AbuDhabiBenchmarkService::ALLOWED_AREAS, true)
                ? $input['area']
                : AbuDhabiBenchmarkService::ALLOWED_AREAS[0],
            'propertyType' => in_array(($input['propertyType'] ?? ''), AbuDhabiBenchmarkService::ALLOWED_PROPERTY_TYPES, true)
                ? $input['propertyType']
                : AbuDhabiBenchmarkService::ALLOWED_PROPERTY_TYPES[0],
            'useCityDefaults' => filter_var($input['useCityDefaults'] ?? false, FILTER_VALIDATE_BOOL),
            'purchasePrice' => (float)($input['purchasePrice'] ?? 4000000),
            'downPaymentPercent' => (float)($input['downPaymentPercent'] ?? 20),
            'loanInterestPercent' => (float)($input['loanInterestPercent'] ?? 5.25),
            'loanYears' => (int)($input['loanYears'] ?? 20),
            'loanTermYears' => (int)($input['loanTermYears'] ?? $input['loanYears'] ?? 25),
            'monthlyRent' => (float)($input['monthlyRent'] ?? 16000),
            'annualRent' => isset($input['annualRent']) ? (float)$input['annualRent'] : null,
            'occupancyPercent' => (float)($input['occupancyPercent'] ?? 95.5),
            'vacancyPercent' => (float)($input['vacancyPercent'] ?? 4.5),
            'operatingExpensesPercent' => (float)($input['operatingExpensesPercent'] ?? 30),
            'monthlyExpenses' => (float)($input['monthlyExpenses'] ?? 1500),
            'annualAppreciationPercent' => (float)($input['annualAppreciationPercent'] ?? 4.0),
            'yearlyRentGrowthPercent' => (float)($input['yearlyRentGrowthPercent'] ?? 2.5),
            'yearlyExpenseGrowthPercent' => (float)($input['yearlyExpenseGrowthPercent'] ?? 2.2),
            'holdYears' => (int)($input['holdYears'] ?? 10),
            'whatIf' => [
                'rentDeltaPercent' => (float)($input['whatIf']['rentDeltaPercent'] ?? 0),
                'expenseDeltaPercent' => (float)($input['whatIf']['expenseDeltaPercent'] ?? 0),
                'appreciationDeltaPercent' => (float)($input['whatIf']['appreciationDeltaPercent'] ?? 0),
                'priceDeltaPercent' => (float)($input['whatIf']['priceDeltaPercent'] ?? 0),
            ],
        ];
    }

    private function calculateRisk(float $annualCashFlow, float $vacancyPercent, float $dscr, float $ltvPercent): array
    {
        $score = 0;
        $score += $annualCashFlow < 0 ? 30 : 8;
        $score += $vacancyPercent > 7 ? 24 : ($vacancyPercent > 5 ? 14 : 8);
        $score += $dscr < 1.0 ? 28 : ($dscr < 1.2 ? 18 : 8);
        $score += $ltvPercent > 80 ? 24 : ($ltvPercent > 70 ? 14 : 8);

        $normalized = min(100, max(0, $score));
        if ($dscr < 1.0) {
            return ['label' => 'High Risk', 'color' => 'danger', 'value' => max(70, $normalized)];
        }
        if ($normalized < 35) {
            return ['label' => 'Low Risk', 'color' => 'success', 'value' => $normalized];
        }
        if ($normalized < 70) {
            return ['label' => 'Medium Risk', 'color' => 'warning', 'value' => $normalized];
        }
        return ['label' => 'High Risk', 'color' => 'danger', 'value' => $normalized];
    }

    private function buildRiskFlags(float $annualCashFlow, float $vacancyPercent, float $dscr, float $ltvPercent, float $loanBurdenPercent): array
    {
        $flags = [];
        if ($annualCashFlow < 0) {
            $flags[] = ['type' => 'LIQUIDITY_RISK', 'reason' => 'Annual cashflow is negative under current assumptions.'];
        }
        if ($vacancyPercent > 7) {
            $flags[] = ['type' => 'DEMAND_RISK', 'reason' => 'Vacancy assumption is elevated versus Abu Dhabi market baseline.'];
        }
        if ($dscr < 1.0) {
            $flags[] = ['type' => 'DEBT_STRESS', 'reason' => 'Debt service coverage ratio is below 1.0, indicating debt strain.'];
        } elseif ($dscr < 1.2) {
            $flags[] = ['type' => 'DEBT_STRESS', 'reason' => 'Debt service coverage ratio is tight (<1.2).'];
        }
        if ($ltvPercent > 80) {
            $flags[] = ['type' => 'LEVERAGE_RISK', 'reason' => 'Leverage is high (LTV > 80%).'];
        }
        if ($loanBurdenPercent > 65) {
            $flags[] = ['type' => 'DEBT_STRESS', 'reason' => 'Debt service consumes a high share of effective rent.'];
        }
        if ($dscr >= 1.0 && $annualCashFlow < 0) {
            $flags[] = ['type' => 'CONSISTENCY_CHECK', 'reason' => 'Negative cashflow with passable DSCR indicates thin underwriting margin.'];
        }
        return $flags;
    }

    private function calculateDealScore(
        float $totalRoiPercent,
        float $annualCashFlow,
        float $ltvPercent,
        float $dscr,
        string $areaBenchmarkLabel
    ): array {
        // Required weighted model:
        // Cashflow 35%, ROI 25%, DSCR 25%, LTV 10%, Benchmark 5%
        $cashflowComponent = $annualCashFlow >= 0 ? 100 : max(0, 100 - (abs($annualCashFlow) / 180000) * 100); // 35%
        $roiComponent = max(0, min(100, ($totalRoiPercent / 12) * 100)); // 25%
        $dscrComponent = $dscr >= 1.35 ? 100 : ($dscr >= 1.0 ? (70 + (($dscr - 1.0) / 0.35) * 30) : max(0, $dscr * 70)); // 25%
        $leverageComponent = $ltvPercent <= 65 ? 100 : max(0, 100 - ($ltvPercent - 65) * 6); // 10%
        $benchmarkComponent = $areaBenchmarkLabel === 'Above Market'
            ? 100
            : ($areaBenchmarkLabel === 'Below Market' ? 40 : 70); // 5%

        $score = ($cashflowComponent * 0.35)
            + ($roiComponent * 0.25)
            + ($dscrComponent * 0.25)
            + ($leverageComponent * 0.10)
            + ($benchmarkComponent * 0.05);
        $score = round(max(0, min(100, $score)), 1);

        // Hard caps/rules
        if ($annualCashFlow < 0 && $dscr < 1.0) {
            $score = min($score, 69.0);
        }
        if ($dscr < 1.0) {
            $score = min($score, 79.0);
        }
        if ($annualCashFlow < 0) {
            $score = min($score, 75.0);
        }

        $label = $score >= 80 && $annualCashFlow >= 0 && $dscr >= 1.0
            ? 'Excellent 🔥'
            : ($score >= 60 ? 'Good 👍' : 'Risky ⚠️');
        return ['score' => $score, 'label' => $label];
    }

    private function buildScenarios(float $baseRoi, float $baseCashFlow, string $riskLabel): array
    {
        return [
            ['name' => 'Conservative', 'roi' => round($baseRoi - 7.5, 2), 'cashFlow' => round($baseCashFlow * 0.84, 2), 'risk' => 'High'],
            ['name' => 'Realistic', 'roi' => round($baseRoi, 2), 'cashFlow' => round($baseCashFlow, 2), 'risk' => str_replace(' Risk', '', $riskLabel)],
            ['name' => 'Optimistic', 'roi' => round($baseRoi + 9.2, 2), 'cashFlow' => round($baseCashFlow * 1.23, 2), 'risk' => 'Medium'],
        ];
    }

    private function buildAiSummary(string $dealLabel, array $riskFlags, string $benchmarkStatus): string
    {
        if (empty($riskFlags)) {
            return "Deal classified as {$dealLabel} with {$benchmarkStatus} benchmark positioning.";
        }

        $topReasons = array_slice(array_map(static fn ($flag) => $flag['reason'], $riskFlags), 0, 2);

        return "Deal classified as {$dealLabel}. Key risks: " . implode(' ', $topReasons);
    }

    private function computeIrrPercent(array $cashFlows): float
    {
        $rate = 0.1;
        for ($i = 0; $i < 100; $i++) {
            $npv = 0.0;
            $dNpv = 0.0;
            foreach ($cashFlows as $t => $cf) {
                $div = pow(1 + $rate, $t);
                $npv += $cf / $div;
                if ($t > 0) {
                    $dNpv -= ($t * $cf) / pow(1 + $rate, $t + 1);
                }
            }
            if (abs($dNpv) < 1e-9) {
                break;
            }
            $newRate = $rate - $npv / $dNpv;
            if (abs($newRate - $rate) < 1e-7) {
                $rate = $newRate;
                break;
            }
            $rate = $newRate;
        }

        return $rate * 100;
    }

    private function formatPercent(float $value): string
    {
        return number_format($value, 2) . '%';
    }

    private function formatCurrency(float $value): string
    {
        return '$' . number_format($value, 0);
    }
}
