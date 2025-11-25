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
use App\Http\Controllers\Api\BillingPaymentGatewayController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProductAssetController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuizContentController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\SubscriptionController;
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
use App\Http\Controllers\Api\ChatArchivalController;
use App\Http\Controllers\Api\ChatSearchController;
use App\Http\Controllers\Api\Onboarding\OnboardingController;
use App\Http\Controllers\Api\ReferralEnvironmentController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\Onboarding\StandaloneOnboardingController;
use App\Http\Controllers\Api\Onboarding\SupportedOnboardingController;
use App\Http\Controllers\Api\Onboarding\DemoOnboardingController;
use App\Http\Controllers\Api\LessonDiscussionController;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Api\UserNotificationController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ChatAnalyticsController;
use App\Http\Controllers\Api\DigitalProductController;
use App\Http\Controllers\Api\ThirdPartyServiceController;

Route::middleware('auth:sanctum')->post('/broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
});

// Health check endpoint for monitoring and deployment verification
Route::get('/health', function () {
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

    // Validation routes
    Route::post('/validate-email', [OnboardingController::class, 'validateEmail']);
    Route::post('/validate-domain', [OnboardingController::class, 'validateDomain']);
    Route::post('/validate', [OnboardingController::class, 'validate']);
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

Route::get('/debug/environments', function () {
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
// Password reset routes with rate limiting
Route::middleware(['throttle:reset'])->group(function () {
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
    Route::post('/reset-password', [ResetPasswordController::class, 'reset']);
});

// Token management routes
// Authentication endpoints with rate limiting
Route::middleware(['throttle:login'])->group(function () {
    Route::post('/tokens', [TokenController::class, 'createToken']);
});

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

// Public Tax Rate route (for onboarding)
Route::post('/public/tax-rate', [OnboardingController::class, 'getTaxRate']);
// Environment management routes
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('environments', EnvironmentController::class);
    Route::get('environments/{id}/users', [EnvironmentController::class, 'getUsers']);
    Route::post('environments/{id}/users', [EnvironmentController::class, 'addUser']);
    Route::delete('environments/{id}/users/{userId}', [EnvironmentController::class, 'removeUser']);
    Route::put('environments/{id}/demo-status', [EnvironmentController::class, 'updateDemoStatus']);

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

    // Customer management routes
    Route::get('/sales/admin/customers', [App\Http\Controllers\Api\Sales\CustomerController::class, 'index']);
    Route::get('/sales/admin/customers/stats', [App\Http\Controllers\Api\Sales\CustomerController::class, 'getStats']);
    Route::get('/sales/admin/customers/{id}', [App\Http\Controllers\Api\Sales\CustomerController::class, 'show']);
    Route::put('/sales/admin/customers/{id}', [App\Http\Controllers\Api\Sales\CustomerController::class, 'update']);
    Route::delete('/sales/admin/customers/{id}', [App\Http\Controllers\Api\Sales\CustomerController::class, 'destroy']);
});

// Sales Agent Management Routes
Route::middleware('auth:sanctum')->group(function () {
    // Sales agent listing and creation
    Route::get('/sales/admin/agents', [App\Http\Controllers\Api\Sales\SalesAgentController::class, 'index']);
    Route::post('/sales/admin/agents', [App\Http\Controllers\Api\Sales\SalesAgentController::class, 'store']);
    Route::get('/sales/admin/agents/{id}', [App\Http\Controllers\Api\Sales\SalesAgentController::class, 'show']);
    Route::put('/sales/admin/agents/{id}', [App\Http\Controllers\Api\Sales\SalesAgentController::class, 'update']);
    Route::delete('/sales/admin/agents/{id}', [App\Http\Controllers\Api\Sales\SalesAgentController::class, 'destroy']);
    Route::get('/sales/admin/agents/{id}/performance', [App\Http\Controllers\Api\Sales\SalesAgentController::class, 'getPerformance']);
    Route::get('/sales/admin/agents/{id}/referrals', [App\Http\Controllers\Api\Sales\SalesAgentController::class, 'getReferrals']);
});

// Template Management Routes
Route::middleware('auth:sanctum')->group(function () {

    // Invoice routes
    Route::get('/invoices', [App\Http\Controllers\Api\InvoiceController::class, 'index']);
    Route::get('/invoices/{id}', [App\Http\Controllers\Api\InvoiceController::class, 'show']);
    Route::post('/invoices', [App\Http\Controllers\Api\InvoiceController::class, 'generateMonthlyInvoices']);
    Route::put('/invoices/{id}', [App\Http\Controllers\Api\InvoiceController::class, 'markAsPaid']);
    Route::get('/invoices/{id}/download', [App\Http\Controllers\Api\InvoiceController::class, 'downloadPDF']);

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
    Route::post('/templates/{templateId}/blocks/batch', [\App\Http\Controllers\Api\BlockController::class, 'batchStore']);

    // Activity routes
    Route::get('/blocks/{blockId}/activities', [ActivityController::class, 'index']);
    Route::post('/blocks/{blockId}/activities', [ActivityController::class, 'store']);
    Route::get('/blocks/activities/{id}', [ActivityController::class, 'show']);
    Route::put('/blocks/activities/{id}', [ActivityController::class, 'update']);
    Route::delete('/blocks/activities/{id}', [ActivityController::class, 'destroy']);
    Route::delete('/blocks/{blockId}/activities/batch', [ActivityController::class, 'batchDestroy']);
    Route::post('/blocks/activities/{id}/duplicate', [ActivityController::class, 'duplicate']);
    Route::post('/blocks/{blockId}/activities/reorder', [ActivityController::class, 'reorder']);
    Route::get('/blocks/activities/{id}/has-content', [ActivityController::class, 'hasContent']);
    Route::patch('/blocks/activities/{id}/change-type', [ActivityController::class, 'changeType']);

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
    Route::get('/quiz/{quizContentId}/user/submissions', [\App\Http\Controllers\QuizSubmissionController::class, 'getUserSubmissions']);
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
    Route::get('/activities/{activityId}/certificate/issued', [CertificateController::class, 'getIssuedCertificateForActivity']);
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
    Route::get('/feedback/user/submissions', [FeedbackSubmissionController::class, 'getUserSubmissions']);
    Route::get('/feedback/user/{userId}/submissions', [FeedbackSubmissionController::class, 'getUserSubmissionsById']);
    Route::get('/feedback/{feedbackContentId}/submissions', [FeedbackSubmissionController::class, 'index']);
    Route::post('/feedback/{feedbackContentId}/submissions', [FeedbackSubmissionController::class, 'store']);
    Route::get('/feedback/submissions/{submissionId}', [FeedbackSubmissionController::class, 'show']);
    Route::put('/feedback/submissions/{submissionId}', [FeedbackSubmissionController::class, 'update']);
    Route::post('/feedback/submissions/{submissionId}/submit', [FeedbackSubmissionController::class, 'submit']);
    Route::delete('/feedback/submissions/{submissionId}', [FeedbackSubmissionController::class, 'destroy']);

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

    // Product Asset routes (for instructors to add external links to products)
    Route::get('/products/{product}/assets', [ProductAssetController::class, 'index']);
    Route::post('/products/{product}/assets', [ProductAssetController::class, 'store']);
    Route::put('/products/{product}/assets/{asset}', [ProductAssetController::class, 'update']);
    Route::delete('/products/{product}/assets/{asset}', [ProductAssetController::class, 'destroy']);

    // Order routes
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::get('/my-orders', [OrderController::class, 'myOrders']);

    // Digital Product routes (for customers to access purchased digital products)
    Route::get('/digital-products', [DigitalProductController::class, 'index']);
    Route::get('/digital-products/access/{token}', [DigitalProductController::class, 'access']);

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

    // Billing Payment Gateway routes (always uses default environment ID 1)
    Route::get('/billing/payment-gateways', [BillingPaymentGatewayController::class, 'index']);
    Route::get('/billing/payment-gateways/{id}', [BillingPaymentGatewayController::class, 'show']);
    Route::get('/billing/payment-gateway-types', [BillingPaymentGatewayController::class, 'getAvailableTypes']);
    Route::get('/billing/payment-gateway-default', [BillingPaymentGatewayController::class, 'getDefault']);

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
    Route::get('/branding/{id}', [BrandingController::class, 'show']);
    Route::put('/branding/{id}', [BrandingController::class, 'update']);
    Route::post('/branding/preview', [BrandingController::class, 'preview']);

    // Landing Page Routes
    Route::get('/branding/{id}/landing-page', [BrandingController::class, 'getLandingPageConfig']);
    Route::put('/branding/{id}/landing-page', [BrandingController::class, 'updateLandingPageConfig']);
    Route::post('/branding/{id}/landing-page/toggle', [BrandingController::class, 'toggleLandingPage']);

    // Finance routes
    Route::get('/finance/overview', [FinanceController::class, 'overview']);
    Route::get('/finance/subscription', [FinanceController::class, 'subscription']);
    Route::get('/finance/orders', [FinanceController::class, 'orders']);
    Route::get('/finance/transactions', [FinanceController::class, 'transactions']);
    Route::get('/finance/revenue-by-product-type', [FinanceController::class, 'revenueByProductType']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/subscription/{id}', [SubscriptionController::class, 'show']);
        Route::get('/subscription/{id}/payments', [SubscriptionController::class, 'payments']);
        Route::post('/subscription/{id}/retry-payment', [SubscriptionController::class, 'retryPayment']);
        Route::post('/subscription/{id}/cancel', [SubscriptionController::class, 'cancel']);
        Route::put('/subscription/{id}', [SubscriptionController::class, 'update']);
        Route::post('/subscription/upgrade', [SubscriptionController::class, 'upgrade']);

        // Advanced subscription management endpoints
        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
        Route::post('/subscriptions', [SubscriptionController::class, 'store']);
        Route::get('/subscriptions/{id}', [SubscriptionController::class, 'adminShow']);
        Route::put('/subscriptions/{id}', [SubscriptionController::class, 'adminUpdate']);
        Route::delete('/subscriptions/{id}', [SubscriptionController::class, 'destroy']);
        Route::post('/subscriptions/{id}/suspend', [SubscriptionController::class, 'suspend']);
        Route::post('/subscriptions/{id}/reactivate', [SubscriptionController::class, 'reactivate']);

        // Customer management endpoints for admin
        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::get('/customers/{id}', [CustomerController::class, 'show']);
        Route::put('/customers/{id}', [CustomerController::class, 'update']);
        Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);

        // Admin subscription management endpoints
        Route::post('/subscription/{id}/calculate-proration', [SubscriptionController::class, 'calculateProration']);
        Route::post('/subscription/{id}/change-plan', [SubscriptionController::class, 'changePlan']);
        Route::get('/subscription/{id}/failed-payment', [SubscriptionController::class, 'getFailedPayment']);
        Route::post('/subscription/{id}/renew', [SubscriptionController::class, 'renew']);
        Route::post('/subscription/{id}/cancel-subscription', [SubscriptionController::class, 'cancelSubscription']);

        // Admin subscription management endpoints
        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
        Route::post('/subscriptions', [SubscriptionController::class, 'store']);
        Route::get('/subscriptions/{id}', [SubscriptionController::class, 'adminShow']);
        Route::put('/subscriptions/{id}', [SubscriptionController::class, 'adminUpdate']);
        Route::delete('/subscriptions/{id}', [SubscriptionController::class, 'destroy']);
        Route::post('/subscriptions/{id}/suspend', [SubscriptionController::class, 'suspend']);
        Route::post('/subscriptions/{id}/reactivate', [SubscriptionController::class, 'reactivate']);
    });

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
    Route::get('/environments/{environmentId}/notifications', [UserNotificationController::class, 'index']);
    Route::get('/environments/{environmentId}/notifications/unread-count', [UserNotificationController::class, 'unreadCount']);
    Route::put('/environments/{environmentId}/notifications/{notificationId}/read', [UserNotificationController::class, 'markAsRead']);
    Route::put('/environments/{environmentId}/notifications/read-all', [UserNotificationController::class, 'markAllAsRead']);
    // Test broadcast route (auth required): expects environment_id, optional user_id, type, title, message, data
    Route::post('/notifications/test-broadcast', [UserNotificationController::class, 'testBroadcast']);
    Route::get('/files/{id}', [FileController::class, 'show']);
    Route::put('/files/{id}', [FileController::class, 'update']);
    Route::delete('/files/{id}', [FileController::class, 'destroy']);
});

