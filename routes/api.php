<?php

use App\Http\Controllers\Api\ActivityCompletionController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AssignmentContentController;
use App\Http\Controllers\Api\AssignmentSubmissionController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\BlockController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\CertificateContentController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\CertificateTemplateController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseSectionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentationContentController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\EventContentController;
use App\Http\Controllers\Api\EnvironmentController;
use App\Http\Controllers\Api\EnvironmentCredentialsController;
use App\Http\Controllers\Api\FeedbackContentController;
use App\Http\Controllers\Api\FeedbackSubmissionController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\LessonContentController;
use App\Http\Controllers\Api\LessonQuestionResponseController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentGatewayController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuizContentController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\TemplateActivityQuestionController;
use App\Http\Controllers\Api\TextContentController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\ValidationController;
use App\Http\Controllers\Api\VideoContentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Api\StorefrontController;
use App\Http\Controllers\Api\Onboarding\AfterPlanSelectionOnboarding;
use App\Http\Controllers\Api\ReferralEnvironmentController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\Onboarding\StandaloneOnboardingController;
use App\Http\Controllers\Api\Onboarding\SupportedOnboardingController;
use App\Http\Controllers\Api\Onboarding\DemoOnboardingController;
use App\Http\Controllers\Api\LessonDiscussionController;
use Illuminate\Support\Facades\Broadcast;


Route::middleware('auth:sanctum')->post('/broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
});

// Health check endpoint for monitoring and deployment verification
Route::get('/health', function() {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'environment' => config('app.env'),
        'version' => config('app.version', '1.0.0'),
    ]);
});

// Onboarding Routes
Route::prefix('onboarding')->group(function () {
    // Standalone plan onboarding
    Route::post('/standalone', [StandaloneOnboardingController::class, 'store']);
    
    // Supported plan onboarding
    Route::post('/supported', [SupportedOnboardingController::class, 'store']);
    
    // Demo plan onboarding
    Route::post('/demo', [DemoOnboardingController::class, 'store']);
    
    // Get available plans
    Route::get('/plans', [PlanController::class, 'getOnboardingPlans']);
    Route::post('/referral/validate', [ReferralController::class, 'validate']);
});

// Queue status endpoint
Route::get('/health/queue', function () {
    $queueSize = DB::table('jobs')->count();
    $failedJobs = DB::table('failed_jobs')->count();
    
    $status = $failedJobs > 0 ? 'warning' : 'ok';
    
    $queueData = [
        'status' => $status,
        'timestamp' => now()->toIso8601String(),
        'queue_size' => $queueSize,
        'failed_jobs' => $failedJobs,
        'driver' => config('queue.default'),
        'queues' => ['default', 'emails', 'notifications'],
    ];
    
    // Send notification email if there are failed jobs
    if ($failedJobs > 0) {
        // Check if we've already sent a notification recently (within the last hour)
        $cacheKey = 'queue_failure_notification_sent';
        if (!cache()->has($cacheKey)) {
            // Send the email notification
            Mail::to('kevinliboire@gmail.com')
                ->send(new \App\Mail\QueueFailureNotification($queueData));
            
            // Cache the notification to prevent sending too many emails
            cache()->put($cacheKey, now(), now()->addHour());
        }
    }
    
    return response()->json($queueData);
});

Route::get('/debug/environments', function() {
    $environments = \App\Models\Environment::where('is_active', true)
        ->get(['id', 'name', 'primary_domain', 'additional_domains']);
    
    return response()->json([
        'current_domain' => request()->getHost(),
        'environments' => $environments,
    ]);
});

// User authentication routes
Route::get('/user', function (Request $request) {
    $user = $request->user();
    $response = $user->toArray();
    
    // Extract environment ID from token abilities
    $token = $request->bearerToken();
    $tokenId = explode('|', $token)[0];
    $tokenModel = $user->tokens()->find($tokenId);
    
    if ($tokenModel) {
        $abilities = $tokenModel->abilities;
        $environmentId = null;
        
        // Find environment_id in abilities
        foreach ($abilities as $ability) {
            if (strpos($ability, 'environment_id:') === 0) {
                $environmentId = (int) substr($ability, strlen('environment_id:'));
                break;
            }
        }
        
        $response['environment_id'] = $environmentId;
    } else {
        $response['environment_id'] = null;
    }
    
    return response()->json($response);
})->middleware('auth:sanctum');

