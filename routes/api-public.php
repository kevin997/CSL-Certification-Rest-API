<?php

use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\EnvironmentController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
|
| These routes are publicly accessible without authentication.
| They are loaded with the 'api' prefix but without the Sanctum
| EnsureFrontendRequestsAreStateful middleware.
|
*/

// Public branding route - returns branding based on domain
Route::get('/branding/public', [BrandingController::class, 'getPublicBranding']);

// Public environment status - returns environment info based on domain
Route::get('/environment/status', [EnvironmentController::class, 'status']);

// Public subscription status - returns subscription info based on domain
Route::get('/subscription/current', [SubscriptionController::class, 'current']);
