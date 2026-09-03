<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\EvaluationSection;
use Illuminate\Http\Request;

class EvaluationSectionController extends Controller
{
    public function __construct()
    {
        $this->middleware('role_or_permission:super_admin', ['except' => ['index', 'show']]);
    }

    public function index(Request $request)
    {
        $query = EvaluationSection::with('questions')->ordered();

        if (! $request->boolean('all')) {
            $query->active();
        }

        return ApiResponse::success($query->get(), 'Evaluation sections retrieved successfully');
    }

    public function show($id)
    {
        $section = EvaluationSection::with('questions')->findOrFail($id);

        return ApiResponse::success($section, 'Evaluation section retrieved successfully');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'question_type' => 'required|in:rating_1_5,yes_no,text',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $section = EvaluationSection::create($validated);

        return ApiResponse::success($section, 'Evaluation section created successfully', 201);
    }

    public function update(Request $request, $id)
    {
        $section = EvaluationSection::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'question_type' => 'sometimes|required|in:rating_1_5,yes_no,text',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $section->update($validated);

        return ApiResponse::success($section, 'Evaluation section updated successfully');
    }

    public function destroy($id)
    {
        $section = EvaluationSection::findOrFail($id);
        $section->delete();

        return ApiResponse::success(null, 'Evaluation section deleted successfully');
    }
}