// API Authentication Routes
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [ResetPasswordController::class, 'reset']);

// Token management routes
Route::post('/tokens', [TokenController::class, 'createToken']);
Route::delete('/tokens', [TokenController::class, 'revokeTokens'])->middleware('auth:sanctum');

// Sales Platform Authentication Routes
Route::post('/admin/sales/tokens', [\App\Http\Controllers\Api\Sales\AuthController::class, 'login']);
Route::get('/admin/sales/user', [\App\Http\Controllers\Api\Sales\AuthController::class, 'user'])->middleware('auth:sanctum');
Route::post('/admin/sales/logout', [\App\Http\Controllers\Api\Sales\AuthController::class, 'logout'])->middleware('auth:sanctum');

// Environment routes
Route::get('/current-environment', [EnvironmentController::class, 'getCurrentEnvironment']);

// Public Plan routes
Route::get('/public/plans', [PlanController::class, 'index']);
Route::get('/public/plans/{id}', [PlanController::class, 'show']);
Route::get('/public/plans/type/{type}', [PlanController::class, 'getByType']);
Route::post('/public/plans/compare', [PlanController::class, 'compare']);
Route::post('/public/onboarding/after-plan-selection', [AfterPlanSelectionOnboarding::class, 'store']);
// Environment management routes
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('environments', EnvironmentController::class);
    Route::get('environments/{id}/users', [EnvironmentController::class, 'getUsers']);
    Route::post('environments/{id}/users', [EnvironmentController::class, 'addUser']);
    Route::delete('environments/{id}/users/{userId}', [EnvironmentController::class, 'removeUser']);
    
    // Environment credentials routes
    Route::get('environment-credentials/{environmentId}', [EnvironmentCredentialsController::class, 'show']);
    Route::put('environment-credentials/{environmentId}', [EnvironmentCredentialsController::class, 'update']);
    Route::delete('environment-credentials/{environmentId}', [EnvironmentCredentialsController::class, 'destroy']);
});

// Dashboard Routes
Route::middleware('auth:sanctum')->group(function () {
    // Dashboard data
    Route::get('/dashboard', [DashboardController::class, 'getDashboardData']);
});

// Sales Dashboard Routes
Route::middleware('auth:sanctum')->group(function () {
    // Admin dashboard stats
    Route::get('/sales/admin/stats', [App\Http\Controllers\Api\Sales\SalesDashboardController::class, 'getAdminStats']);
    
    // Sales agent dashboard stats
    Route::get('/sales/agent/stats', [App\Http\Controllers\Api\Sales\SalesDashboardController::class, 'getAgentStats']);
    
    // Performance by period
    Route::get('/sales/performance', [App\Http\Controllers\Api\Sales\SalesDashboardController::class, 'getPerformanceByPeriod']);

      // Referral listing and creation
      Route::get('/sales/admin/referrals', [App\Http\Controllers\Api\ReferralController::class, 'index']);
      Route::post('/sales/admin/referrals', [App\Http\Controllers\Api\ReferralController::class, 'store']);
      
      // Referral statistics (must come before wildcard routes)
      Route::get('/sales/admin/referrals/stats', [App\Http\Controllers\Api\ReferralController::class, 'getStats']);
      
      // Validate referral code
      Route::post('/sales/admin/referrals/validate', [App\Http\Controllers\Api\ReferralController::class, 'validate']);
      
      // Individual referral operations (wildcard routes come last)
      Route::get('/sales/admin/referrals/{id}', [App\Http\Controllers\Api\ReferralController::class, 'show']);
      Route::put('/sales/admin/referrals/{id}', [App\Http\Controllers\Api\ReferralController::class, 'update']);
      Route::delete('/sales/admin/referrals/{id}', [App\Http\Controllers\Api\ReferralController::class, 'destroy']);
});

