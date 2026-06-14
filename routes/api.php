<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MoneyTransferController;
use App\Http\Controllers\Api\Support\SupportCategoryController;
use App\Http\Controllers\Api\Support\SupportTicketController;
use App\Http\Controllers\Api\Support\SupportTicketMessageController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\Mobile\ProfileController as MobileProfileController;
use App\Http\Controllers\Api\Mobile\TransferController as MobileTransferController;
use App\Http\Controllers\Api\Mobile\AdController as MobileAdController;
use App\Http\Controllers\Api\Mobile\ReferenceController as MobileReferenceController;
use App\Http\Controllers\Api\Admin\AssetController as AdminAssetController;
use App\Http\Controllers\Api\Admin\PaymentMethodController as AdminPaymentMethodController;
use App\Http\Controllers\Api\Admin\AdvertisementController as AdminAdvertisementController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Public Routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/portal/login', [AuthController::class, 'portalLogin']);

// Mobile Auth Routes (public)
Route::prefix('mobile/auth')->group(function () {
    Route::post('/register', [MobileAuthController::class, 'register']);
    Route::post('/request-whatsapp-otp', [MobileAuthController::class, 'requestWhatsAppOtp']);
    Route::post('/verify-whatsapp-otp', [MobileAuthController::class, 'verifyWhatsAppOtp']);
    Route::post('/verify-firebase-phone', [MobileAuthController::class, 'verifyFirebasePhone']);
    Route::post('/logout', [MobileAuthController::class, 'logout'])->middleware('auth:sanctum');
});

// Mobile App Routes (Protected via Sanctum)
Route::prefix('mobile')->middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [MobileProfileController::class, 'show']);
    Route::put('/profile', [MobileProfileController::class, 'update']);

    // Money Transfers (legacy)
    Route::get('/transfers', [MobileTransferController::class, 'index']);
    Route::post('/transfers', [MobileTransferController::class, 'store']);
    Route::get('/transfers/{moneyTransfer}', [MobileTransferController::class, 'show']);
    Route::post('/transfers/{moneyTransfer}/usdt-proof', [MobileTransferController::class, 'uploadUsdtProof']);
    Route::post('/transfers/{moneyTransfer}/confirm-usdt', [MobileTransferController::class, 'confirmUsdt']);
    Route::post('/transfers/{moneyTransfer}/payout-proof', [MobileTransferController::class, 'uploadPayoutProof']);

    // P2P Reference Data
    Route::get('/assets', [MobileReferenceController::class, 'assets']);
    Route::get('/fiat-currencies', [MobileReferenceController::class, 'fiatCurrencies']);
    Route::get('/payment-methods', [MobileReferenceController::class, 'paymentMethods']);

    // P2P Advertisements
    Route::get('/ads', [MobileAdController::class, 'index']);
    Route::get('/my-ads', [MobileAdController::class, 'myAds']);
    Route::post('/ads', [MobileAdController::class, 'store']);
    Route::get('/ads/{advertisement}', [MobileAdController::class, 'show']);
    Route::put('/ads/{advertisement}', [MobileAdController::class, 'update']);
    Route::delete('/ads/{advertisement}', [MobileAdController::class, 'destroy']);
});

// Protected Routes (Session-based via Vue Portal)
Route::middleware('auth')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('portal')->group(function () {

        // Money Transfer Workflow
        Route::get('/transfers/stats', [MoneyTransferController::class, 'stats']);
        Route::get('/transfers', [MoneyTransferController::class, 'index']);
        Route::post('/transfers', [MoneyTransferController::class, 'store']);
        Route::get('/transfers/{moneyTransfer}', [MoneyTransferController::class, 'show']);
        Route::post('/transfers/{moneyTransfer}/usdt-proof', [MoneyTransferController::class, 'uploadUsdtProof']);
        Route::post('/transfers/{moneyTransfer}/confirm-usdt', [MoneyTransferController::class, 'confirmUsdt']);
        Route::post('/transfers/{moneyTransfer}/payout-proof', [MoneyTransferController::class, 'uploadPayoutProof']);
        Route::patch('/transfers/{moneyTransfer}/status', [MoneyTransferController::class, 'updateStatus']);

        // Currencies
        Route::get('/currencies', [CurrencyController::class, 'index']);
        Route::post('/currencies', [CurrencyController::class, 'store']);
        Route::get('/currencies/{currency}', [CurrencyController::class, 'show']);
        Route::put('/currencies/{currency}', [CurrencyController::class, 'update']);
        Route::delete('/currencies/{currency}', [CurrencyController::class, 'destroy']);
        Route::get('/currencies/{currency}/exchange-rates', [CurrencyController::class, 'exchangeRates']);
        Route::post('/exchange-rates', [CurrencyController::class, 'storeExchangeRate']);
        Route::delete('/exchange-rates/{exchangeRate}', [CurrencyController::class, 'destroyExchangeRate']);

        // User / Agent Management
        Route::get('/users/stats', [UserController::class, 'kycStats']);
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::post('/users/{user}/approve-kyc', [UserController::class, 'approveKyc']);
        Route::post('/users/{user}/suspend', [UserController::class, 'suspend']);
        Route::post('/users/{user}/assign-role', [UserController::class, 'assignRole']);

        // P2P Admin — Reference Data
        Route::get('/assets', [AdminAssetController::class, 'index']);
        Route::post('/assets', [AdminAssetController::class, 'store']);
        Route::get('/assets/{asset}', [AdminAssetController::class, 'show']);
        Route::put('/assets/{asset}', [AdminAssetController::class, 'update']);
        Route::delete('/assets/{asset}', [AdminAssetController::class, 'destroy']);

        Route::get('/payment-methods', [AdminPaymentMethodController::class, 'index']);
        Route::post('/payment-methods', [AdminPaymentMethodController::class, 'store']);
        Route::get('/payment-methods/{paymentMethod}', [AdminPaymentMethodController::class, 'show']);
        Route::put('/payment-methods/{paymentMethod}', [AdminPaymentMethodController::class, 'update']);
        Route::delete('/payment-methods/{paymentMethod}', [AdminPaymentMethodController::class, 'destroy']);

        // P2P Admin — Advertisements
        Route::get('/ads', [AdminAdvertisementController::class, 'index']);
        Route::get('/ads/{advertisement}', [AdminAdvertisementController::class, 'show']);
        Route::put('/ads/{advertisement}', [AdminAdvertisementController::class, 'update']);
        Route::delete('/ads/{advertisement}', [AdminAdvertisementController::class, 'destroy']);

        // Support System
        Route::prefix('support')->group(function () {
            Route::get('categories', [SupportCategoryController::class, 'index']);
            Route::get('tickets', [SupportTicketController::class, 'index']);
            Route::post('tickets', [SupportTicketController::class, 'store']);
            Route::get('tickets/{ticket}', [SupportTicketController::class, 'show']);
            Route::patch('tickets/{ticket}/status', [SupportTicketController::class, 'updateStatus']);
            Route::patch('tickets/{ticket}/assign', [SupportTicketController::class, 'assign']);
            Route::post('tickets/{ticket}/messages', [SupportTicketMessageController::class, 'store']);
        });
    });
});
