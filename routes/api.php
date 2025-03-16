<?php

use App\Http\Controllers\Api\ActivityCompletionController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AssignmentContentController;
use App\Http\Controllers\Api\BlockController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\CertificateContentController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseSectionController;
use App\Http\Controllers\Api\DocumentationContentController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\EventContentController;
use App\Http\Controllers\Api\FeedbackContentController;
use App\Http\Controllers\Api\LessonContentController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\QuizContentController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\TextContentController;
use App\Http\Controllers\Api\VideoContentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// User authentication routes
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

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
    Route::get('/activities/{id}', [ActivityController::class, 'show']);
    Route::put('/activities/{id}', [ActivityController::class, 'update']);
    Route::delete('/activities/{id}', [ActivityController::class, 'destroy']);
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
    
    // Lesson Content routes
    Route::post('/activities/{activityId}/lesson', [LessonContentController::class, 'store']);
    Route::get('/activities/{activityId}/lesson', [LessonContentController::class, 'show']);
    Route::put('/activities/{activityId}/lesson', [LessonContentController::class, 'update']);
    Route::delete('/activities/{activityId}/lesson', [LessonContentController::class, 'destroy']);
    
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
    // Product routes
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::post('/products/{id}/activate', [ProductController::class, 'activate']);
    Route::post('/products/{id}/deactivate', [ProductController::class, 'deactivate']);
    
    // Order routes
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::get('/my-orders', [OrderController::class, 'myOrders']);
    
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
});

// Public routes
Route::get('/branding/public', [BrandingController::class, 'getPublicBranding']);