// Public routes (explicitly exclude auth middleware)
Route::withoutMiddleware(['auth:sanctum'])->group(function () {
    Route::get('/branding/public', [BrandingController::class, 'getPublicBranding']);
    Route::get('/environment/status', [EnvironmentController::class, 'status']);
    Route::get('/subscription/current', [SubscriptionController::class, 'current']);
});

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
Route::get('/user/certificates', [CertificateController::class, 'getUserCertificates'])->middleware('auth:sanctum');

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

    // Calculate product price with commission (for product creation)
    Route::post('/{environmentId}/calculate-product-price', [StorefrontController::class, 'calculateProductPriceWithCommission']);

    // Free course enrollment (requires authentication)
    Route::post('/{environmentId}/enroll-free', [StorefrontController::class, 'enrollFree'])->middleware('auth:sanctum');
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

// Chat System Routes
Route::middleware('auth:sanctum')->prefix('chat')->group(function () {
    // Discussion routes
    Route::post('discussions', [\App\Http\Controllers\Api\Chat\DiscussionController::class, 'store']);
    Route::get('discussions/{discussion}', [\App\Http\Controllers\Api\Chat\DiscussionController::class, 'show']);
    Route::post('discussions/{discussion}/join', [\App\Http\Controllers\Api\Chat\DiscussionController::class, 'join'])
        ->middleware('throttle:10,1'); // 10 join/leave actions per minute
    Route::post('discussions/{discussion}/leave', [\App\Http\Controllers\Api\Chat\DiscussionController::class, 'leave'])
        ->middleware('throttle:10,1'); // 10 join/leave actions per minute
    Route::get('discussions/{discussion}/participants', [\App\Http\Controllers\Api\Chat\DiscussionController::class, 'participants']);

    // Course-specific discussion routes
    Route::get('courses/{courseId}/discussions', [\App\Http\Controllers\Api\Chat\DiscussionController::class, 'courseDiscussions']);
    Route::post('courses/{courseId}/discussions/get-or-create', [\App\Http\Controllers\Api\Chat\DiscussionController::class, 'getOrCreate']);

    // Message routes with rate limiting
    Route::post('messages', [\App\Http\Controllers\Api\Chat\MessageController::class, 'store'])
        ->middleware('chat.rate.messages'); // 60 messages per minute
    Route::post('discussions/{discussion}/mark-read', [\App\Http\Controllers\Api\Chat\MessageController::class, 'markAsRead']);
});

