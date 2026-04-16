<?php

use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\CampaignFunderController;
use App\Http\Controllers\Api\AnalyticsWidgetsController;
use App\Http\Controllers\Api\EnvironmentController;
use App\Http\Controllers\Api\LandingPagePopupController;
use App\Http\Controllers\Api\LegalPageController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\ThirdPartyServiceController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\MediaAssetController;
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

// Public legal pages - returns published legal pages (About Us, Privacy Policy, etc.)
Route::get('/legal-pages/public/{pageType}', [LegalPageController::class, 'getPublicPage']);

// Public landing page - returns landing page configuration based on domain
Route::get('/branding/public/landing-page', [BrandingController::class, 'getPublicLandingPage']);

// Public landing page popups - returns active popups based on domain
Route::get('/branding/public/popups', [LandingPagePopupController::class, 'publicPopups']);

// Public analytics visit tracking (guest-safe)
Route::post('/analytics/visits/track', [AnalyticsWidgetsController::class, 'trackVisit']);

// Public KURSA funding endpoints
Route::post('/funders', [CampaignFunderController::class, 'store']);
Route::post('/funders/webhooks/tara', [CampaignFunderController::class, 'handleTaraWebhook']);

Route::post('/webhooks/media/processing', [MediaAssetController::class, 'processingWebhook']);

// Public WhatsApp config - returns WhatsApp button config based on domain
Route::get('/integrations/whatsapp/config', [ThirdPartyServiceController::class, 'getWhatsAppConfig']);

// Admin token login — no CSRF required (cross-domain admin clients like manager.getkursa.space
// cannot use cookie-based Sanctum auth since they are on a different root domain than the API).
// This endpoint authenticates and returns a Sanctum API token for Bearer auth.
Route::post('/admin/token-login', [TokenController::class, 'createToken']);
