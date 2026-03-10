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
use App\Http\Controllers\Api\SuggestionController;
use App\Http\Controllers\Api\Deal\DealActivityController;

Route::get('/test-email', function () {
    try {
        // Test basic email
        Mail::raw('This is a test email', function ($message) {
            $message->to('')
                    ->subject('Test Email');
        });
        
        return 'Email sent successfully! Check your email inbox.';
    } catch (\Exception $e) {
        return 'Email error: ' . $message;
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

Route::get('/webhook/facebook', [IntegrationController::class, 'verifyWebhook']);
Route::post('/webhook/facebook', [IntegrationController::class, 'handleWebhook']);
Route::post('/website-lead', [IntegrationController::class, 'store_website']);
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::get('users/role/{role}', [UserController::class, 'getByRole']);

    Route::middleware(['jwt.auth'])->group(function () {
  
        Route::post('logout', [AuthController::class,'logout']);
        Route::get('notifications',[AuthController::class,'notifications']);
        Route::post('notifications/read-all', [AuthController::class, 'markAllNotificationsAsRead']);
        Route::any('notifications/{id}/read', [AuthController::class, 'markNotificationAsRead']);
        Route::delete('notifications/{id}', [AuthController::class, 'deleteNotification']);
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

});
Route::get('/teams-with-leads', [StageController::class, 'getTeamsWithLeads'])->middleware('jwt.auth');

Route::middleware('jwt.auth')->group(function () {
    
    // === Lead Conversion API ===
    Route::post('/leads/convert/to-deal', [LeadConversionController::class, 'convert']);
    Route::get('/leads/{lead}/can-convert', [LeadConversionController::class, 'canConvert']);
    
    // === Deals API ===
    Route::prefix('deals')->group(function () {
        Route::get('/', [DealController::class, 'index']);
        Route::get('/grouped-by-stage', [DealController::class, 'getDealsGroupedByStage']);
        Route::get('/{deal}', [DealController::class, 'show']);
        Route::put('/{deal}', [DealController::class, 'update']);
        Route::post('/store/new', [LeadConversionController::class, 'store']);
        Route::post('/{id}/update-stage', [DealController::class, 'updateStage']);
        Route::post('/check-stage-requirements', [DealController::class, 'checkStageRequirements']);
        Route::post('/{id}/update-partial', [DealController::class, 'updatePartial']);
         Route::post('/{id}/change-stage', [DealController::class, 'changeStage']);
        Route::post('/get-stage-required-fields', [DealController::class, 'getStageRequiredFields']);

    });
    
});
Route::get('leads/integration', [LeadController::class,'storeIntegration']);

Route::middleware(['jwt.auth'])->group(function () {
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

   Route::get('/sidebar/counts', [DashboardController::class, 'getSidebarCounts']);

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
Route::get('/available-responsible-persons', [LeadController::class, 'getAvailableResponsiblePersons']);
Route::post('/check-revert', [LeadController::class, 'checkRevert']);
Route::get('get/lead/branch_source',[StageController::class,'getLeadBranchSource']);
Route::prefix('leads')->group(function(){
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
    
    
Route::post('/search-alerts',[ListingController::class, 'store_search_alert']);

Route::prefix('listings')->group(function(){
    
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
                Route::patch('/{id}/assign-agent', [ListingController::class, 'assignAgent']);
                Route::patch('/{id}/mark-converted', [ListingController::class, 'markAsConverted']);
                Route::patch('/{id}/revert-converted', [ListingController::class, 'revertFromConverted']);
                Route::post('/{owner}/soldBy', [ListingController::class, 'changeOwner']);

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