// Sales Agent Management Routes
Route::middleware('auth:sanctum')->group(function () {
    // Sales agent listing and creation
    Route::get('/sales-agents', [App\Http\Controllers\Api\Sales\SalesAgentController::class, 'index']);
    Route::post('/sales-agents', [App\Http\Controllers\Api\Sales\SalesAgentController::class, 'store']);
    
    // Individual sales agent operations
    Route::get('/sales-agents/{id}', [App\Http\Controllers\Api\Sales\SalesAgentController::class, 'show']);
    Route::put('/sales-agents/{id}', [App\Http\Controllers\Api\Sales\SalesAgentController::class, 'update']);
    Route::delete('/sales-agents/{id}', [App\Http\Controllers\Api\Sales\SalesAgentController::class, 'destroy']);
    
    // Sales agent performance
    Route::get('/sales-agents/{id}/performance', [App\Http\Controllers\Api\Sales\SalesAgentController::class, 'getPerformance']);
    
    // Sales agent referrals
    Route::get('/sales-agents/{id}/referrals', [App\Http\Controllers\Api\Sales\SalesAgentController::class, 'getReferrals']);
});

// Template Management Routes
Route::middleware('auth:sanctum')->group(function () {
    // Template routes
    Route::get('/templates', [TemplateController::class, 'index']);
    Route::post('/templates', [TemplateController::class, 'store']);
    Route::get('/templates/{id}', [TemplateController::class, 'show']);
    Route::put('/templates/{id}', [TemplateController::class, 'update']);
    Route::delete('/templates/{id}', [TemplateController::class, 'destroy']);
    Route::post('/templates/{id}/duplicate', [TemplateController::class, 'duplicate']);
    
    // Block routes
    Route::get('/templates/{templateId}/blocks', [BlockController::class, 'index']);
    Route::post('/templates/{templateId}/blocks', [BlockController::class, 'store']);
    Route::get('/blocks/{id}', [BlockController::class, 'show']);
    Route::put('/blocks/{id}', [BlockController::class, 'update']);
    Route::delete('/blocks/{id}', [BlockController::class, 'destroy']);
    Route::post('/templates/{templateId}/blocks/reorder', [BlockController::class, 'reorder']);
    
    // Activity routes
    Route::get('/blocks/{blockId}/activities', [ActivityController::class, 'index']);
    Route::post('/blocks/{blockId}/activities', [ActivityController::class, 'store']);
    Route::get('/blocks/activities/{id}', [ActivityController::class, 'show']);
    Route::put('/blocks/activities/{id}', [ActivityController::class, 'update']);
    Route::delete('/blocks/activities/{id}', [ActivityController::class, 'destroy']);
    Route::delete('/blocks/{blockId}/activities/batch', [ActivityController::class, 'batchDestroy']);
    Route::post('/blocks/activities/{id}/duplicate', [ActivityController::class, 'duplicate']);
    Route::post('/blocks/{blockId}/activities/reorder', [ActivityController::class, 'reorder']);
    
    // Content Type routes
    // Text Content routes
    Route::post('/activities/{activityId}/text', [TextContentController::class, 'store']);
    Route::get('/activities/{activityId}/text', [TextContentController::class, 'show']);
    Route::put('/activities/{activityId}/text', [TextContentController::class, 'update']);
    Route::delete('/activities/{activityId}/text', [TextContentController::class, 'destroy']);
    
    // Video Content routes
    Route::post('/activities/{activityId}/video', [VideoContentController::class, 'store']);
    Route::get('/activities/{activityId}/video', [VideoContentController::class, 'show']);
    Route::put('/activities/{activityId}/video', [VideoContentController::class, 'update']);
    Route::delete('/activities/{activityId}/video', [VideoContentController::class, 'destroy']);
    
    // Quiz Content routes
    Route::post('/activities/{activityId}/quiz', [QuizContentController::class, 'store']);
    Route::get('/activities/{activityId}/quiz', [QuizContentController::class, 'show']);
    Route::put('/activities/{activityId}/quiz', [QuizContentController::class, 'update']);
    Route::delete('/activities/{activityId}/quiz', [QuizContentController::class, 'destroy']);
    
    // Question routes for individual question management
    Route::get('/activities/{activityId}/questions', [QuestionController::class, 'index']);
    Route::post('/activities/{activityId}/questions', [QuestionController::class, 'store']);
    Route::put('/activities/{activityId}/questions/{questionId}', [QuestionController::class, 'update']);
    Route::delete('/activities/{activityId}/questions/{questionId}', [QuestionController::class, 'destroy']);
    
    // Template activity question routes for browsing and importing questions
    Route::get('/templates/{templateId}/questions', [TemplateActivityQuestionController::class, 'getTemplateQuestions']);
    Route::post('/activities/{activityId}/import-questions', [TemplateActivityQuestionController::class, 'importQuestions']);
    
    // Lesson Content routes
    Route::post('/activities/{activityId}/lesson', [LessonContentController::class, 'store']);
    Route::get('/activities/{activityId}/lesson', [LessonContentController::class, 'show']);
    Route::put('/activities/{activityId}/lesson', [LessonContentController::class, 'update']);
    Route::delete('/activities/{activityId}/lesson', [LessonContentController::class, 'destroy']);
    
    // Lesson Question Response routes

  
    
    // Quiz Submission Routes
    Route::post('/quiz/{quizContentId}/submissions', [\App\Http\Controllers\QuizSubmissionController::class, 'store']);
    Route::get('/quiz/{quizContentId}/submissions', [\App\Http\Controllers\QuizSubmissionController::class, 'index']);
    Route::get('/quiz/submissions/{submissionId}', [\App\Http\Controllers\QuizSubmissionController::class, 'show']);
    Route::get('/enrollments/{enrollmentId}/quiz-submissions', [\App\Http\Controllers\QuizSubmissionController::class, 'getByEnrollment']);
    Route::get('/lessons/{lessonId}/responses', [LessonQuestionResponseController::class, 'getResponses']);
    
    // Assignment Content routes
    Route::post('/activities/{activityId}/assignment', [AssignmentContentController::class, 'store']);
    Route::get('/activities/{activityId}/assignment', [AssignmentContentController::class, 'show']);
    Route::put('/activities/{activityId}/assignment', [AssignmentContentController::class, 'update']);
    Route::delete('/activities/{activityId}/assignment', [AssignmentContentController::class, 'destroy']);
    
    // Assignment Submission routes
    Route::get('/activities/{activityId}/submissions', [AssignmentSubmissionController::class, 'index']);
    Route::post('/activities/{activityId}/submissions', [AssignmentSubmissionController::class, 'store']);
    Route::get('/activities/{activityId}/submissions/{submissionId}', [AssignmentSubmissionController::class, 'show']);
    Route::put('/activities/{activityId}/submissions/{submissionId}/grade', [AssignmentSubmissionController::class, 'grade']);
    
    // Documentation Content routes
    Route::post('/activities/{activityId}/documentation', [DocumentationContentController::class, 'store']);
    Route::get('/activities/{activityId}/documentation', [DocumentationContentController::class, 'show']);
    
    // Certificate Template routes
    Route::get('/certificate-templates', [CertificateTemplateController::class, 'index']);
    Route::post('/certificate-templates', [CertificateTemplateController::class, 'store']);
    Route::get('/certificate-templates/{id}', [CertificateTemplateController::class, 'show']);
    Route::put('/certificate-templates/{id}/set-default', [CertificateTemplateController::class, 'setDefault']);
    Route::delete('/certificate-templates/{id}', [CertificateTemplateController::class, 'destroy']);
    
    // Certificate Generation routes
    Route::post('/activities/{activityId}/certificate-content/{id}/generate', [CertificateController::class, 'generate']);
    Route::put('/activities/{activityId}/documentation', [DocumentationContentController::class, 'update']);
    Route::delete('/activities/{activityId}/documentation', [DocumentationContentController::class, 'destroy']);
    
    // Event Content routes
    Route::post('/activities/{activityId}/event', [EventContentController::class, 'store']);
    Route::get('/activities/{activityId}/event', [EventContentController::class, 'show']);
    Route::put('/activities/{activityId}/event', [EventContentController::class, 'update']);
    Route::delete('/activities/{activityId}/event', [EventContentController::class, 'destroy']);
    
    // Certificate Content routes
    Route::post('/activities/{activityId}/certificate', [CertificateContentController::class, 'store']);
    Route::get('/activities/{activityId}/certificate', [CertificateContentController::class, 'show']);
    Route::put('/activities/{activityId}/certificate', [CertificateContentController::class, 'update']);
    Route::delete('/activities/{activityId}/certificate', [CertificateContentController::class, 'destroy']);
    
    // Feedback Content routes
    Route::post('/activities/{activityId}/feedback', [FeedbackContentController::class, 'store']);
    Route::get('/activities/{activityId}/feedback', [FeedbackContentController::class, 'show']);
    Route::put('/activities/{activityId}/feedback', [FeedbackContentController::class, 'update']);
    Route::delete('/activities/{activityId}/feedback', [FeedbackContentController::class, 'destroy']);
    
    // Feedback Submission routes
    Route::get('/feedback/{feedbackContentId}/submissions', [FeedbackSubmissionController::class, 'index']);
    Route::post('/feedback/{feedbackContentId}/submissions', [FeedbackSubmissionController::class, 'store']);
    Route::get('/feedback/submissions/{submissionId}', [FeedbackSubmissionController::class, 'show']);
    Route::put('/feedback/submissions/{submissionId}', [FeedbackSubmissionController::class, 'update']);
    Route::post('/feedback/submissions/{submissionId}/submit', [FeedbackSubmissionController::class, 'submit']);
    Route::delete('/feedback/submissions/{submissionId}', [FeedbackSubmissionController::class, 'destroy']);
    Route::get('/feedback/user/submissions', [FeedbackSubmissionController::class, 'getUserSubmissions']);
    Route::get('/feedback/user/{userId}/submissions', [FeedbackSubmissionController::class, 'getUserSubmissionsById']);
    
    // Course Delivery routes
    // Course routes
    Route::get('/courses', [CourseController::class, 'index']);
    Route::post('/courses', [CourseController::class, 'store']);
    Route::get('/courses/{id}', [CourseController::class, 'show']);
    Route::put('/courses/{id}', [CourseController::class, 'update']);
    Route::delete('/courses/{id}', [CourseController::class, 'destroy']);
    Route::post('/courses/{id}/publish', [CourseController::class, 'publish']);
    Route::post('/courses/{id}/archive', [CourseController::class, 'archive']);
    Route::post('/courses/{id}/duplicate', [CourseController::class, 'duplicate']);
    
    // Course Section routes
    Route::get('/courses/{courseId}/sections', [CourseSectionController::class, 'index']);
    Route::post('/courses/{courseId}/sections', [CourseSectionController::class, 'store']);
    Route::get('/sections/{id}', [CourseSectionController::class, 'show']);
    Route::put('/sections/{id}', [CourseSectionController::class, 'update']);
    Route::delete('/sections/{id}', [CourseSectionController::class, 'destroy']);
    Route::post('/courses/{courseId}/sections/reorder', [CourseSectionController::class, 'reorder']);
    
    // Enrollment routes
    Route::get('/enrollments', [EnrollmentController::class, 'index']);
    Route::post('/enrollments', [EnrollmentController::class, 'store']);
    Route::get('/enrollments/{id}', [EnrollmentController::class, 'show']);
    Route::put('/enrollments/{id}', [EnrollmentController::class, 'update']);
    Route::delete('/enrollments/{id}', [EnrollmentController::class, 'destroy']);
    Route::get('/my-enrollments', [EnrollmentController::class, 'myEnrollments']);
    Route::get('/courses/{courseId}/enrollments', [EnrollmentController::class, 'courseEnrollments']);
    
    // Activity Completion routes
    Route::get('/enrollments/{enrollmentId}/activity-completions', [ActivityCompletionController::class, 'index']);
    Route::put('/enrollments/{enrollmentId}/activity-completions/{activityId}', [ActivityCompletionController::class, 'update']);
    Route::get('/enrollments/{enrollmentId}/progress', [ActivityCompletionController::class, 'progress']);
    Route::post('/enrollments/{enrollmentId}/activity-completions/{activityId}/reset', [ActivityCompletionController::class, 'reset']);
    Route::post('/enrollments/{enrollmentId}/activity-completions/reset-all', [ActivityCompletionController::class, 'resetAll']);
    
    // E-commerce routes
    // Product Category routes
    Route::get('/product-categories', [ProductCategoryController::class, 'index']);
    Route::post('/product-categories', [ProductCategoryController::class, 'store']);
    Route::get('/product-categories/{id}', [ProductCategoryController::class, 'show']);
    Route::put('/product-categories/{id}', [ProductCategoryController::class, 'update']);
    Route::delete('/product-categories/{id}', [ProductCategoryController::class, 'destroy']);
    Route::get('/product-categories/hierarchy', [ProductCategoryController::class, 'hierarchy']);
    
    // Product routes
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::post('/products/{id}/activate', [ProductController::class, 'activate']);
    Route::post('/products/{id}/deactivate', [ProductController::class, 'deactivate']);
    Route::post('/products/{id}/feature', [ProductController::class, 'feature']);
    Route::post('/products/{id}/unfeature', [ProductController::class, 'unfeature']);
    
    // Order routes
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::get('/my-orders', [OrderController::class, 'myOrders']);
    
    // Plan routes
    Route::get('/plans', [PlanController::class, 'index']);
    Route::get('/plans/{id}', [PlanController::class, 'show']);
    Route::get('/plans/type/{type}', [PlanController::class, 'getByType']);
    Route::post('/plans/compare', [PlanController::class, 'compare']);
    
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'getProfile']);
    Route::put('/profile', [ProfileController::class, 'updateProfile']);
    Route::put('/profile/photo', [ProfileController::class, 'updateProfilePhoto']);

    // Payment Gateway routes
    Route::get('/payment-gateways', [PaymentGatewayController::class, 'index']);
    Route::post('/payment-gateways', [PaymentGatewayController::class, 'store']);
    Route::get('/payment-gateways/{id}', [PaymentGatewayController::class, 'show']);
    Route::put('/payment-gateways/{id}', [PaymentGatewayController::class, 'update']);
    Route::delete('/payment-gateways/{id}', [PaymentGatewayController::class, 'destroy']);
    Route::get('/payment-gateway-types', [PaymentGatewayController::class, 'getTypes']);
    
    // Transaction routes
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    Route::put('/transactions/{id}/status', [TransactionController::class, 'updateStatus']);
    // Marketing routes
    // Referral routes
    Route::get('/marketing/referrals', [ReferralEnvironmentController::class, 'index']);
    Route::post('/marketing/referrals', [ReferralEnvironmentController::class, 'store']);
    Route::get('/marketing/referrals/{id}', [ReferralEnvironmentController::class, 'show']);
    Route::put('/marketing/referrals/{id}', [ReferralEnvironmentController::class, 'update']);
    Route::delete('/marketing/referrals/{id}', [ReferralEnvironmentController::class, 'destroy']);
    Route::get('/marketing/my-referrals', [ReferralEnvironmentController::class, 'myReferrals']);
    Route::post('/marketing/referrals/validate', [ReferralEnvironmentController::class, 'validate']);
    Route::get('/marketing/referrals/stats', [App\Http\Controllers\Api\ReferralEnvironmentController::class, 'getStats']);

    
    // Branding routes
    Route::get('/branding', [BrandingController::class, 'index']);
    Route::post('/branding', [BrandingController::class, 'store']);
    Route::post('/branding/reset', [BrandingController::class, 'reset']);
    Route::post('/branding/preview', [BrandingController::class, 'preview']);
    
    // Finance routes
    Route::get('/finance/overview', [FinanceController::class, 'overview']);
    Route::get('/finance/subscription', [FinanceController::class, 'subscription']);
    Route::get('/finance/orders', [FinanceController::class, 'orders']);
    Route::get('/finance/transactions', [FinanceController::class, 'transactions']);
    Route::get('/finance/revenue-by-product-type', [FinanceController::class, 'revenueByProductType']);
    
    // Analytics routes
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('/analytics/user-engagement', [AnalyticsController::class, 'userEngagement']);
    Route::get('/analytics/course-analytics', [AnalyticsController::class, 'courseAnalytics']);
    Route::get('/analytics/certificate-analytics', [AnalyticsController::class, 'certificateAnalytics']);
    
    // File routes
    Route::post('/files', [FileController::class, 'store']);
    Route::post('/files/batch', [FileController::class, 'batchStore']); // New batch route
    Route::get('/environments/{environmentId}/files', [FileController::class, 'getByEnvironment']);
    
    // User Notification routes
    Route::get('/environments/{environmentId}/notifications', [\App\Http\Controllers\Api\UserNotificationController::class, 'index']);
    Route::get('/environments/{environmentId}/notifications/unread-count', [\App\Http\Controllers\Api\UserNotificationController::class, 'unreadCount']);
    Route::put('/environments/{environmentId}/notifications/{notificationId}/read', [\App\Http\Controllers\Api\UserNotificationController::class, 'markAsRead']);
    Route::put('/environments/{environmentId}/notifications/read-all', [\App\Http\Controllers\Api\UserNotificationController::class, 'markAllAsRead']);
    Route::get('/files/{id}', [FileController::class, 'show']);
    Route::put('/files/{id}', [FileController::class, 'update']);
    Route::delete('/files/{id}', [FileController::class, 'destroy']);
});

