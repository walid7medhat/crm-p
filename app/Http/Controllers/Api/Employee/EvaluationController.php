<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Mail\EvaluationCompletedHrMail;
use App\Models\Evaluation;
use App\Models\EvaluationAnswer;
use App\Models\EvaluationSection;
use App\Models\User;
use App\Notifications\EvaluationCompletedHrNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class EvaluationController extends Controller
{
    /** Rating scale for `rating_1_5` sections, shown on the PDF instead of the raw stored value. */
    private const RATING_LABELS = [
        '1' => 'Unsatisfactory (1)',
        '2' => 'Marginal (2)',
        '3' => 'Satisfactory (3)',
        '4' => 'Highly Satisfactory (4)',
        '5' => 'Exceptional (5)',
        'N/A' => 'N/A',
    ];

    /**
     * Pending evaluations the current user (as manager/evaluator) needs to fill in.
     * GET /evaluations/pending-for-me
     */
    public function pendingForMe()
    {
        $evaluations = Evaluation::where('evaluator_id', Auth::id())
            ->where('status', 'pending')
            ->with('user:id,name,display_name,avatar')
            ->orderBy('created_at', 'desc')
            ->get();

        return ApiResponse::success($evaluations, 'Pending evaluations retrieved successfully');
    }

    /**
     * A specific user's submitted evaluations (admin/super_admin/hr, e.g.
     * UserDetail.vue and the HR employee profile page).
     * GET /evaluations/for-user/{userId}
     */
    public function forUser($userId)
    {
        $me = Auth::user();
        $targetUser = User::find($userId);
        if (! $targetUser) {
            return ApiResponse::error('User not found', 404);
        }

        $isAdmin = $me->hasAnyRole(['super_admin', 'admin', 'hr']);
        $hasPermission = $me->getAllPermissions()->pluck('name')->contains('view-evaluations');
        $isDirectManager = (int) $targetUser->parent_id === (int) $me->id;

        if (! $isAdmin && ! $hasPermission && ! $isDirectManager) {
            return ApiResponse::error('Access denied', 403);
        }

        $evaluations = Evaluation::where('user_id', $userId)
            ->where('status', 'submitted')
            ->orderBy('submitted_at', 'desc')
            ->get();

        return ApiResponse::success($evaluations, 'User evaluations retrieved successfully');
    }

    /**
     * Form structure + saved answers (if any) for a pending evaluation.
     * GET /evaluations/{id}
     */
    public function show($id)
    {
        $evaluation = Evaluation::with(['user:id,name,display_name', 'evaluator:id,name,display_name', 'answers'])
            ->findOrFail($id);

        $me = Auth::user();
        if ($evaluation->evaluator_id !== $me->id && ! $me->hasAnyRole(['super_admin', 'admin'])) {
            return ApiResponse::error('Access denied', 403);
        }

        $answersByQuestion = $evaluation->answers->keyBy('evaluation_question_id');

        $sections = EvaluationSection::active()->ordered()->with(['questions' => function ($q) {
            $q->active()->ordered();
        }])->get()->map(function ($section) use ($answersByQuestion) {
            return [
                'id' => $section->id,
                'title' => $section->title,
                'question_type' => $section->question_type,
                'questions' => $section->questions->map(function ($question) use ($answersByQuestion) {
                    return [
                        'id' => $question->id,
                        'question_text' => $question->question_text,
                        'answer_value' => $answersByQuestion->get($question->id)?->answer_value,
                    ];
                }),
            ];
        });

        return ApiResponse::success([
            'evaluation' => $evaluation,
            'sections' => $sections,
        ], 'Evaluation retrieved successfully');
    }

    /**
     * Submit answers, generate the PDF, email the employee.
     * POST /evaluations/{id}/submit
     */
    public function submit(Request $request, $id)
    {
        $evaluation = Evaluation::with('user', 'evaluator')->findOrFail($id);

        $me = Auth::user();
        if ($evaluation->evaluator_id !== $me->id && ! $me->hasAnyRole(['super_admin', 'admin'])) {
            return ApiResponse::error('Access denied', 403);
        }

        if ($evaluation->status === 'submitted') {
            return ApiResponse::error('This evaluation has already been submitted', 422);
        }

        $validated = $request->validate([
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => 'required|exists:evaluation_questions,id',
            'answers.*.answer_value' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            foreach ($validated['answers'] as $answer) {
                EvaluationAnswer::updateOrCreate(
                    ['evaluation_id' => $evaluation->id, 'evaluation_question_id' => $answer['question_id']],
                    ['answer_value' => $answer['answer_value'] ?? null]
                );
            }

            $evaluation->update([
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            $pdfBytes = $this->generatePdf($evaluation);
            $path = "employees/{$evaluation->user_id}/evaluations/evaluation-{$evaluation->id}.pdf";
            Storage::disk('public')->put($path, $pdfBytes);
            $evaluation->update(['pdf_path' => $path]);

            $hrUsers = User::whereHas('roles', function ($q) {
                $q->where('name', 'hr');
            })->where('status', 'active')->get();

            foreach ($hrUsers as $hrUser) {
                if ($hrUser->email) {
                    Mail::to($hrUser->email)->send(new EvaluationCompletedHrMail($evaluation, $evaluation->user, $pdfBytes));
                }
                $hrUser->notify(new EvaluationCompletedHrNotification($evaluation));
            }

            DB::commit();

            return ApiResponse::success($evaluation->fresh(), 'Evaluation submitted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to submit evaluation: ' . $e->getMessage());
        }
    }

    private function generatePdf(Evaluation $evaluation): string
    {
        $evaluation->load('answers.question.section');

        $sections = EvaluationSection::active()->ordered()->with(['questions' => function ($q) {
            $q->active()->ordered();
        }])->get()->map(function ($section) use ($evaluation) {
            return [
                'title' => $section->title,
                'questions' => $section->questions->map(function ($question) use ($evaluation, $section) {
                    $answer = $evaluation->answers->firstWhere('evaluation_question_id', $question->id);
                    $value = $answer?->answer_value;
                    return [
                        'question_text' => $question->question_text,
                        'answer_value' => $section->question_type === 'rating_1_5'
                            ? (self::RATING_LABELS[$value] ?? $value)
                            : $value,
                    ];
                })->toArray(),
            ];
        })->toArray();

        $employeeName = $evaluation->user->displayName() ?? $evaluation->user->name;
        $evaluatorName = $evaluation->evaluator?->displayName() ?? $evaluation->evaluator?->name ?? 'N/A';

        return Pdf::loadView('pdf.employee-evaluation', [
            'employeeName' => $employeeName,
            'evaluatorName' => $evaluatorName,
            'designationName' => $evaluation->designation_name_snapshot,
            'milestoneMonths' => $evaluation->milestone_months,
            'submittedAt' => $evaluation->submitted_at,
            'generatedAt' => now(),
            'sections' => $sections,
        ])->setPaper('a4')->output();
    }
}
