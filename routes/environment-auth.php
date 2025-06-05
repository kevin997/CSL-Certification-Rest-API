<?php

use App\Http\Controllers\Api\EnvironmentUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Environment User Authentication Routes
|--------------------------------------------------------------------------
|
| These routes handle authentication for environment-specific users, including
| password reset, account setup, and other environment-specific auth flows.
|
*/

// Public routes for environment user authentication
Route::prefix('environment-auth')->group(function () {
    // Forgot password flow
    Route::post('/forgot-password', [EnvironmentUserController::class, 'forgotPassword']);
    Route::post('/reset-password', [EnvironmentUserController::class, 'resetPassword']);
});
