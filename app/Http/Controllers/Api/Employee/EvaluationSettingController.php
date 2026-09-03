<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\EvaluationSetting;
use Illuminate\Http\Request;

class EvaluationSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('role_or_permission:super_admin', ['only' => ['update']]);
    }

    public function show()
    {
        return ApiResponse::success(EvaluationSetting::current(), 'Evaluation settings retrieved successfully');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'recurrence_mode' => 'required|in:single,recurring',
        ]);

        $setting = EvaluationSetting::current();
        $setting->update($validated);

        return ApiResponse::success($setting, 'Evaluation settings updated successfully');
    }
}
