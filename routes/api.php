<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Domain Root Contexts
use App\Http\Controllers\Identity\AuthController;
use App\Http\Controllers\Identity\OnboardingController;
use App\Http\Controllers\Identity\FCMController;
use App\Http\Controllers\Identity\UserAddressController;

use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\CategoryController;
use App\Http\Controllers\Inventory\UploadController;

use App\Http\Controllers\Operations\OrderController;
use App\Http\Controllers\Operations\CouponController;
use App\Http\Controllers\Operations\StripeWebhookController;

use App\Http\Controllers\Logistics\RiderController;

use App\Http\Controllers\Analytics\DashboardController;

use App\Http\Controllers\Management\MerchantController;
use App\Http\Controllers\Management\LocationController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\AIController;

/*
|--------------------------------------------------------------------------
| API Routes - Nexus Enterprise Re-Architecture
|--------------------------------------------------------------------------
*/

Route::get('/ping', function() { return response()->json(['status' => 'ok']); });

// 0. Public Gateway (Non-Authenticated)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/products/curated', [ProductController::class, 'curated']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/merchants', [MerchantController::class, 'index']);
Route::get('/merchants/{id}', [MerchantController::class, 'showPublic']);
Route::get('/merchants/{id}/reviews', [MerchantController::class, 'reviews']);
Route::get('/live-offers', [OfferController::class, 'index']);
Route::post('/live-offers/{id}/click', [OfferController::class, 'recordClick']);
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);

// Public location endpoints (for cascading dropdowns in registration / profile)
Route::get('/locations/countries', [LocationController::class, 'countries']);
Route::get('/locations/states',    [LocationController::class, 'states']);
Route::get('/locations/cities',    [LocationController::class, 'cities']);

// 1. Authenticated Secure Context
Route::middleware('auth:sanctum')->group(function () {
    
    // --- Identity & Access Management (IAM) ---
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/addresses', [UserAddressController::class, 'index']);
    Route::post('/addresses', [UserAddressController::class, 'store']);
    Route::post('/save-fcm-token', [FCMController::class, 'saveToken']);
    Route::post('/remove-fcm-token', [FCMController::class, 'removeToken']);

    // --- Inventory & Asset Management ---
    Route::post('/upload', [UploadController::class, 'store']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::post('/products/import', [ProductController::class, 'import']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    // Merchant-level review (scoped by product to derive merchant)
    Route::post('/products/{id}/reviews', [ProductController::class, 'addReview']);
    Route::post('/merchants/{id}/reviews', [MerchantController::class, 'addReview']);
    
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // --- Core Operations (Orders & Payments) ---
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::patch('/orders/{id}/payment-status', [OrderController::class, 'updatePaymentStatus']);
    Route::post('/payments/confirm', [StripeWebhookController::class, 'confirmPayment']);
    
    Route::get('/coupons', [CouponController::class, 'index']);
    Route::post('/coupons', [CouponController::class, 'store']);
    Route::put('/coupons/{id}', [CouponController::class, 'update']);
    Route::delete('/coupons/{id}', [CouponController::class, 'destroy']);
    Route::post('/coupons/validate', [CouponController::class, 'validateCoupon']);
    
    Route::get('/offers', [OfferController::class, 'listAll']);
    Route::post('/offers', [OfferController::class, 'store']);
    Route::put('/offers/{id}', [OfferController::class, 'update']);
    Route::delete('/offers/{id}', [OfferController::class, 'destroy']);


    // --- Performance Analytics ---
    Route::get('/stats', [DashboardController::class, 'stats']);

    // --- Admin Control Plane ---
    Route::middleware('admin')->group(function () {
        Route::post('/admin/generate-offer-image', [AIController::class, 'generateOfferImage']);
        Route::get('/admin/merchants', [MerchantController::class, 'listAll']);
        Route::post('/admin/merchants', [MerchantController::class, 'store']);
        Route::put('/admin/merchants/{id}', [MerchantController::class, 'adminUpdate']);
        Route::patch('/admin/merchants/{id}/toggle', [MerchantController::class, 'toggleStatus']);
        Route::get('/admin/users', [AuthController::class, 'listUsers']);
        Route::post('/admin/onboard-rider', [OnboardingController::class, 'adminOnboardRider']);

        // Location Master CRUD
        Route::get('/admin/locations/countries',         [LocationController::class, 'allCountries']);
        Route::post('/admin/locations/countries',        [LocationController::class, 'storeCountry']);
        Route::put('/admin/locations/countries/{id}',   [LocationController::class, 'updateCountry']);
        Route::delete('/admin/locations/countries/{id}',[LocationController::class, 'destroyCountry']);

        Route::get('/admin/locations/states',           [LocationController::class, 'allStates']);
        Route::post('/admin/locations/states',          [LocationController::class, 'storeState']);
        Route::put('/admin/locations/states/{id}',      [LocationController::class, 'updateState']);
        Route::delete('/admin/locations/states/{id}',   [LocationController::class, 'destroyState']);

        Route::get('/admin/locations/cities',           [LocationController::class, 'allCities']);
        Route::post('/admin/locations/cities',          [LocationController::class, 'storeCity']);
        Route::put('/admin/locations/cities/{id}',      [LocationController::class, 'updateCity']);
        Route::delete('/admin/locations/cities/{id}',   [LocationController::class, 'destroyCity']);
    });

    // --- Merchant Node Control ---
    Route::middleware('merchant')->group(function () {
        Route::get('/merchant/profile', [MerchantController::class, 'show']);
        Route::put('/merchant/profile', [MerchantController::class, 'update']);
        Route::post('/merchant/onboard-rider', [OnboardingController::class, 'merchantOnboardRider']);
    });

    // --- Logistics & Dispatch (Rider Fleet) ---
    Route::prefix('rider')->group(function () {
        Route::get('/orders/available', [RiderController::class, 'availableOrders']);
        Route::post('/orders/{id}/accept', [RiderController::class, 'acceptOrder']);
        Route::post('/location', [RiderController::class, 'updateLocation']);
        Route::post('/orders/{id}/complete', [RiderController::class, 'completeOrder']);
        Route::get('/orders/history', [RiderController::class, 'history']);
        Route::get('/staff', [RiderController::class, 'listStaff']);
    });
});


