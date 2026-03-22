<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\StripeWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/ping', function() { return response()->json(['status' => 'ok']); });

// Public Authentication Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public Product Routes
Route::get('/products', [\App\Http\Controllers\ProductController::class, 'index']);
Route::get('/products/{id}', [\App\Http\Controllers\ProductController::class, 'show']);
Route::get('/categories', [\App\Http\Controllers\CategoryController::class, 'index']);
Route::post('/webhooks/stripe', [\App\Http\Controllers\StripeWebhookController::class, 'handle']);

// Protected Routes (Require Bearer Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [\App\Http\Controllers\AuthController::class, 'profile']);
    Route::put('/profile', [\App\Http\Controllers\AuthController::class, 'updateProfile']);
    Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout']);
    
    Route::get('/addresses', [\App\Http\Controllers\API\UserAddressController::class, 'index']);
    Route::post('/addresses', [\App\Http\Controllers\API\UserAddressController::class, 'store']);

    // --- Admin Only Routes ---
    Route::middleware('admin')->group(function () {
        // Prefixed with /admin
        Route::prefix('admin')->group(function () {
            Route::get('/stats', [DashboardController::class, 'stats']);
            Route::get('/coupons', [CouponController::class, 'index']);
            Route::post('/coupons', [CouponController::class, 'store']);
            Route::put('/coupons/{id}', [CouponController::class, 'update']);
            Route::delete('/coupons/{id}', [CouponController::class, 'destroy']);
        });

        // Non-prefixed (sharing base paths with public routes or direct paths)
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
        
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
        
        Route::post('/upload', [UploadController::class, 'store']);
        Route::get('/users', [AuthController::class, 'listUsers']);
        Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
        Route::patch('/orders/{id}/payment-status', [OrderController::class, 'updatePaymentStatus']);
        Route::post('/orders/{id}/initiate-payment', [OrderController::class, 'initiatePayment']);
    });
    // --- End Admin Routes ---
    
    // Authenticated User Routes
    Route::post('/products/{id}/reviews', [\App\Http\Controllers\ProductController::class, 'addReview']);
    
    // Cart Routes
    // Route::post('/cart/add', [CartController::class, 'add']);
    // Route::get('/cart', [CartController::class, 'index']);
    
    // Order Routes with Rate Limiting (10 requests per minute)
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/orders', [\App\Http\Controllers\OrderController::class, 'store']);
    });
    Route::get('/orders', [\App\Http\Controllers\OrderController::class, 'index']);
    Route::get('/orders/{id}', [\App\Http\Controllers\OrderController::class, 'show']);

    // Stripe Payment Confirmation (local dev alternative to webhook)
    Route::post('/payments/confirm', [\App\Http\Controllers\StripeWebhookController::class, 'confirmPayment']);
    
    // Coupon Routes
    Route::post('/coupons/validate', [\App\Http\Controllers\CouponController::class, 'validateCoupon']);

    // FCM Notification Routes
    Route::post('/save-fcm-token', [\App\Http\Controllers\FCMController::class, 'saveToken']);
    Route::post('/remove-fcm-token', [\App\Http\Controllers\FCMController::class, 'removeToken']);
    Route::post('/send-notification', [\App\Http\Controllers\FCMController::class, 'sendManualNotification']);
});