// Public routes
Route::get('/branding/public', [BrandingController::class, 'getPublicBranding']);

// Validation routes
Route::post('/subdomains/validate', [ValidationController::class, 'validateSubdomain']);
Route::post('/domains/validate', [ValidationController::class, 'validateDomain']);
Route::post('/emails/validate', [ValidationController::class, 'validateEmail']);

// Certificate public routes
Route::get('/certificates/download/{path}', [CertificateController::class, 'download'])->name('api.certificates.download');
Route::get('/certificates/preview/{path}', [CertificateController::class, 'preview'])->name('api.certificates.preview');

// Certificate routes
Route::post('/certificates/verify', [CertificateController::class, 'verify']);
Route::post('/certificate-content/{certificateContentId}/issue', [CertificateController::class, 'issueCertificate'])->middleware('auth:sanctum');

Route::group(['prefix' => 'storefront'], function () {
    // Public storefront routes that don't require authentication
    Route::get('/{environmentId}/products', [StorefrontController::class, 'getProducts']);
    Route::get('/{environmentId}/products/{productId}', [StorefrontController::class, 'getProduct']);
    

    // Get Course By Slug
    Route::get('/{environmentId}/courses/{slug}', [StorefrontController::class, 'getCourseBySlug']);

    // Get featured products
    Route::get('/{environmentId}/featured-products', [StorefrontController::class, 'getFeaturedProducts']);
    
    // Additional routes for singular product access (for backward compatibility)
    Route::get('/{environmentId}/product/{productId}', [StorefrontController::class, 'getProduct']);
    
    // Get product categories
    Route::get('/{environmentId}/categories', [StorefrontController::class, 'getCategories']);
    Route::get('/{environmentId}/categories/{categoryId}', [StorefrontController::class, 'getCategory']);
    
    // Get payment methods
    Route::get('/{environmentId}/payment-methods', [StorefrontController::class, 'getPaymentMethods']);
    
    // Get payment gateways
    Route::get('/{environmentId}/payment-gateways', [StorefrontController::class, 'getPaymentGateways']);
    
    // Process checkout
    Route::post('/{environmentId}/checkout', [StorefrontController::class, 'checkout']);
    
    // Get Order
    Route::get('/{environmentId}/orders/{orderId}', [StorefrontController::class, 'getOrder']);
    

    
    // Get product reviews
    Route::get('/{environmentId}/products/{productId}/reviews', [StorefrontController::class, 'getProductReviews']);
    
    // Submit product review
    Route::post('/{environmentId}/products/{productId}/reviews', [StorefrontController::class, 'submitProductReview']);
    
    // Get countries, states, cities
    Route::get('/{environmentId}/countries', [StorefrontController::class, 'getCountries']);
    Route::get('/{environmentId}/states/{country}', [StorefrontController::class, 'getStates']);
    Route::get('/{environmentId}/cities/{country}/{state}', [StorefrontController::class, 'getCities']);
    
    // Get tax rate for location
    Route::post('/{environmentId}/tax-rate', [StorefrontController::class, 'getTaxRateForLocation']);
});

