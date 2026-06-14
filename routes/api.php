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

    // Money Transfers
    Route::get('/transfers', [MobileTransferController::class, 'index']);
    Route::post('/transfers', [MobileTransferController::class, 'store']);
    Route::get('/transfers/{moneyTransfer}', [MobileTransferController::class, 'show']);
    Route::post('/transfers/{moneyTransfer}/usdt-proof', [MobileTransferController::class, 'uploadUsdtProof']);
    Route::post('/transfers/{moneyTransfer}/confirm-usdt', [MobileTransferController::class, 'confirmUsdt']);
    Route::post('/transfers/{moneyTransfer}/payout-proof', [MobileTransferController::class, 'uploadPayoutProof']);
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
