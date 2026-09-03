<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\EvaluationQuestion;
use App\Models\EvaluationSection;
use Illuminate\Http\Request;

class EvaluationQuestionController extends Controller
{
    public function __construct()
    {
        $this->middleware('role_or_permission:super_admin', ['except' => ['index']]);
    }

    public function index($sectionId)
    {
        $section = EvaluationSection::findOrFail($sectionId);
        $questions = $section->questions()->get();

        return ApiResponse::success($questions, 'Evaluation questions retrieved successfully');
    }

    public function store(Request $request, $sectionId)
    {
        EvaluationSection::findOrFail($sectionId);

        $validated = $request->validate([
            'question_text' => 'required|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['evaluation_section_id'] = $sectionId;
        $question = EvaluationQuestion::create($validated);

        return ApiResponse::success($question, 'Evaluation question created successfully', 201);
    }

    public function update(Request $request, $id)
    {
        $question = EvaluationQuestion::findOrFail($id);

        $validated = $request->validate([
            'question_text' => 'sometimes|required|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $question->update($validated);

        return ApiResponse::success($question, 'Evaluation question updated successfully');
    }

    public function destroy($id)
    {
        $question = EvaluationQuestion::findOrFail($id);
        $question->delete();

        return ApiResponse::success(null, 'Evaluation question deleted successfully');
    }
}
