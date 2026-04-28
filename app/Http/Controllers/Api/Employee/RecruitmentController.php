<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Job;  
use App\Models\Applicant;
use App\Models\Interview;
use App\Models\User;
use App\Helpers\ApiResponse;
use App\Mail\ApplicantStatusMail;
use App\Mail\InterviewScheduledMail;
use App\Mail\InterviewReminderMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Notifications\NewApplicationNotification;
use App\Mail\NewApplicationMail;
class RecruitmentController extends Controller
{
    public function __construct()
    {
        // Jobs Permissions
        $this->middleware('permission:jobs-list', ['only' => ['getJobs', 'getJob']]);
        $this->middleware('permission:jobs-create', ['only' => ['storeJob']]);
        $this->middleware('permission:jobs-edit', ['only' => ['updateJob']]);
        $this->middleware('permission:jobs-delete', ['only' => ['deleteJob']]);
        
        // Applicants Permissions
        $this->middleware('permission:applicants-list', ['only' => ['getApplicants', 'getApplicant']]);
        $this->middleware('permission:applicants-status-edit', ['only' => ['updateApplicantStatus']]);
        
        // Interviews Permissions
        $this->middleware('permission:interviews-list', ['only' => ['getInterviews']]);
        $this->middleware('permission:interviews-create', ['only' => ['scheduleInterview']]);
        $this->middleware('permission:interviews-edit', ['only' => ['updateInterview']]);
        
        // Statistics
        $this->middleware('permission:recruitment-list', ['only' => ['statistics']]);
    }
    
    // ==================== PUBLIC JOBS ) ====================
    
    public function getPublicJobs(Request $request)
    {
        try {
            $query = Job::with(['department', 'branch'])
                ->where('status', 'open')
                ->where(function($q) {
                    $q->whereNull('closing_date')
                      ->orWhere('closing_date', '>=', now());
                });
            
            if ($request->has('search')) {
                $query->where('title', 'like', '%' . $request->search . '%');
            }
            
            if ($request->has('department_id')) {
                $query->where('department_id', $request->department_id);
            }
            
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
            
            if ($request->has('job_type')) {
                $query->where('job_type', $request->job_type);
            }
            
            $jobs = $query->orderBy('posted_date', 'desc')
                ->paginate($request->per_page ?? 15);
            
            foreach ($jobs as $job) {
                $job->questions = $job->getFormattedQuestions();
            }
            
            return ApiResponse::success($jobs, 'Jobs retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve jobs: ' . $e->getMessage());
        }
    }
    
    public function getPublicJob($id)
    {
        try {
            $job = Job::with(['department', 'branch'])
                ->where('status', 'open')
                ->where(function($q) {
                    $q->whereNull('closing_date')
                      ->orWhere('closing_date', '>=', now());
                })
                ->findOrFail($id);
            
            // إضافة الأسئلة المنسقة
            $job->questions = $job->getFormattedQuestions();
            
            return ApiResponse::success($job, 'Job retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Job not found', 404);
        }
    }
    
    // ==================== APPLY FOR JOB (Public) ====================
    
    public function apply(Request $request, $jobId)
    {
        $job = Job::findOrFail($jobId);
        
        if (!$job->isOpen()) {
            return ApiResponse::error('This job is no longer accepting applications', 422);
        }
        
        $rules = [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string',
            'resume' => 'required|file|mimes:pdf,doc,docx|max:5120',
            'answers' => 'nullable|array',
        ];
        
        if ($job->custom_questions) {
            foreach ($job->custom_questions as $index => $q) {
                $questionKey = 'q' . ($index + 1);
                
                if (isset($q['required']) && $q['required'] === true) {
                    $rules["answers.{$questionKey}"] = 'required';
                    
                    $type = $q['type'] ?? 'text';
                    switch ($type) {
                        case 'number':
                            $rules["answers.{$questionKey}"] .= '|numeric';
                            break;
                        case 'radio':
                        case 'select':
                            if (isset($q['options'])) {
                                $rules["answers.{$questionKey}"] .= '|in:' . implode(',', $q['options']);
                            }
                            break;
                    }
                }
            }
        }
        
        $request->validate($rules);
        
        try {
            DB::beginTransaction();
            
            // Check if already applied
            $existing = Applicant::where('job_id', $jobId)
                ->where('email', $request->email)
                ->first();
                
            if ($existing) {
                return ApiResponse::error('You have already applied for this position', 422);
            }
            
            // Upload resume
            $resumePath = null;
            if ($request->hasFile('resume')) {
                $resumePath = $request->file('resume')->store("applicants/resumes/{$jobId}", 'public');
            }
            
            $answersWithQuestions = [];
            if ($request->has('answers')) {
                foreach ($job->custom_questions as $index => $q) {
                    $questionKey = 'q' . ($index + 1);
                    if (isset($request->answers[$questionKey])) {
                        $answersWithQuestions[$q['question']] = $request->answers[$questionKey];
                    }
                }
            }
            
            $applicant = Applicant::create([
                'job_id' => $jobId,
                'full_name' => $request->full_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'nationality' => $request->nationality,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'visa_status' => $request->visa_status,
                'notice_period_days' => $request->notice_period_days,
                'total_experience_years' => $request->total_experience_years,
                'experience_in_uae_years' => $request->experience_in_uae_years,
                'current_salary' => $request->current_salary,
                'expected_salary' => $request->expected_salary,
                'resume_path' => $resumePath,
                'additional_notes' => $request->additional_notes,
                'answers' => $answersWithQuestions,
                'status' => 'pending',
                'applied_at' => now(),
            ]);
            
            DB::commit();
            
            // 🔥 إرسال إشعار للمدير (Hiring Manager)
            $this->sendNotificationToHiringManager($job, $applicant);
            
            return ApiResponse::success($applicant, 'Application submitted successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to submit application: ' . $e->getMessage());
        }
    }
    
  
    private function sendNotificationToHiringManager($job, $applicant)
    {
        $hiringManager = null;
        
        if ($job->hiring_manager_id) {
            $hiringManager = User::find($job->hiring_manager_id);
        }
        
        if (!$hiringManager) {
            $hiringManager = User::role('hr')->first();
        }
        
        if ($hiringManager) {
            $hiringManager->notify(new NewApplicationNotification($job, $applicant));
            
            Mail::to($hiringManager->email)->send(new NewApplicationMail($job, $applicant));
        }
        
        $hrUsers = User::role('hr')->get();
        foreach ($hrUsers as $hr) {
            $hr->notify(new NewApplicationNotification($job, $applicant));
        }
    }
    