// Continue payment for a pending order
Route::post('/storefront/orders/{orderId}/continue-payment', [StorefrontController::class, 'continuePayment'])->middleware('auth:sanctum');

// Payment Routes
Route::group(['prefix' => 'payments'], function () {
    Route::match(['get', 'post'], '/transactions/callback/success/{environment_id}', [TransactionController::class, 'callbackSuccess'])->name('api.transactions.callback.success');
    Route::match(['get', 'post'], '/transactions/callback/failure/{environment_id}', [TransactionController::class, 'callbackFailure'])->name('api.transactions.callback.failure');
    Route::match(['get', 'post'], '/transactions/webhook/{gateway}/{environment_id}', [TransactionController::class, 'webhook'])->name('api.transactions.webhook');
});

// Team Management Routes
Route::middleware('auth:sanctum')->group(function () {
    // Team routes
    Route::get('/teams', [\App\Http\Controllers\Api\TeamController::class, 'index']);
    Route::post('/teams', [\App\Http\Controllers\Api\TeamController::class, 'store']);
    Route::get('/teams/{id}', [\App\Http\Controllers\Api\TeamController::class, 'show']);
    Route::put('/teams/{id}', [\App\Http\Controllers\Api\TeamController::class, 'update']);
    Route::delete('/teams/{id}', [\App\Http\Controllers\Api\TeamController::class, 'destroy']);
    
    // Team members routes
    Route::get('/teams/{id}/members', [\App\Http\Controllers\Api\TeamController::class, 'getTeamMembers']);
    Route::post('/teams/invite', [\App\Http\Controllers\Api\TeamController::class, 'inviteMember']);
    Route::post('/teams/accept-invitation', [\App\Http\Controllers\Api\TeamController::class, 'acceptInvitation']);
    Route::post('/teams/remove-member', [\App\Http\Controllers\Api\TeamController::class, 'removeMember']);
    Route::post('/teams/update-member-role', [\App\Http\Controllers\Api\TeamController::class, 'updateMemberRole']);
});

