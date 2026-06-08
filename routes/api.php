<?php
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\StageController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserRoleController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\Listing\AreaController;
use App\Http\Controllers\Api\Listing\PropertyTypeController;
use App\Http\Controllers\Api\Listing\OwnerController;
use App\Http\Controllers\Api\Listing\DeveloperController;
use App\Http\Controllers\Api\Listing\ListingController;
use App\Http\Controllers\Api\Listing\ListingAccessRequestController;
use App\Http\Controllers\Api\Listing\UnitViewController;
use App\Http\Controllers\Api\Listing\LayoutTypeController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\Listing\FeatureController;
use App\Http\Controllers\Api\UserInvitationController;
use App\Http\Controllers\Api\Listing\ListingCommentController;
use App\Http\Controllers\Api\Listing\ProjectController;
use App\Http\Controllers\Api\Listing\HotDealApprovalController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Notifications\ListingAccessRequestNotification;
use App\Http\Controllers\Api\SourceController;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Http\Controllers\Api\LeadActivityController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\Deal\DealController;
use App\Http\Controllers\Api\Deal\LeadConversionController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceCheckinController;
use App\Http\Controllers\Api\SuggestionController;
use App\Http\Controllers\Api\Deal\DealActivityController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\KanbanSettingsController;
use App\Http\Controllers\Api\LeadScoringSettingController;
use App\Http\Controllers\Api\LeadAssignmentController;
use App\Http\Controllers\Api\InvestmentController;
use App\Http\Controllers\Api\CityInvestmentSettingsController;
use App\Http\Controllers\Api\AbuDhabiBenchmarkController;
use App\Http\Controllers\Api\AdminEmailController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\LeadImportController;
use App\Http\Controllers\Api\Bitrix24SyncController;
use App\Http\Controllers\Api\SalesIntelligence\SalesIntelligenceController;
use App\Http\Controllers\Api\Mobile\MobileKanbanController;
use App\Http\Controllers\Api\Mobile\MobileLeadMoveController;
use App\Http\Controllers\Api\Employee\EmployeeController;
use App\Http\Controllers\Api\Employee\DesignationController;
use App\Http\Controllers\Api\Employee\DepartmentController;
use App\Http\Controllers\Api\Employee\CompanyBranchController;
use App\Http\Controllers\Api\Employee\EmployeeExcelImportController;
use App\Http\Controllers\Api\Employee\DocumentRequestController;
use App\Http\Controllers\Api\Employee\AssetController;
use App\Http\Controllers\Api\Employee\LeaveController;
use App\Http\Controllers\Api\Employee\AnnouncementController;
use App\Http\Controllers\Api\Employee\RecruitmentController;


Route::get('/test-email', function () {
    try {
        // Test basic email
        Mail::raw('This is a test email', function ($message) {
            $message->to('')
                    ->subject('Test Email');
        });
        
        return 'Email sent successfully! Check your email inbox.';
    } catch (\Exception $e) {
        return 'Email error: ' . $e->getMessage();
    }
});