    // ==================== ADMIN JOBS ====================
    
    public function getJobs(Request $request)
    {
        try {
            $query = Job::with(['department', 'branch', 'hiringManager']);
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            if ($request->has('department_id')) {
                $query->where('department_id', $request->department_id);
            }
            
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
            
            if ($request->has('search')) {
                $query->where('title', 'like', '%' . $request->search . '%');
            }
            
            $jobs = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);
            
            return ApiResponse::success($jobs, 'Jobs retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve jobs: ' . $e->getMessage());
        }
    }
    
    public function getJob($id)
    {
        try {
            $job = Job::with(['department', 'branch', 'hiringManager'])->findOrFail($id);
            return ApiResponse::success($job, 'Job retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Job not found', 404);
        }
    }
    
    public function storeJob(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
            'branch_id' => 'nullable|exists:company_branches,id',
            'job_type' => 'required|in:full_time,part_time,contract,internship,remote',
            'openings' => 'required|integer|min:1',
            'closing_date' => 'nullable|date|after:today',
        ]);
        
        try {
            $job = Job::create([
                'title' => $request->title,
                'slug' => Str::slug($request->title) . '-' . uniqid(),
                'description' => $request->description,
                'requirements' => $request->requirements,
                'skills' => $request->skills,
                'department_id' => $request->department_id,
                'branch_id' => $request->branch_id,
                'hiring_manager_id' => $request->hiring_manager_id,
                'job_type' => $request->job_type,
                'status' => $request->status ?? 'draft',
                'openings' => $request->openings,
                'posted_date' => $request->posted_date ?? now(),
                'closing_date' => $request->closing_date,
                'custom_questions' => $request->custom_questions,
                'required_documents' => $request->required_documents,
            ]);
            
            return ApiResponse::success($job, 'Job created successfully', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create job: ' . $e->getMessage());
        }
    }
    
    public function updateJob(Request $request, $id)
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:draft,open,on_hold,closed,cancelled',
        ]);
        
        try {
            $job = Job::findOrFail($id);
            $job->update($request->all());
            
            return ApiResponse::success($job, 'Job updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update job: ' . $e->getMessage());
        }
    }
    
    public function deleteJob($id)
    {
        try {
            $job = Job::findOrFail($id);
            
            // Check if there are applicants
            if ($job->applicants()->count() > 0) {
                return ApiResponse::error('Cannot delete job with existing applicants', 422);
            }
            
            $job->delete();
            return ApiResponse::success(null, 'Job deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete job: ' . $e->getMessage());
        }
    }
    
    // ==================== APPLICANTS ====================
    
    public function getApplicants(Request $request, $jobId = null)
    {
        try {
            $query = Applicant::with(['job', 'interviews']);
            
            if ($jobId) {
                $query->where('job_id', $jobId);
            }
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            if ($request->has('search')) {
                $query->where(function($q) use ($request) {
                    $q->where('full_name', 'like', '%' . $request->search . '%')
                      ->orWhere('email', 'like', '%' . $request->search . '%');
                });
            }
            
            $applicants = $query->orderBy('applied_at', 'desc')
                ->paginate($request->per_page ?? 15);
            
            return ApiResponse::success($applicants, 'Applicants retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve applicants: ' . $e->getMessage());
        }
    }
    
    public function getApplicant($id)
    {
        try {
            $applicant = Applicant::with(['job', 'interviews.interviewer'])->findOrFail($id);
            
            if ($applicant->resume_path) {
                $applicant->resume_url = asset('storage/' . $applicant->resume_path);
            }
            
            return ApiResponse::success($applicant, 'Applicant details retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Applicant not found', 404);
        }
    }
    
    public function updateApplicantStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,shortlisted,interview,hired,rejected,withdrawn',
            'rejection_reason' => 'required_if:status,rejected|nullable|string',
        ]);
        
        try {
            DB::beginTransaction();
            
            $applicant = Applicant::with(['job'])->findOrFail($id);
            
            $applicant->update([
                'status' => $request->status,
                'rejection_reason' => $request->rejection_reason,
            ]);
            
            // Send email notification to applicant
            if (in_array($request->status, ['shortlisted', 'rejected', 'hired'])) {
                Mail::to($applicant->email)->send(new ApplicantStatusMail($applicant, $request->status));
            }
            
            DB::commit();
            
            return ApiResponse::success($applicant, 'Applicant status updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to update status: ' . $e->getMessage());
        }
    }
    
    // ==================== INTERVIEWS ====================
    
  public function scheduleInterview(Request $request)
{
    $request->validate([
        'applicant_id' => 'required|exists:applicants,id',
        'interviewer_id' => 'required|exists:users,id',
        'scheduled_at' => 'required|date|after:now',
        'type' => 'required|in:online,in_person,phone',
        'location' => 'nullable|string',
        'meeting_link' => 'nullable|url',
    ]);
    
    try {
        DB::beginTransaction();
        
        $applicant = Applicant::with(['job'])->find($request->applicant_id);
        
        $allowedStatuses = ['pending', 'shortlisted'];
        
        if ($applicant && !in_array($applicant->status, $allowedStatuses)) {
            $statusMessages = [
                'rejected' => 'This applicant has been rejected. Cannot schedule interview.',
                'hired' => 'This applicant has already been hired. Cannot schedule interview.',
                'withdrawn' => 'This applicant has withdrawn. Cannot schedule interview.',
                'interview' => 'This applicant already has an interview scheduled.',
            ];
            
            $message = $statusMessages[$applicant->status] ?? 'Cannot schedule interview for applicant with status: ' . $applicant->status;
            
            return ApiResponse::error($message, 422);
        }
        
        $interviewer = User::findOrFail($request->interviewer_id);
        
        $existingInterview = Interview::where('applicant_id', $applicant->id)
            ->where('status', 'scheduled')
            ->first();
            
        if ($existingInterview) {
            return ApiResponse::error('An interview is already scheduled for this applicant', 422);
        }
        
        $interview = Interview::create([
            'applicant_id' => $request->applicant_id,
            'job_id' => $applicant->job_id,
            'interviewer_id' => $request->interviewer_id,
            'scheduled_at' => $request->scheduled_at,
            'type' => $request->type,
            'location' => $request->location,
            'meeting_link' => $request->meeting_link,
            'status' => 'scheduled',
        ]);
        
        // Update applicant status
        $applicant->update(['status' => 'interview']);
        
        // Send email to applicant with interview details
        Mail::to($applicant->email)->send(new InterviewScheduledMail($interview, $applicant));
        
        // Send email to interviewer (manager)
        Mail::to($interviewer->email)->send(new InterviewReminderMail($interview, $applicant, 'scheduled'));
        
        DB::commit();
        
        return ApiResponse::success($interview->load(['applicant', 'interviewer']), 'Interview scheduled successfully', 201);
    } catch (\Exception $e) {
        DB::rollBack();
        return ApiResponse::error('Failed to schedule interview: ' . $e->getMessage());
    }
}
    
    public function updateInterview(Request $request, $id)
    {
        $request->validate([
            'feedback' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'status' => 'sometimes|in:scheduled,completed,cancelled,no_show',
        ]);
        
        try {
            $interview = Interview::with(['applicant', 'interviewer'])->findOrFail($id);
            $interview->update($request->all());
            
            return ApiResponse::success($interview, 'Interview updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update interview: ' . $e->getMessage());
        }
    }
    
    public function getInterviews(Request $request)
    {
        try {
            $query = Interview::with(['applicant', 'job', 'interviewer']);
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            if ($request->has('interviewer_id')) {
                $query->where('interviewer_id', $request->interviewer_id);
            }
            
            $interviews = $query->orderBy('scheduled_at', 'desc')
                ->paginate($request->per_page ?? 15);
            
            return ApiResponse::success($interviews, 'Interviews retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve interviews: ' . $e->getMessage());
        }
    }
    
    // ==================== STATISTICS ====================
    
    public function statistics()
    {
        try {
            $stats = [
                'total_jobs' => Job::count(),
                'open_jobs' => Job::where('status', 'open')->count(),
                'total_applicants' => Applicant::count(),
                'pending_applicants' => Applicant::where('status', 'pending')->count(),
                'shortlisted' => Applicant::where('status', 'shortlisted')->count(),
                'interviewing' => Applicant::where('status', 'interview')->count(),
                'hired' => Applicant::where('status', 'hired')->count(),
                'rejected' => Applicant::where('status', 'rejected')->count(),
                'upcoming_interviews' => Interview::where('status', 'scheduled')
                    ->where('scheduled_at', '>', now())
                    ->count(),
            ];
            
            return ApiResponse::success($stats, 'Statistics retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve statistics: ' . $e->getMessage());
        }
    }
}