// Environment User Routes
Route::middleware('auth:sanctum')->group(function () {
    // Account setup route
    Route::put('/environment-users/setup-account', [\App\Http\Controllers\Api\EnvironmentUserController::class, 'setupAccount']);
});
// Include the environment authentication routes
require __DIR__ . '/environment-auth.php';

// Include the learner routes
require __DIR__ . '/learner.php';

// Enrollment Analytics Routes
Route::middleware('auth:sanctum')->prefix('analytics')->group(function () {
    // Track activity analytics
    Route::post('/activity/track', [\App\Http\Controllers\Api\EnrollmentAnalyticsController::class, 'trackActivityAnalytics']);
    
    // Get analytics for specific activity
    Route::get('/enrollments/{enrollmentId}/activities/{activityId}', [\App\Http\Controllers\Api\EnrollmentAnalyticsController::class, 'getActivityAnalytics']);
    
    // Get all analytics for an enrollment
    Route::get('/enrollments/{enrollmentId}', [\App\Http\Controllers\Api\EnrollmentAnalyticsController::class, 'getEnrollmentAnalytics']);
    
    // Get analytics summary for a user
    Route::get('/users/{userId}/summary', [\App\Http\Controllers\Api\EnrollmentAnalyticsController::class, 'getUserAnalyticsSummary']);
    
    // Get course engagement over time
    Route::get('/courses/{courseId}/engagement-over-time', [\App\Http\Controllers\Api\EnrollmentAnalyticsController::class, 'getCourseEngagementOverTime']);
    
    // Get activity engagement leaderboard
    Route::get('/courses/{courseId}/activity-leaderboard', [\App\Http\Controllers\Api\EnrollmentAnalyticsController::class, 'getActivityEngagementLeaderboard']);
});

// Lesson Discussion Routes
Route::prefix('lessons/{lessonId}/discussions')
    ->controller(LessonDiscussionController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::post('{discussionId}/reply', 'reply');
        Route::delete('{discussionId}', 'destroy');
    })->middleware("auth:sanctum");
