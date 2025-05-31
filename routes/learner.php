<?php

use App\Http\Controllers\Api\Learner\CourseController;
use App\Http\Controllers\Api\Learner\EnrollmentController;
use App\Http\Controllers\Api\Learner\OrderController;
use App\Http\Controllers\Api\Learner\TemplateController;
use App\Http\Controllers\Api\Learner\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Learner API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for learner functionality.
| These routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

/*
|--------------------------------------------------------------------------
| Learner API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the api.php file.
| Authentication is handled through the main TokenController in api.php
| which assigns the 'role:learner' ability to learner tokens.
|
*/

// Protected learner routes - requiring authentication and appropriate roles
// Users with role:learner or env_role:(learner|company_learner|company_team_member) can access these routes
Route::middleware(['auth:sanctum'])->prefix('learner')->group(function () {
    // Dashboard routes
    Route::get('/dashboard', [DashboardController::class, 'index']);
    
    // Course routes
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{id}', [CourseController::class, 'show']);
    
    // Enrollment routes
    Route::get('/enrollments', [EnrollmentController::class, 'index']);
    Route::get('/enrollments/{id}', [EnrollmentController::class, 'show']);
    
    // Activity completion routes
    Route::post('/activity-completions', [EnrollmentController::class, 'updateActivityCompletion']);
    Route::get('/enrollments/{enrollmentId}/activity-completions', [EnrollmentController::class, 'getActivityCompletions']);
    Route::get('enrollments/{enrollmentId}/activity-completions/{activityId}', [EnrollmentController::class, 'getActivityCompletion']);
    Route::post('enrollments/{enrollmentId}/activity-completions/{activityId}/reset', [EnrollmentController::class, 'resetActivityCompletion']);
    // Order routes
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    
    // Template routes
    Route::get('/templates/{id}', [TemplateController::class, 'show']);
});