// Chat Analytics Routes
Route::middleware('auth:sanctum')->prefix('chat/analytics')->group(function () {
    // Course engagement reports
    Route::get('course/{courseId}/engagement', [\App\Http\Controllers\Api\ChatAnalyticsController::class, 'getCourseEngagementReport']);

    // Participation metrics
    Route::get('course/{courseId}/participation', [\App\Http\Controllers\Api\ChatAnalyticsController::class, 'getParticipationMetrics']);

    // Certificate eligibility
    Route::get('course/{courseId}/certificate-eligibility', [\App\Http\Controllers\Api\ChatAnalyticsController::class, 'getCertificateEligibility']);

    // Generate participation certificate
    Route::post('course/{courseId}/users/{userId}/certificate', [\App\Http\Controllers\Api\ChatAnalyticsController::class, 'generateParticipationCertificate']);

    // Process participation data (webhook endpoint)
    Route::post('participation/process', [\App\Http\Controllers\Api\ChatAnalyticsController::class, 'processParticipationData']);

    // Dashboard summary
    Route::get('course/{courseId}/dashboard', [\App\Http\Controllers\Api\ChatAnalyticsController::class, 'getDashboardSummary']);
});

// Chat Archival Routes
Route::prefix('chat/archival')->group(function () {
    // Get archival status for a course
    Route::get('courses/{courseId}/status', [ChatArchivalController::class, 'getArchivalStatus']);

    // Trigger manual archival for a course
    Route::post('courses/{courseId}/archive', [ChatArchivalController::class, 'triggerArchival']);

    // Restore archived messages for a date range
    Route::post('courses/{courseId}/restore', [ChatArchivalController::class, 'restoreMessages']);

    // Get archival statistics
    Route::get('statistics', [ChatArchivalController::class, 'getArchivalStatistics']);

    // Cancel a running archival job
    Route::delete('jobs/{jobId}/cancel', [ChatArchivalController::class, 'cancelArchivalJob']);
});

