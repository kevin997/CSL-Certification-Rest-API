<?php

use App\Http\Controllers\Api\ActivityCompletionController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AssignmentContentController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\BlockController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\CertificateContentController;
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
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuizContentController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\TextContentController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\VideoContentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


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

// Environment routes
Route::get('/current-environment', [EnvironmentController::class, 'getCurrentEnvironment']);

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
    
    // Lesson Content routes
    Route::post('/activities/{activityId}/lesson', [LessonContentController::class, 'store']);
    Route::get('/activities/{activityId}/lesson', [LessonContentController::class, 'show']);
    Route::put('/activities/{activityId}/lesson', [LessonContentController::class, 'update']);
    Route::delete('/activities/{activityId}/lesson', [LessonContentController::class, 'destroy']);
    
    // Lesson Question Response routes
    Route::post('/lessons/{lessonId}/submit-responses', [LessonQuestionResponseController::class, 'submitResponses']);
    Route::get('/lessons/{lessonId}/responses', [LessonQuestionResponseController::class, 'getResponses']);
    
    // Assignment Content routes
    Route::post('/activities/{activityId}/assignment', [AssignmentContentController::class, 'store']);
    Route::get('/activities/{activityId}/assignment', [AssignmentContentController::class, 'show']);
    Route::put('/activities/{activityId}/assignment', [AssignmentContentController::class, 'update']);
    Route::delete('/activities/{activityId}/assignment', [AssignmentContentController::class, 'destroy']);
    
    // Documentation Content routes
    Route::post('/activities/{activityId}/documentation', [DocumentationContentController::class, 'store']);
    Route::get('/activities/{activityId}/documentation', [DocumentationContentController::class, 'show']);
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
    Route::post('/transactions/{id}/process', [TransactionController::class, 'process']);
    Route::post('/transactions/callback/success', [TransactionController::class, 'callbackSuccess'])->name('api.transactions.callback.success');
    Route::post('/transactions/callback/failure', [TransactionController::class, 'callbackFailure'])->name('api.transactions.callback.failure');
    Route::post('/transactions/webhook/{gateway}', [TransactionController::class, 'webhook'])->name('api.transactions.webhook');
    
    // Marketing routes
    // Referral routes
    Route::get('/referrals', [ReferralController::class, 'index']);
    Route::post('/referrals', [ReferralController::class, 'store']);
    Route::get('/referrals/{id}', [ReferralController::class, 'show']);
    Route::put('/referrals/{id}', [ReferralController::class, 'update']);
    Route::delete('/referrals/{id}', [ReferralController::class, 'destroy']);
    Route::get('/my-referrals', [ReferralController::class, 'myReferrals']);
    Route::post('/referrals/validate', [ReferralController::class, 'validate']);
    
    // Branding routes
    Route::get('/branding', [BrandingController::class, 'index']);
    Route::post('/branding', [BrandingController::class, 'store']);
    Route::post('/branding/reset', [BrandingController::class, 'reset']);
    Route::post('/branding/preview', [BrandingController::class, 'preview']);
    
    // File routes
    Route::post('/files', [FileController::class, 'store']);
    Route::get('/environments/{environmentId}/files', [FileController::class, 'getByEnvironment']);
    Route::get('/files/{id}', [FileController::class, 'show']);
    Route::put('/files/{id}', [FileController::class, 'update']);
    Route::delete('/files/{id}', [FileController::class, 'destroy']);
});

// Public routes
Route::get('/branding/public', [BrandingController::class, 'getPublicBranding']);

// Storefront routes
use App\Http\Controllers\Api\StorefrontController;

Route::prefix('storefront')->group(function () {
    // Public storefront routes that don't require authentication
    Route::get('/{environment_id}/products/featured', [StorefrontController::class, 'getFeaturedProducts']);
    Route::get('/{environment_id}/products', [StorefrontController::class, 'getAllProducts']);
    Route::get('/{environment_id}/products/{slug}', [StorefrontController::class, 'getProductBySlug']);
    Route::get('/{environment_id}/product/{id}', [StorefrontController::class, 'getProductById']);
    Route::get('/{environment_id}/categories', [StorefrontController::class, 'getCategories']);
    Route::get('/{environment_id}/payment-methods', [StorefrontController::class, 'getPaymentMethods']);
    Route::get('/{environment_id}/payment-gateways', [StorefrontController::class, 'getPaymentGateways']);
    Route::post('/{environment_id}/checkout', [StorefrontController::class, 'checkout']);
    
    // Product review routes
    Route::get('/{environment_id}/products/{product_id}/reviews', [StorefrontController::class, 'getProductReviews']);
    Route::post('/{environment_id}/products/{product_id}/reviews', [StorefrontController::class, 'submitProductReview']);
    
    // Course routes
    Route::get('/{environment_id}/courses', [StorefrontController::class, 'getCourses']);
    Route::get('/{environment_id}/courses/{slug}', [StorefrontController::class, 'getCourseBySlug']);
    Route::get('/{environment_id}/course/{id}', [StorefrontController::class, 'getCourseById']);
});
