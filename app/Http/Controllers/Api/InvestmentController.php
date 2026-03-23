<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\InvestmentScenarioVersion;
use App\Services\CityInvestmentSettingsService;
use App\Services\InvestmentCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class InvestmentController extends Controller
{
    public function __construct(
        private readonly InvestmentCalculationService $calculationService,
        private readonly CityInvestmentSettingsService $citySettingsService
    )
    {
    }

    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'input' => 'required|array',
        ]);

        $result = $this->calculationService->calculate($request->input('input', []));
        return ApiResponse::success($result, 'Investment calculated successfully');
    }

    public function index(Request $request): JsonResponse
    {
        $investments = Investment::query()
            ->where('user_id', $request->user()->id)
            ->with(['currentScenarioVersions' => fn($q) => $q->orderByDesc('version')])
            ->latest('updated_at')
            ->paginate(15);

        return ApiResponse::success($investments, 'Investments retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'currency' => 'nullable|string|max:8',
            'input' => 'required|array',
            'scenario_name' => 'nullable|string|max:120',
            'scenario_key' => 'nullable|string|max:100',
        ]);

        $input = $request->input('input', []);
        $calculation = $this->calculationService->calculate($input);

        $investment = Investment::query()->create([
            'user_id' => $request->user()->id,
            'name' => $request->string('name')->toString(),
            'description' => $request->input('description'),
            'currency' => strtoupper($request->input('currency', 'USD')),
            'input_payload' => $input,
            'latest_calculation_payload' => $calculation,
        ]);

        $scenarioKey = $request->input('scenario_key') ?: Str::slug($request->input('scenario_name', 'base-scenario'));
        $scenarioName = $request->input('scenario_name', 'Base Scenario');
        $this->createScenarioVersion($investment, $scenarioKey, $scenarioName, $input, $calculation, $request->user()->id);
        $this->syncInvestmentOverrides($investment, $input);

        return ApiResponse::success(
            $investment->load(['currentScenarioVersions' => fn($q) => $q->orderByDesc('version')]),
            'Investment saved successfully',
            201
        );
    }

    public function show(Request $request, Investment $investment): JsonResponse
    {
        $this->authorizeOwnership($request, $investment);
        $investment->load(['scenarioVersions' => fn($q) => $q->orderByDesc('created_at')]);
        return ApiResponse::success($investment, 'Investment loaded successfully');
    }

    public function storeScenarioVersion(Request $request, Investment $investment): JsonResponse
    {
        $this->authorizeOwnership($request, $investment);

        $request->validate([
            'scenario_key' => 'required|string|max:100',
            'scenario_name' => 'required|string|max:120',
            'input' => 'required|array',
        ]);

        $scenarioKey = $request->string('scenario_key')->toString();
        $scenarioName = $request->string('scenario_name')->toString();
        $input = $request->input('input', []);
        $calculation = $this->calculationService->calculate($input, $investment);

        $version = ((int) InvestmentScenarioVersion::query()
            ->where('investment_id', $investment->id)
            ->where('scenario_key', $scenarioKey)
            ->max('version')) + 1;

        InvestmentScenarioVersion::query()
            ->where('investment_id', $investment->id)
            ->where('scenario_key', $scenarioKey)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        $record = InvestmentScenarioVersion::query()->create([
            'investment_id' => $investment->id,
            'created_by' => $request->user()->id,
            'scenario_key' => $scenarioKey,
            'scenario_name' => $scenarioName,
            'version' => $version,
            'is_current' => true,
            'input_payload' => $input,
            'calculation_payload' => $calculation,
        ]);

        $investment->update([
            'input_payload' => $input,
            'latest_calculation_payload' => $calculation,
        ]);
        $this->syncInvestmentOverrides($investment, $input);

        return ApiResponse::success($record, 'Scenario version created successfully', 201);
    }

    public function scenarios(Request $request, Investment $investment): JsonResponse
    {
        $this->authorizeOwnership($request, $investment);

        $rows = InvestmentScenarioVersion::query()
            ->where('investment_id', $investment->id)
            ->orderBy('scenario_key')
            ->orderByDesc('version')
            ->get()
            ->groupBy('scenario_key');

        return ApiResponse::success($rows, 'Scenario versions loaded successfully');
    }

    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'investment_ids' => 'required|array|min:2',
            'investment_ids.*' => 'required|integer|exists:investments,id',
        ]);

        $ids = $request->input('investment_ids', []);
        $investments = Investment::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $ids)
            ->with(['currentScenarioVersions' => fn($q) => $q->orderByDesc('version')])
            ->get();

        return ApiResponse::success([
            'rows' => $investments->map(function (Investment $investment) {
                $calc = $investment->latest_calculation_payload ?? [];
                return [
                    'id' => $investment->id,
                    'name' => $investment->name,
                    'currency' => $investment->currency,
                    'derived' => $calc['derived'] ?? null,
                    'risk' => $calc['risk'] ?? null,
                    'bestScenarioName' => $calc['bestScenarioName'] ?? null,
                    'updated_at' => $investment->updated_at,
                ];
            })->values(),
        ], 'Investment comparison loaded successfully');
    }

    public function pdf(Request $request, Investment $investment)
    {
        $this->authorizeOwnership($request, $investment);

        $scenarioKey = $request->query('scenario_key');
        $scenario = null;
        if ($scenarioKey) {
            $scenario = InvestmentScenarioVersion::query()
                ->where('investment_id', $investment->id)
                ->where('scenario_key', $scenarioKey)
                ->where('is_current', true)
                ->latest('version')
                ->first();
        }

        $calculation = $scenario?->calculation_payload ?? $investment->latest_calculation_payload ?? $this->calculationService->calculate($investment->input_payload ?? [], $investment);

        $pdf = Pdf::loadView('pdf.investment-report', [
            'investment' => $investment,
            'scenario' => $scenario,
            'calculation' => $calculation,
            'generatedAt' => now(),
        ])->setPaper('a4');

        $filename = Str::slug($investment->name) . '-investment-report.pdf';
        return $pdf->download($filename);
    }

    private function createScenarioVersion(
        Investment $investment,
        string $scenarioKey,
        string $scenarioName,
        array $input,
        array $calculation,
        int $userId
    ): void {
        InvestmentScenarioVersion::query()->create([
            'investment_id' => $investment->id,
            'created_by' => $userId,
            'scenario_key' => $scenarioKey,
            'scenario_name' => $scenarioName,
            'version' => 1,
            'is_current' => true,
            'input_payload' => $input,
            'calculation_payload' => $calculation,
        ]);
    }

    private function authorizeOwnership(Request $request, Investment $investment): void
    {
        abort_if($investment->user_id !== $request->user()->id, 403, 'Unauthorized investment access');
    }

    private function syncInvestmentOverrides(Investment $investment, array $input): void
    {
        $overridableFields = ['purchasePrice', 'downPaymentPercent', 'loanInterestPercent', 'holdYears', 'vacancyPercent'];
        $rows = [];
        $useCityDefaults = filter_var($input['useCityDefaults'] ?? false, FILTER_VALIDATE_BOOL);
        $city = (string)($input['city'] ?? 'Dubai');
        $baseline = $this->citySettingsService->getCalculationConfig(['city' => $city, 'useCityDefaults' => true]);

        if (!$useCityDefaults) {
            foreach ($overridableFields as $field) {
                if (array_key_exists($field, $input) && array_key_exists($field, $baseline)) {
                    $inputValue = (float)$input[$field];
                    $baseValue = (float)$baseline[$field];
                    if (abs($inputValue - $baseValue) > 0.0001) {
                        $rows[$field] = ['overridden_value' => $inputValue];
                    }
                }
            }
        }

        $investment->overrides()->delete();
        foreach ($rows as $fieldName => $values) {
            $investment->overrides()->create([
                'field_name' => $fieldName,
                'overridden_value' => $values['overridden_value'],
            ]);
        }
    }
}