// Chat Search Routes
Route::prefix('chat/search')->group(function () {
    // Search chat messages
    Route::get('messages', [ChatSearchController::class, 'searchMessages']);

    // Get search suggestions
    Route::get('suggestions', [ChatSearchController::class, 'getSearchSuggestions']);

    // Build search index for a course (admin only)
    Route::post('courses/{courseId}/build-index', [ChatSearchController::class, 'buildSearchIndex'])
        ->middleware('can:admin-access,course');

    // Rebuild search index for a course (admin only)
    Route::post('courses/{courseId}/rebuild-index', [ChatSearchController::class, 'rebuildSearchIndex'])
        ->middleware('can:admin-access,course');

    // Get search analytics
    Route::get('analytics', [ChatSearchController::class, 'getSearchAnalytics']);

    // Get search index status
    Route::get('index/status', [ChatSearchController::class, 'getIndexStatus']);
});

// Chat Instructor Routes
Route::prefix('chat/instructor')->group(function () {
    // Get all discussions for instructor (across all their courses)
    Route::get('discussions', [\App\Http\Controllers\Api\Chat\DiscussionController::class, 'instructorDiscussions']);

    // Get discussions for a specific course
    Route::get('courses/{courseId}/discussions', [\App\Http\Controllers\Api\Chat\DiscussionController::class, 'courseDiscussions']);

    // Get discussion analytics for instructor
    Route::get('discussions/analytics', [\App\Http\Controllers\Api\Chat\DiscussionController::class, 'instructorAnalytics']);
});