Route::get('/test-invitation-email', function () {
    try {
        // Create test invitation
        $invitation = new UserInvitation([
            'email' => 'test@example.com',
            'token' => Str::random(60),
            'expires_at' => now()->addDays(7),
            'invited_by' => 1,
        ]);

        // Test invitation email
        Mail::send(new App\Mail\UserInvitationMail($invitation));
        
        return 'Invitation email sent successfully!';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});
/* Preview account-activated email design in browser (no mail sent) */
Route::get('/preview-account-activated-email', function () {
    $userName = 'Walid';
    $userEmail = 'walidmedhat.uae@gmail.com';
    return response()->view('emails.account-activated', [
        'userName' => $userName,
    ])->header('Content-Type', 'text/html');
});

/* Test account-activated email – actually sends to walidmedhat.uae@gmail.com (requires SMTP in .env) */
Route::get('/test-account-activated-email', function () {
    $to = 'walidmedhat.uae@gmail.com';
    try {
        $mailable = new App\Mail\AccountActivatedMail($to, 'Walid');
        Mail::to($to)->send($mailable);
        return response()->json([
            'success' => true,
            'message' => 'Email sent to ' . $to . '. Check inbox and spam.',
            'mail_driver' => config('mail.default'),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'mail_driver' => config('mail.default'),
            'hint' => config('mail.default') === 'log' ? 'Emails are only written to storage/logs. Set MAIL_MAILER=smtp in .env and configure SMTP to send to real inbox.' : 'Check .env MAIL_* settings and credentials.',
        ], 500);
    }
});

/* Save account-activated email HTML to file so you can open it in browser */
Route::get('/save-account-activated-email-html', function () {
    $html = view('emails.account-activated', ['userName' => 'Walid'])->render();
    $path = storage_path('app/account-activated-email-preview.html');
    file_put_contents($path, $html);
    return response()->json([
        'message' => 'Email HTML saved. Open this file in your browser:',
        'file_path' => $path,
        'open_url' => 'file://' . $path,
    ]);
});
Route::get('/test-server', function() {
    return response()->json([
        'gd_installed' => extension_loaded('gd'),
        'gd_info' => function_exists('gd_info') ? gd_info() : 'GD not available',
        'intervention_loaded' => class_exists('Intervention\Image\ImageManagerStatic'),
        'storage_working' => class_exists('Illuminate\Support\Facades\Storage'),
    ]);
});
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
Route::middleware(['throttle:60,1','block.bots'])->group(function () {

Route::get('/webhook/facebook', [IntegrationController::class, 'verifyWebhook']);
Route::post('/webhook/facebook', [IntegrationController::class, 'handleWebhook']);
Route::post('/website-lead', [IntegrationController::class, 'store_website']);
Route::post('/website-lead/wordpress', [IntegrationController::class, 'store_wordpress']);
Route::prefix('auth')->group(function () {

    Route::post('forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('reset-password', [PasswordResetController::class, 'resetPassword']);
    Route::get('users/role/{role}', [UserController::class, 'getByRole']);
 ;
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::middleware(['jwt.auth'])->group(function () {
  
        Route::post('logout', [AuthController::class,'logout']);
        Route::get('notifications',[AuthController::class,'notifications']);
        Route::post('notifications/read-all', [AuthController::class, 'markAllNotificationsAsRead']);
        Route::any('notifications/{id}/read', [AuthController::class, 'markNotificationAsRead']);
        Route::delete('notifications/{id}', [AuthController::class, 'deleteNotification']);
     });
});
Route::prefix('recruitment')->group(function () {
    Route::get('/jobs', [RecruitmentController::class, 'getPublicJobs']);
    Route::get('/jobs/{id}', [RecruitmentController::class, 'getPublicJob']);
    Route::post('/jobs/{jobId}/apply', [RecruitmentController::class, 'apply']);
});

Route::prefix('settings')->middleware(['jwt.auth'])->group(function () {
  Route::get('/kanban', [KanbanSettingsController::class, 'getSettings']);
    Route::post('/kanban/card-fields', [KanbanSettingsController::class, 'updateCardFields']);
    Route::post('/kanban/revert-hours', [KanbanSettingsController::class, 'updateRevertHours']);
});

Route::middleware(['jwt.auth'])->group(function () {
    Route::get('/scoring-settings', [LeadScoringSettingController::class, 'show']);
    Route::post('/scoring-settings', [LeadScoringSettingController::class, 'store']);
    Route::post('/scoring/test', [LeadScoringSettingController::class, 'test']);

    Route::get('/lead-assignment/settings', [LeadAssignmentController::class, 'show']);
    Route::get('/lead-assignment/stats', [LeadAssignmentController::class, 'stats']);
    Route::get('/lead-assignment/insights', [LeadAssignmentController::class, 'insights']);
    Route::put('/lead-assignment/settings', [LeadAssignmentController::class, 'update']);
    Route::get('/lead-assignment/queue', [LeadAssignmentController::class, 'queue']);
    Route::get('/lead-assignment/logs', [LeadAssignmentController::class, 'logs']);
    Route::post('/lead-assignment/run', [LeadAssignmentController::class, 'runNow']);
    Route::post('/lead-assignment/reassign', [LeadAssignmentController::class, 'reassign']);
    Route::post('/lead-assignment/simulate', [LeadAssignmentController::class, 'simulate']);
    Route::post('/lead-assignment/override', [LeadAssignmentController::class, 'override']);
    Route::post('/lead-assignment/revert-stage', [LeadAssignmentController::class, 'revertStageAssignments']);
    Route::get('/city-settings', [CityInvestmentSettingsController::class, 'index']);
    Route::get('/city-settings/{city}', [CityInvestmentSettingsController::class, 'show']);
    Route::post('/city-settings/update', [CityInvestmentSettingsController::class, 'update']);
    Route::get('/abu-dhabi/benchmarks', [AbuDhabiBenchmarkController::class, 'index']);

    Route::prefix('investments')->group(function () {
        Route::post('/calculate', [InvestmentController::class, 'calculate']);
        Route::get('/', [InvestmentController::class, 'index']);
        Route::post('/', [InvestmentController::class, 'store']);
        Route::post('/compare', [InvestmentController::class, 'compare']);
        Route::get('/{investment}', [InvestmentController::class, 'show']);
        Route::get('/{investment}/scenarios', [InvestmentController::class, 'scenarios']);
        Route::post('/{investment}/scenarios', [InvestmentController::class, 'storeScenarioVersion']);
        Route::get('/{investment}/pdf', [InvestmentController::class, 'pdf']);
    });
});
Route::prefix('stages')->middleware(['jwt.auth'])->group(function () {
    Route::get('/', [StageController::class, 'index']);
    Route::post('/', [StageController::class, 'store']);
    Route::get('/{stage}', [StageController::class, 'show']);
    Route::put('/{stage}', [StageController::class, 'update']);
    Route::delete('/{stage}', [StageController::class, 'destroy']);
    Route::post('/reorder', [StageController::class, 'reorder']);
    Route::get('/kanban/stages-with-leads', [StageController::class, 'getStagesWithLeads']);
    Route::get('/kanban/leads-by-stage/{stage}', [StageController::class, 'getLeadsByStage']);
    Route::get('/kanban/stage/{stage}/more-leads', [StageController::class, 'getMoreStageLeads']);
    Route::get('/visibility/settings', [StageController::class, 'getStageVisibilitySettings']);
    Route::post('/visibility/settings', [StageController::class, 'updateStageVisibility']);
});
Route::prefix('company-branches')->group(function () {
    Route::get('/', [CompanyBranchController::class, 'index']);
    Route::post('/', [CompanyBranchController::class, 'store']);
    Route::get('/{id}', [CompanyBranchController::class, 'show']);
    Route::put('/{id}', [CompanyBranchController::class, 'update']);
    Route::delete('/{id}', [CompanyBranchController::class, 'destroy']);
    
    // Extra routes
    Route::get('/{id}/employees', [CompanyBranchController::class, 'getEmployees']);
    Route::patch('/{id}/toggle-status', [CompanyBranchController::class, 'toggleStatus']);
    Route::post('/bulk-delete', [CompanyBranchController::class, 'bulkDelete']);
    Route::get('/cities/list', [CompanyBranchController::class, 'getCities']);
    Route::get('/statistics/summary', [CompanyBranchController::class, 'getStatistics']);
});
// Document Types CRUD
Route::middleware(['role:super_admin|hr'])->prefix('document-types')->group(function () {
    Route::get('/', [DocumentRequestController::class, 'getDocumentTypes']);
    Route::post('/', [DocumentRequestController::class, 'storeDocumentType']);
    Route::put('/{id}', [DocumentRequestController::class, 'updateDocumentType']);
    Route::delete('/{id}', [DocumentRequestController::class, 'destroyDocumentType']);
});
// Asset Types CRUD
Route::prefix('asset-types')->middleware(['auth:api'])->group(function () {
    Route::get('/', [AssetController::class, 'getAssetTypes']);
    Route::post('/', [AssetController::class, 'storeAssetType']);
    Route::put('/{id}', [AssetController::class, 'updateAssetType']);
    Route::delete('/{id}', [AssetController::class, 'destroyAssetType']);
});

// Assets Management
Route::prefix('assets')->middleware(['auth:api'])->group(function () {
    Route::get('/get/statistics', [AssetController::class, 'statistics']);
    Route::get('/', [AssetController::class, 'index']);
    Route::post('/store/new', [AssetController::class, 'store']);
    Route::get('/{id}', [AssetController::class, 'show']);
    Route::put('/{id}', [AssetController::class, 'update']);
    Route::delete('/{id}', [AssetController::class, 'destroy']);
    
    // Assignment
    Route::post('/{id}/assign', [AssetController::class, 'assignAsset']);
    Route::post('/{id}/return', [AssetController::class, 'returnAsset']);
    Route::get('/{id}/history', [AssetController::class, 'getAssetHistory']);
    
    // Employee Assets
    Route::get('/employee/{userId}/assets', [AssetController::class, 'getEmployeeAssets']);
});

// ==================== Leave Management Routes ====================
Route::middleware(['auth:api'])->prefix('leaves')->group(function () {

    // Leave Types CRUD (Admin/HR only)
    Route::prefix('types')->group(function () {
        Route::get('/', [LeaveController::class, 'getLeaveTypes']);
        Route::post('/', [LeaveController::class, 'storeLeaveType']);
        Route::put('/{id}', [LeaveController::class, 'updateLeaveType']);
        Route::delete('/{id}', [LeaveController::class, 'destroyLeaveType']);
    });

    // Leave Balance
    Route::get('/my-balance', [LeaveController::class, 'getMyBalance']);
    Route::get('/balance/{userId}', [LeaveController::class, 'getEmployeeBalance'])
        ->middleware('permission:view-employee-balance');

    // Leave Requests
    Route::get('/statistics', [LeaveController::class, 'statistics']);
    Route::get('/', [LeaveController::class, 'index']);
    Route::post('/', [LeaveController::class, 'store']);
    Route::get('/{id}', [LeaveController::class, 'show']);
    Route::put('/{id}', [LeaveController::class, 'update']);
    Route::delete('/{id}', [LeaveController::class, 'cancel']);

    // Approve/Reject by Parent (Team Lead)
    Route::post('/{id}/approve-parent', [LeaveController::class, 'approveByParent']);
    Route::post('/{id}/reject-parent', [LeaveController::class, 'rejectByParent']);

    // Approve/Reject by HR
    Route::post('/{id}/approve-hr', [LeaveController::class, 'approveByHr']);
    Route::post('/{id}/reject-hr', [LeaveController::class, 'rejectByHr']);
});
Route::middleware(['auth:api'])->prefix('announcements')->group(function () {
    
    // Statistics & Reports (HR/Admin only)
    Route::get('/statistics', [AnnouncementController::class, 'statistics'])
        ->middleware('permission:announcements-list');
    
    // User-specific routes (employees)
    Route::get('/active', [AnnouncementController::class, 'getActiveAnnouncements']);
    Route::get('/unread', [AnnouncementController::class, 'getUnreadAnnouncements']);
    Route::post('/mark-all-read', [AnnouncementController::class, 'markAllAsRead']);
    
    // Standard CRUD routes
    Route::get('/', [AnnouncementController::class, 'index']);
    Route::post('/', [AnnouncementController::class, 'store'])
        ->middleware('permission:announcements-create');
    Route::get('/{id}', [AnnouncementController::class, 'show']);
    Route::put('/{id}', [AnnouncementController::class, 'update'])
        ->middleware('permission:announcements-edit');
    Route::delete('/{id}', [AnnouncementController::class, 'destroy'])
        ->middleware('permission:announcements-delete');
});
// Document Requests
Route::middleware(['auth:api'])->prefix('document-requests')->group(function () {
    Route::get('/statistics', [DocumentRequestController::class, 'statistics']);
    Route::get('/', [DocumentRequestController::class, 'index']);
    Route::get('/{id}', [DocumentRequestController::class, 'show']);
    Route::post('/store/new', [DocumentRequestController::class, 'store']);
    Route::put('/{id}', [DocumentRequestController::class, 'update']);  
    Route::delete('/{id}', [DocumentRequestController::class, 'destroy']);
    Route::post('/{id}/approve', [DocumentRequestController::class, 'approve']);
    Route::post('/{id}/reject', [DocumentRequestController::class, 'reject']);
});
  Route::prefix('designations')->group(function () {
        Route::get('/', [DesignationController::class, 'index']);           
        Route::post('/', [DesignationController::class, 'store']);          
        Route::get('/{id}', [DesignationController::class, 'show']);        
        Route::put('/{id}', [DesignationController::class, 'update']);      
        Route::delete('/{id}', [DesignationController::class, 'destroy']);  
        
        // Extra routes
        Route::get('/{id}/employees', [DesignationController::class, 'getEmployees']);  
        Route::patch('/{id}/toggle-status', [DesignationController::class, 'toggleStatus']); 
        Route::post('/bulk-delete', [DesignationController::class, 'bulkDelete']);  
    });

      Route::prefix('departments')->group(function () {
        Route::get('/', [DepartmentController::class, 'index']);           
        Route::post('/', [DepartmentController::class, 'store']);          
        Route::get('/{id}', [DepartmentController::class, 'show']);        
        Route::put('/{id}', [DepartmentController::class, 'update']);      
        Route::delete('/{id}', [DepartmentController::class, 'destroy']);  
        
        // Extra routes
        Route::get('/{id}/employees', [DepartmentController::class, 'getEmployees']);  
        Route::patch('/{id}/toggle-status', [DepartmentController::class, 'toggleStatus']); 
        Route::post('/bulk-delete', [DepartmentController::class, 'bulkDelete']);  
    });
    // ==================== Recruitment Routes ====================


Route::middleware(['auth:api'])->prefix('recruitment/admin')->group(function () {
    // Jobs
    Route::get('/jobs', [RecruitmentController::class, 'getJobs']);
    Route::get('/jobs/{id}', [RecruitmentController::class, 'getJob']);
    Route::post('/jobs', [RecruitmentController::class, 'storeJob']);
    Route::put('/jobs/{id}', [RecruitmentController::class, 'updateJob']);
    Route::delete('/jobs/{id}', [RecruitmentController::class, 'deleteJob']);
    
    // Applicants
    Route::get('/applicants', [RecruitmentController::class, 'getApplicants']);
    Route::get('/applicants/{id}', [RecruitmentController::class, 'getApplicant']);
    Route::put('/applicants/{id}/status', [RecruitmentController::class, 'updateApplicantStatus']);
    
    // Interviews
    Route::get('/interviews', [RecruitmentController::class, 'getInterviews']);
    Route::post('/interviews', [RecruitmentController::class, 'scheduleInterview']);
    Route::put('/interviews/{id}', [RecruitmentController::class, 'updateInterview']);
    
    // Statistics
    Route::get('/statistics', [RecruitmentController::class, 'statistics']);
});
    // ========== EMPLOYEE ROUTES ==========
    Route::prefix('employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'index']);                    
        Route::post('/', [EmployeeController::class, 'store']);                   
        Route::get('/statistics', [EmployeeController::class, 'getStatistics']);  
        Route::get('/{id}', [EmployeeController::class, 'show']);                 
        Route::put('/{id}', [EmployeeController::class, 'update']);               
        Route::delete('/{id}', [EmployeeController::class, 'destroy']);           
        
        // Document routes
        Route::get('/{id}/documents', [EmployeeController::class, 'getDocuments']);           
        Route::delete('/documents/{documentId}', [EmployeeController::class, 'deleteDocument']); 
    });
    Route::post('/admin/employees/import-excel', [EmployeeExcelImportController::class, 'import']);

Route::get('/teams-with-leads', [StageController::class, 'getTeamsWithLeads'])->middleware('jwt.auth');

Route::middleware(['jwt.auth', 'role:super_admin|admin'])->group(function () {
    Route::get('/system-overview/access', function () {
        return response()->json([
            'success' => true,
            'message' => 'System overview access granted',
            'data' => [
                'access' => true,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    });
});

Route::middleware('jwt.auth')->group(function () {
    Route::prefix('sales-intelligence')->group(function () {
        Route::get('/overview', [SalesIntelligenceController::class, 'overview']);
        Route::get('/settings', [SalesIntelligenceController::class, 'settings']);
        Route::put('/settings', [SalesIntelligenceController::class, 'updateSettings']);
        Route::get('/scoring-rules', [SalesIntelligenceController::class, 'scoringRules']);
        Route::put('/scoring-rules', [SalesIntelligenceController::class, 'updateScoringRules']);
        Route::get('/agents', [SalesIntelligenceController::class, 'agents']);
        Route::post('/recalculate', [SalesIntelligenceController::class, 'recalculate']);
        Route::post('/preview-score', [SalesIntelligenceController::class, 'previewScore']);
        Route::post('/distribute', [SalesIntelligenceController::class, 'distribute']);
        Route::get('/distribution-logs', [SalesIntelligenceController::class, 'distributionLogs']);
        Route::post('/ai/suggest', [SalesIntelligenceController::class, 'aiSuggest']);
    });

    Route::get('/attendance/today', [AttendanceController::class, 'today']);
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::prefix('attendance')->group(function () {
            Route::get('/status', [AttendanceCheckinController::class, 'status']);
            Route::get('/departments', [AttendanceCheckinController::class, 'departments']);
            Route::get('/settings', [AttendanceCheckinController::class, 'settings']);
            Route::put('/settings', [AttendanceCheckinController::class, 'updateSettings']);
            Route::post('/check-in', [AttendanceCheckinController::class, 'checkIn']);

            // 🔹 sync الشهر اللي فات
            Route::get('/sync-last-month', [AttendanceController::class, 'syncLastMonth']);

            // 🔹 monthly report
            Route::get('/monthly-report', [AttendanceController::class, 'generateMonthlyReport']);
            Route::get('/period-report', [AttendanceController::class, 'generatePeriodReport']);


        });
    // === Lead Conversion API ===
    Route::post('/leads/convert/to-deal', [LeadConversionController::class, 'convert']);
    Route::get('/leads/{lead}/can-convert', [LeadConversionController::class, 'canConvert']);
    Route::post('/leads/import', [LeadImportController::class, 'import']);

    // === Bitrix24 sync (admin-only, batched) ===
    Route::post('/leads/bitrix24/sync', [Bitrix24SyncController::class, 'syncBatch']);
    Route::post('/leads/bitrix24/fetch/{bitrixId}', [Bitrix24SyncController::class, 'fetchOne']);
    Route::post('/leads/bitrix24/start-queue', [Bitrix24SyncController::class, 'start']);
    Route::post('/leads/bitrix24/cancel-queue', [Bitrix24SyncController::class, 'cancel']);
    Route::post('/leads/bitrix24/reset-queue', [Bitrix24SyncController::class, 'reset']);
    Route::get('/bitrix24/queue-status', [Bitrix24SyncController::class, 'status']);
    // === Bitrix24 lead sync (bitrix24:sync-leads command) — live progress ===
    Route::post('/leads/bitrix24/sync-leads/start', [Bitrix24SyncController::class, 'startLeadsSync']);
    Route::post('/leads/bitrix24/sync-leads/cancel', [Bitrix24SyncController::class, 'cancelLeadsSync']);
    Route::get('/bitrix24/sync-leads/progress', [Bitrix24SyncController::class, 'leadsSyncProgress']);
    // === Deals API ===
    Route::prefix('deals')->group(function () {
        Route::get('/', [DealController::class, 'index']);
        Route::get('/grouped-by-stage', [DealController::class, 'getDealsGroupedByStage']);
        Route::get('/get-more/by-stage', [DealController::class, 'getDealsByStage']);
        Route::post('/{deal}/assign-responsible-person', [DealController::class, 'assignResponsiblePerson']);
        Route::delete('/documents/{id}', [DealController::class, 'deleteDocument']);
        Route::get('/{id}/history', [DealController::class, 'history']);
        Route::get('/{id}/history/view', [DealController::class, 'view_history']);
        Route::get('/{dealId}/activities', [DealActivityController::class, 'getDealActivities']);
        Route::post('/activities', [DealActivityController::class, 'storeActivity']);
        Route::put('/activities/{id}', [DealActivityController::class, 'updateActivity']);
        Route::patch('/activities/{id}/toggle-completion', [DealActivityController::class, 'toggleActivityCompletion']);
        Route::delete('/activities/{id}', [DealActivityController::class, 'destroyActivity']);
        Route::get('/{dealId}/comments', [DealActivityController::class, 'getDealComments']);
        Route::post('/comments', [DealActivityController::class, 'storeComment']);
        Route::put('/comments/{id}', [DealActivityController::class, 'updateComment']);
        Route::delete('/comments/{id}', [DealActivityController::class, 'destroyComment']);
        Route::post('/comments/{commentId}/attachments', [DealActivityController::class, 'addCommentAttachments']);
        Route::delete('/comments/{commentId}/attachments/{attachmentId}', [DealActivityController::class, 'destroyCommentAttachment']);
        Route::get('/{deal}', [DealController::class, 'show']);
        Route::put('/{deal}', [DealController::class, 'update']);
        Route::post('/store/new', [LeadConversionController::class, 'store']);
        Route::post('/{id}/update-stage', [DealController::class, 'updateStage']);
        Route::post('/check-stage-requirements', [DealController::class, 'checkStageRequirements']);
        Route::post('/{id}/update-partial', [DealController::class, 'updatePartial']);
         Route::post('/{id}/change-stage', [DealController::class, 'changeStage']);
        Route::post('/get-stage-required-fields', [DealController::class, 'getStageRequiredFields']);
        Route::post('/{id}/update-and-change-stage', [DealController::class, 'updateAndChangeStage']);
        Route::put('/{deal}/properties/{property}', [DealController::class, 'updateProperty']);
        Route::delete('/property-document', [DealController::class, 'deletePropertyDocument']);
        Route::post('/{deal}/properties', [DealController::class, 'addProperty']);
    });
    
});
Route::get('leads/integration', [LeadController::class,'storeIntegration']);

Route::middleware(['jwt.auth'])->group(function () {
      Route::get('/my-clients', [LeadController::class, 'getMyClients']);
    Route::get('/logs', [DashboardController::class, 'index'])->name('logs');
    // أو يمكنك استخدام هذا الاسم
    Route::get('/leads/clients', [LeadController::class, 'getClientsList']);
    
    Route::get('/properties/map', [ListingController::class, 'map']);
    Route::get('/suggestions', [SuggestionController::class, 'index']);
    Route::post('/suggestions', [SuggestionController::class, 'store']);
    Route::get('dashboard/stats',[DashboardController::class,'getStats']);
    Route::get('/dashboard/listings-statistics', [DashboardController::class, 'getListingsStatistics']);
    Route::get('/dashboard/active-agents', [DashboardController::class, 'getActiveAgents']);
    Route::get('/dashboard/leads-overview', [DashboardController::class, 'getLeadsOverview']);
    Route::get('/dashboard/my-latest-orders', [DashboardController::class, 'getMyLatestOrders']);
    Route::get('/dashboard/my-latest-requests', [DashboardController::class, 'getMyLatestRequests']);
    Route::get('/dashboard/top-agent-performance', [DashboardController::class, 'getTopAgentPerformance']);
    Route::get(
    '/dashboard/admin/latest-requests',
    [DashboardController::class, 'getAdminLatestRequests']
);
  Route::get('/dashboard/property-types-with-listings', [DashboardController::class, 'getPropertyTypesWithListings']);
    Route::get('/dashboard/listings-status-summary', [DashboardController::class, 'getListingsStatusSummary']);
    Route::get('/dashboard/kanban-task-summary', [DashboardController::class, 'getKanbanTaskSummary']);
    Route::get('/dashboard/schedule', [DashboardController::class, 'getDashboardSchedule']);

   Route::get('/sidebar/counts', [DashboardController::class, 'getSidebarCounts']);


    // Chat
    Route::prefix('chat')->group(function () {
        Route::get('/users-search', [ChatController::class, 'usersSearch']);
        Route::post('/start', [ChatController::class, 'start']);
        Route::get('/unread-count', [ChatController::class, 'unreadCount']);
        Route::get('/conversations', [ChatController::class, 'conversations']);
        Route::get('/messages/{conversation}', [ChatController::class, 'messages']);
        Route::post('/send', [ChatController::class, 'send']);
        Route::post('/read', [ChatController::class, 'read']);
        Route::get('/admin/conversations', [ChatController::class, 'adminConversations']);
        Route::delete('/admin/conversations/{conversation}', [ChatController::class, 'adminDeleteConversation']);
    });

    // Admin email blast (SUPER_ADMIN only)
    Route::get('/agents-emails', [AdminEmailController::class, 'agentsEmails']);
    Route::post('/send-email', [AdminEmailController::class, 'sendEmail']);
    
    
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::post('/profile/change-password', [ProfileController::class, 'changePassword']);


     Route::get('/team/hierarchy', [TeamController::class, 'getTeamHierarchy']);
    Route::get('/team/my-team', [TeamController::class, 'getMyTeam']);
    Route::get('/users/with-children', [UserController::class, 'getUsersWithChildren']);
    
     // User routes
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('users/{id}/biometric-code',[UserController::class,'updateBiometricCode']);
    Route::get('/users/{user}/permissions', [UserController::class, 'permissions']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}/status', [UserController::class, 'updateStatus']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::get('/users/managers/available', [UserController::class, 'getAvailableManagers']);
    Route::get('/users/statistics', [UserController::class, 'getStatistics']);
    Route::get('/users/{user}/team-members', [UserController::class, 'getTeamMembers']);
    Route::get('/users/{user}/team-members/recursive', [UserController::class, 'getTeamMembersRecursive']);
        Route::get('/users/online/current', [UserController::class, 'getOnlineUsers']);
    Route::get('/users/login-activity', [UserController::class, 'getLoginActivity']);
// Role Routes
Route::apiResource('roles', RoleController::class);
Route::get('/permissions', [RoleController::class, 'permissions']);
// User Role Routes
Route::prefix('users/{user}')->group(function () {
    Route::post('/assign-role', [UserRoleController::class, 'assignRole']);
    Route::delete('/remove-role/{roleName}', [UserRoleController::class, 'removeRole']);
    Route::post('/sync-roles', [UserRoleController::class, 'syncRoles']);
    Route::get('/roles', [UserRoleController::class, 'getUserRoles']);
});


// leadssssss

Route::apiResource('leads', LeadController::class);
Route::get('leads/get/duplicate/{lead_id}',[LeadController::class,'getDuplicate']);
Route::post('/leads/{lead}/change-stage', [LeadController::class, 'changeStage']);
Route::post('/leads/{lead}/assign-responsible-person', [LeadController::class, 'assignResponsiblePerson']);
Route::put('/leads/{lead}/extra-client-requirements', [LeadController::class, 'updateExtraClientRequirements']);
Route::get('/available-responsible-persons', [LeadController::class, 'getAvailableResponsiblePersons']);
Route::post('/check-revert', [LeadController::class, 'checkRevert']);
Route::get('get/lead/branch_source',[StageController::class,'getLeadBranchSource']);
Route::get('/get-offices', [StageController::class, 'getOffices']);
Route::prefix('leads')->group(function(){
    Route::get('/{lead}/integration-project', [LeadController::class, 'leadIntegrationProject']);
    Route::get('/{leadId}/history',[LeadController::class, 'history']);
    Route::get('/{leadId}/history/view',[LeadController::class, 'view_history']);
  // Lead-specific activities
    Route::get('/{leadId}/activities', [LeadActivityController::class, 'getLeadActivities']);
    Route::post('/activities', [LeadActivityController::class, 'storeActivity']);
    Route::put('/activities/{id}', [LeadActivityController::class, 'updateActivity']);
    Route::delete('/activities/{id}', [LeadActivityController::class, 'destroyActivity']);
    
    // Activity status
    Route::patch('/activities/{id}/toggle-completion', [LeadActivityController::class, 'toggleActivityCompletion']);
    
    // User's activities
    Route::get('/my-activities/upcoming', [LeadActivityController::class, 'getUpcomingActivities']);
    Route::get('/my-activities/overdue', [LeadActivityController::class, 'getOverdueActivities']);
    Route::get('/my-activities/completed', [LeadActivityController::class, 'getCompletedActivities']);
    
    // Comments routes
    Route::get('/{leadId}/comments', [LeadActivityController::class, 'getLeadComments']);
    Route::post('/add/new/comments', [LeadActivityController::class, 'storeComment']);
    Route::put('/update/comments/{id}', [LeadActivityController::class, 'updateComment']);
    Route::delete('/comments/{id}', [LeadActivityController::class, 'destroyComment']);
    
    // Comment attachments
    Route::post('/comments/{commentId}/attachments', [LeadActivityController::class, 'addCommentAttachments']);
    Route::delete('/comments/{commentId}/attachments/{attachmentId}', [LeadActivityController::class, 'destroyCommentAttachment']);
    Route::get('mentions/agents',[LeadActivityController::class,'get_mentions']);
    
    Route::delete('comments/{comment}/admin-delete', [LeadActivityController::class, 'destroyCommentByAdmin']);
    Route::delete('activities/{activity}/admin-delete', [LeadActivityController::class, 'destroyActivityByAdmin']);
    
      Route::delete('{leadId}/comments/all', [LeadActivityController::class, 'destroyAllComments']);
        Route::delete('{leadId}/activities/all', [LeadActivityController::class, 'destroyAllActivities']);
        Route::delete('{leadId}/activities_comments/all', [LeadActivityController::class, 'destroyAllActivitieComments']);
        
        Route::post('{leadId}/comments/restore-all', [LeadActivityController::class, 'restoreAllComments']);
        Route::post('{leadId}/activities/restore-all', [LeadActivityController::class, 'restoreAllActivities']);
        
        
          Route::prefix('reports')->group(function () {
            Route::get('/users', [ReportController::class, 'userReport']);
            Route::get('/users/{userId}', [ReportController::class, 'singleUserReport']);
            Route::get('/months', [ReportController::class, 'getMonthOptions']);
            Route::get('/years', [ReportController::class, 'getYearOptions']);
        });
});
  // =================sources=============
        Route::apiResource('sources', SourceController::class);

    // Integrations (Meta forms connect)
    Route::apiResource('integrations', IntegrationController::class);
    Route::get('integrations/meta/pages', [IntegrationController::class, 'fetchMetaPages']);
    Route::get('integrations/meta/forms', [IntegrationController::class, 'fetchMetaForms']);
    Route::post('integrations/meta', [IntegrationController::class, 'fetchMetaForms']); // alias for same action
    // Route::post('integrations', [IntegrationController::class, 'store']);
    Route::patch('integrations/{integration}/toggle-active', [IntegrationController::class, 'toggleActive']);
    // Route::delete('integrations/{integration}', [IntegrationController::class, 'destroy']);
    Route::get('integrations/meta/form-fields/{form_id}',[IntegrationController::class,'getFormFields']);
    
Route::post('/search-alerts',[ListingController::class, 'store_search_alert']);

    /** Mobile Kanban API — versioned, additive; does not replace existing routes. */
    Route::prefix('v1/mobile')->group(function () {
        Route::get('/kanban', [MobileKanbanController::class, 'show']);
        Route::post('/leads/{lead}/move', [MobileLeadMoveController::class, 'store']);
    });

Route::prefix('listings')->group(function(){
      Route::patch('/properties/{listing}/approve', [ListingController::class, 'approve']);
    Route::patch('/properties/{listing}/reject', [ListingController::class, 'reject']);
    Route::get('/pending-approvals', [ListingController::class, 'getPendingApprovals']);
    Route::post('/batch-approve', [ListingController::class, 'batchApprove']);
    
    
        Route::post('/areas/coordinates/bulk', [AreaController::class, 'bulkUpdateCoordinates']);
     Route::get('/properties/map', [ListingController::class, 'map']);
     Route::get('matching',[ListingController::class,'getMatchingListings']);
        // Hot deal approval routes
    Route::get('hot-deal-requests/pending', [HotDealApprovalController::class, 'pendingRequests']);
    Route::post('hot-deal-requests/{requestId}/process', [HotDealApprovalController::class, 'processRequest']);
    
    Route::post('/properties/{id}/generate-offer', [ListingController::class, 'generateOffer']);
Route::get('/properties/{id}/offers', [ListingController::class, 'getOffers']);
    Route::get('/{id}/comments', [ListingCommentController::class, 'index']);
    Route::get('/{id}/comments/stats', [ListingCommentController::class, 'getStats']);
    Route::post('/{id}/comments', [ListingCommentController::class, 'store']);
    Route::put('/comments/{commentId}', [ListingCommentController::class, 'update']);
    Route::delete('/comments/{commentId}', [ListingCommentController::class, 'destroy']);
        // ===============areas======================
        Route::apiResource('areas', AreaController::class);
        Route::get('areas/type/{type}', [AreaController::class, 'byType']);
        Route::get('areas/{area}/children', [AreaController::class, 'children']);
        
        // =================property types=============
        Route::apiResource('property-types', PropertyTypeController::class);
        Route::get('property-types/{propertyType}/children', [PropertyTypeController::class, 'children']);


        // Owner CRUD Routes
        Route::apiResource('owners', OwnerController::class);
        Route::get('/owners/{owner}/properties', [OwnerController::class, 'getOwnerProperties']);

        Route::prefix('owners')->group(function () {
       
                
            Route::get('/locations/available', [OwnerController::class, 'getLocationsByResidency'])
                ->name('owners.available-locations');
                
            Route::get('/statistics/summary', [OwnerController::class, 'getStatistics'])
                ->name('owners.statistics');
        });

        // developer CRUD Routes
        Route::apiResource('developers', DeveloperController::class);

        Route::prefix('developers')->group(function () {
            Route::get('/statistics/summary', [DeveloperController::class, 'getStatistics'])
                ->name('developers.statistics');
        });

        // listings CRUD Routes
        Route::apiResource('properties', ListingController::class);
         Route::prefix('properties')->group(function () {
                Route::patch('/{id}/toggle-archive', [ListingController::class, 'toggleArchive']);
                Route::patch('/{id}/toggle-status', [ListingController::class, 'toggleStatus']);
                Route::patch('/{id}/payment-breakdown', [ListingController::class, 'updatePaymentBreakdown']);
                Route::patch('/{id}/assign-agent', [ListingController::class, 'assignAgent']);
                Route::patch('/{id}/mark-converted', [ListingController::class, 'markAsConverted']);
                Route::patch('/{id}/revert-converted', [ListingController::class, 'revertFromConverted']);
                Route::post('/{owner}/soldBy', [ListingController::class, 'changeOwner']);
                // routes/api.php
                Route::patch('/{id}/mark-rented', [ListingController::class, 'markAsRented']);
                Route::patch('/{id}/revert-rented', [ListingController::class, 'revertFromRented']);
                Route::post('/{property}/set-hero-image', [ListingController::class, 'setHeroImage']);
                Route::get('/statistics/summary', [ListingController::class, 'getStatistics'])->name('listings.statistics');
                Route::delete('/{listing}/floor-plans/{floorPlan}', [ListingController::class, 'deleteFloorPlan'])->name('floor-plans.destroy');
                Route::delete('/{listing}/gallery/{gallery}', [ListingController::class, 'deleteGalleryImage'])->name('gallery.destroy');
                Route::delete('/{listing}/additional-documents/{document}', [ListingController::class, 'deleteAdditionalDocument'])->name('listings.additional-documents.destroy');
                Route::post('/validate-unit-number',  [ListingController::class, 'validateUnitNumber']);
         });
         Route::get('/agents', [ListingController::class, 'getAgents']);
         Route::post('/agent/vacation',[ListingAccessRequestController::class,'setVacationMode']);
         Route::get('/agent/vacation-mode',[ListingAccessRequestController::class,'getVacationMode']);
         // Manager (listing_team=1) can manage vacation for a team member
         Route::get('/agent/{user}/vacation-mode',[ListingAccessRequestController::class,'getUserVacationMode']);
         Route::post('/agent/{user}/vacation',[ListingAccessRequestController::class,'setUserVacationMode']);
        Route::prefix('access-requests')->group(function () {
            Route::post('/{listing}/request', [ListingAccessRequestController::class, 'store']);
            Route::get('/my-requests', [ListingAccessRequestController::class, 'myRequests']);
            Route::get('/my-orders', [ListingAccessRequestController::class, 'myOrders']);
            Route::get('/{listing}/check-access', [ListingAccessRequestController::class, 'checkAccess']);
            Route::put('/{accessRequest}/respond', [ListingAccessRequestController::class, 'respond']);
                Route::get('status/{listing}', [ListingAccessRequestController::class, 'getStatus']);
                Route::post('{id}/cancel', [ListingAccessRequestController::class, 'cancelRequest']);
            Route::put('{accessRequest}/convert', [ListingAccessRequestController::class, 'markAsConverted']);
            Route::put('{accessRequest}/update-time', [ListingAccessRequestController::class, 'updateViewingTime']);
            // Manually log an already-approved viewing (used by /my-viewings "Add Viewing" button)
            Route::post('approved-viewing', [ListingAccessRequestController::class, 'storeApprovedViewing']);
            // Add this route
            Route::post('{accessRequest}/review', [ListingAccessRequestController::class, 'submitReview']);
        });


          // =================unit views=============
        Route::apiResource('unit_views', UnitViewController::class);

        // =================Layout Types=============
        Route::apiResource('layout_types', LayoutTypeController::class);
        
        
         // =================features=============
        Route::apiResource('features', FeatureController::class);
        
           // =================projects=============
        Route::apiResource('projects', ProjectController::class);
        Route::get('/projects/{project_id}/areas', [AreaController::class, 'getProjectAreas']);

       Route::get('/projects/{project}/floor-plans', [ProjectController::class, 'getFloorPlans']);
       Route::put('/projects/floor-plan-images/{id}/name', [ProjectController::class, 'updateFloorPlanName']);


            Route::get('projects/{projectId}/floor-plans/{areaId}', [ProjectController::class, 'getFloorPlansByArea'])
                 ->whereNumber(['projectId', 'areaId']); 
            
            Route::post('projects/{id}/update/floor-plans', [ProjectController::class, 'updateFloorPlans']);
            Route::put('projects/floor-plan-images/{id}/name', [ProjectController::class, 'updateFloorPlanName']);
});    
});
Route::middleware('auth:api')->group(function () {
    Route::post('/user-invitations', [UserInvitationController::class, 'store']);
    Route::post('/user-invitations/{id}/resend', [UserInvitationController::class, 'resend']);
    Route::get('/user-invitations', [UserInvitationController::class, 'index']);
});

// Public routes for invitation registration
Route::get('/invitation/{token}', [UserInvitationController::class, 'validateInvitation']);
Route::post('/register', [UserInvitationController::class, 'registerWithInvitation']);
});