// Third Party Service Management Routes
Route::middleware('auth:sanctum')->prefix('third-party-services')->group(function () {
    // CRUD operations
    Route::get('/', [ThirdPartyServiceController::class, 'index']);
    Route::post('/', [ThirdPartyServiceController::class, 'store']);
    Route::get('/{id}', [ThirdPartyServiceController::class, 'show']);
    Route::put('/{id}', [ThirdPartyServiceController::class, 'update']);
    Route::delete('/{id}', [ThirdPartyServiceController::class, 'destroy']);

    // Service-specific actions
    Route::post('/{id}/refresh-token', [ThirdPartyServiceController::class, 'refreshToken']);
    Route::post('/{id}/test-connection', [ThirdPartyServiceController::class, 'testConnection']);

    // Utility endpoints
    Route::get('/types/available', [ThirdPartyServiceController::class, 'getServiceTypes']);
});

// Admin API Endpoints for Payment Gateway Centralization
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    // Commission Management
    Route::prefix('commissions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Admin\CommissionController::class, 'index']);
        Route::get('/stats', [\App\Http\Controllers\Api\Admin\CommissionController::class, 'stats']);
        Route::get('/environment/{environmentId}', [\App\Http\Controllers\Api\Admin\CommissionController::class, 'byEnvironment']);
        Route::get('/{id}', [\App\Http\Controllers\Api\Admin\CommissionController::class, 'show']);
        Route::post('/{id}/approve', [\App\Http\Controllers\Api\Admin\CommissionController::class, 'approve']);
        Route::post('/bulk-approve', [\App\Http\Controllers\Api\Admin\CommissionController::class, 'bulkApprove']);
    });

    // Withdrawal Request Management
    Route::prefix('withdrawal-requests')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Admin\WithdrawalRequestController::class, 'index']);
        Route::get('/stats', [\App\Http\Controllers\Api\Admin\WithdrawalRequestController::class, 'stats']);
        Route::get('/{id}', [\App\Http\Controllers\Api\Admin\WithdrawalRequestController::class, 'show']);
        Route::post('/{id}/approve', [\App\Http\Controllers\Api\Admin\WithdrawalRequestController::class, 'approve']);
        Route::post('/{id}/reject', [\App\Http\Controllers\Api\Admin\WithdrawalRequestController::class, 'reject']);
        Route::post('/{id}/process', [\App\Http\Controllers\Api\Admin\WithdrawalRequestController::class, 'process']);
    });

    // Centralized Transaction Management
    Route::prefix('centralized-transactions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Admin\CentralizedTransactionController::class, 'index']);
        Route::get('/stats', [\App\Http\Controllers\Api\Admin\CentralizedTransactionController::class, 'stats']);
        Route::get('/export', [\App\Http\Controllers\Api\Admin\CentralizedTransactionController::class, 'export']);
        Route::get('/environment/{environmentId}', [\App\Http\Controllers\Api\Admin\CentralizedTransactionController::class, 'byEnvironment']);
        Route::get('/{id}', [\App\Http\Controllers\Api\Admin\CentralizedTransactionController::class, 'show']);
    });

    // Environment Payment Configuration
    Route::prefix('environment-payment-configs')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Admin\EnvironmentPaymentConfigController::class, 'index']);
        Route::get('/{environmentId}', [\App\Http\Controllers\Api\Admin\EnvironmentPaymentConfigController::class, 'show']);
        Route::put('/{environmentId}', [\App\Http\Controllers\Api\Admin\EnvironmentPaymentConfigController::class, 'update']);
        Route::post('/{environmentId}/toggle', [\App\Http\Controllers\Api\Admin\EnvironmentPaymentConfigController::class, 'toggle']);
    });
});

// Instructor API Endpoints for Earnings and Withdrawals
Route::middleware(['auth:sanctum'])->prefix('instructor')->group(function () {
    // Earnings Management
    Route::prefix('earnings')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Instructor\EarningsController::class, 'index']);
        Route::get('/stats', [\App\Http\Controllers\Api\Instructor\EarningsController::class, 'stats']);
        Route::get('/balance', [\App\Http\Controllers\Api\Instructor\EarningsController::class, 'balance']);
    });

    // Withdrawal Management
    Route::prefix('withdrawals')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Instructor\WithdrawalController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\Instructor\WithdrawalController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\Api\Instructor\WithdrawalController::class, 'show']);
    });

    // Payment Configuration
    Route::prefix('payment-config')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Instructor\PaymentConfigController::class, 'show']);
        Route::put('/', [\App\Http\Controllers\Api\Instructor\PaymentConfigController::class, 'update']);

        // Centralized payment gateway opt-in
        Route::get('/centralized', [\App\Http\Controllers\Api\Instructor\PaymentConfigController::class, 'getCentralizedConfig']);
        Route::post('/centralized/toggle', [\App\Http\Controllers\Api\Instructor\PaymentConfigController::class, 'toggleCentralized']);
    });
});
