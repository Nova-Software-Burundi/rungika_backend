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
use App\Http\Controllers\Api\Mobile\TradeController as MobileTradeController;
use App\Http\Controllers\Api\Mobile\RatingController as MobileRatingController;
use App\Http\Controllers\Api\Mobile\DeviceTokenController as MobileDeviceTokenController;
use App\Http\Controllers\Api\Mobile\NotificationController as MobileNotificationController;
use App\Http\Controllers\Api\Mobile\AgentController as MobileAgentController;
use App\Http\Controllers\Api\Mobile\RemittanceController as MobileRemittanceController;
use App\Http\Controllers\Api\Mobile\AgentOrderController as MobileAgentOrderController;
use App\Http\Controllers\Api\Mobile\RemittanceTicketController as MobileRemittanceTicketController;
use App\Http\Controllers\Api\Admin\AssetController as AdminAssetController;
use App\Http\Controllers\Api\Admin\PaymentMethodController as AdminPaymentMethodController;
use App\Http\Controllers\Api\Admin\AdvertisementController as AdminAdvertisementController;
use App\Http\Controllers\Api\Admin\TradeController as AdminTradeController;
use App\Http\Controllers\Api\Admin\DisputeMessageController as AdminDisputeMessageController;
use App\Http\Controllers\Api\Admin\PlatformFeeController as AdminPlatformFeeController;
use App\Http\Controllers\Api\Admin\ReferencePriceController as AdminReferencePriceController;
use App\Http\Controllers\Api\Admin\RevenueController as AdminRevenueController;
use App\Http\Controllers\Api\Admin\RatingController as AdminRatingController;
use App\Http\Controllers\Api\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Api\Admin\ExportController as AdminExportController;
use App\Http\Controllers\Api\Admin\CountryController as AdminCountryController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Mobile\ReportController as MobileReportController;
use App\Http\Controllers\Api\ApiKeyController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Public Routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/portal/login', [AuthController::class, 'portalLogin']);

// Public Reference Data (no auth needed)
Route::get('/countries', function () {
    return \App\Models\Country::where('is_active', true)->orderBy('name')->get();
});

// Mobile Auth Routes (public)
Route::prefix('mobile/auth')->group(function () {
    Route::post('/register', [MobileAuthController::class, 'register']);
    Route::post('/request-whatsapp-otp', [MobileAuthController::class, 'requestWhatsAppOtp']);
    Route::post('/verify-whatsapp-otp', [MobileAuthController::class, 'verifyWhatsAppOtp']);
    Route::post('/verify-firebase-phone', [MobileAuthController::class, 'verifyFirebasePhone']);
    Route::post('/login', [AuthController::class, 'mobileLogin']);
    Route::post('/logout', [MobileAuthController::class, 'logout'])->middleware('auth:sanctum');
});

// Mobile App Routes (Protected via Sanctum + approval gate)
Route::prefix('mobile')->middleware(['auth:sanctum', 'approved'])->group(function () {
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
    Route::get('/reference-prices', [MobileReferenceController::class, 'referencePrices']);

    // P2P Advertisements
    Route::get('/ads', [MobileAdController::class, 'index']);
    Route::get('/my-ads', [MobileAdController::class, 'myAds']);
    Route::post('/ads', [MobileAdController::class, 'store']);
    Route::get('/ads/{advertisement}', [MobileAdController::class, 'show']);
    Route::put('/ads/{advertisement}', [MobileAdController::class, 'update']);
    Route::delete('/ads/{advertisement}', [MobileAdController::class, 'destroy']);

    // P2P Trades
    Route::get('/trades', [MobileTradeController::class, 'index']);
    Route::post('/trades', [MobileTradeController::class, 'store']);
    Route::get('/trades/{trade}', [MobileTradeController::class, 'show']);
    Route::post('/trades/{trade}/confirm', [MobileTradeController::class, 'confirm']);
    Route::post('/trades/{trade}/mark-paid', [MobileTradeController::class, 'markPaid']);
    Route::post('/trades/{trade}/release', [MobileTradeController::class, 'release']);
    Route::post('/trades/{trade}/cancel', [MobileTradeController::class, 'cancel']);
    Route::post('/trades/{trade}/dispute', [MobileTradeController::class, 'dispute']);
    Route::get('/trades/{trade}/messages', [MobileTradeController::class, 'messages']);
    Route::post('/trades/{trade}/messages', [MobileTradeController::class, 'sendMessage']);

    // P2P Ratings
    Route::post('/trades/{trade}/rate', [MobileRatingController::class, 'store']);
    Route::get('/users/{user}/ratings', [MobileRatingController::class, 'userRatings']);
    Route::get('/users/{user}/stats', [MobileRatingController::class, 'stats']);

    // P2P Notifications
    Route::get('/notifications', [MobileNotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [MobileNotificationController::class, 'read']);
    Route::post('/notifications/read-all', [MobileNotificationController::class, 'readAll']);
    Route::get('/notifications/unread-count', [MobileNotificationController::class, 'unreadCount']);

    // Remittances (Requester side)
    Route::get('/remittances', [MobileRemittanceController::class, 'index']);
    Route::post('/remittances', [MobileRemittanceController::class, 'store']);
    Route::get('/remittances/{moneyTransfer}', [MobileRemittanceController::class, 'show']);
    Route::post('/remittances/{moneyTransfer}/requester-proof', [MobileRemittanceController::class, 'uploadRequesterProof']);
    Route::post('/remittances/{moneyTransfer}/confirm', [MobileRemittanceController::class, 'confirm']);
    Route::post('/remittances/{moneyTransfer}/cancel', [MobileRemittanceController::class, 'cancel']);
    Route::get('/remittances/debts/list', [MobileRemittanceController::class, 'debts']);

    // Remittance Support Tickets
    Route::post('/support/remittance-tickets', [MobileRemittanceTicketController::class, 'store']);

    // Mobile Reports (User self-service)
    Route::get('/reports/my-activity', [MobileReportController::class, 'myActivity']);
    Route::get('/reports/my-activity/export', [MobileReportController::class, 'exportMyActivity']);

    // Agent Orders (Agent side)
    Route::get('/agent/orders', [MobileAgentOrderController::class, 'index']);
    Route::get('/agent/orders/{moneyTransfer}', [MobileAgentOrderController::class, 'show']);
    Route::post('/agent/orders/{moneyTransfer}/accept', [MobileAgentOrderController::class, 'accept']);
    Route::post('/agent/orders/{moneyTransfer}/execute', [MobileAgentOrderController::class, 'execute']);
    Route::post('/agent/orders/{moneyTransfer}/proof', [MobileAgentOrderController::class, 'uploadProof']);

    // Agent Marketplace
    Route::get('/agents', [MobileAgentController::class, 'index']);
    Route::get('/agents/{user}', [MobileAgentController::class, 'show']);

    // Agent Self-Management
    Route::patch('/agent/availability', [MobileAgentController::class, 'toggleAvailability']);
    Route::post('/agent/profile', [MobileAgentController::class, 'updateProfile']);

    // Online Ping
    Route::post('/ping', [MobileAgentController::class, 'ping']);

    // Device Tokens
    Route::post('/device-tokens', [MobileDeviceTokenController::class, 'store']);
    Route::delete('/device-tokens/{token}', [MobileDeviceTokenController::class, 'destroy']);
});

// Protected Routes (Session-based via Vue Portal)
Route::middleware(['auth', 'approved', 'role:super_admin|Admin|Operator'])->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    // 2FA setup/challenge routes — behind auth but NOT 2fa middleware
    Route::prefix('portal/2fa')->group(function () {
        Route::get('status', [\App\Http\Controllers\Api\Portal\TwoFactorController::class, 'status']);
        Route::post('setup', [\App\Http\Controllers\Api\Portal\TwoFactorController::class, 'setup']);
        Route::post('confirm', [\App\Http\Controllers\Api\Portal\TwoFactorController::class, 'confirm']);
        Route::post('challenge', [\App\Http\Controllers\Api\Portal\TwoFactorController::class, 'challenge']);
        Route::post('disable', [\App\Http\Controllers\Api\Portal\TwoFactorController::class, 'disable']);
    });

    // All portal routes behind 2fa check
    Route::prefix('portal')->middleware('2fa')->group(function () {

        // Money Transfer Workflow
        Route::get('/transfers/stats', [MoneyTransferController::class, 'stats']);
        Route::get('/transfers', [MoneyTransferController::class, 'index']);
        Route::post('/transfers', [MoneyTransferController::class, 'store']);
        Route::get('/transfers/{moneyTransfer}', [MoneyTransferController::class, 'show']);
        Route::post('/transfers/{moneyTransfer}/usdt-proof', [MoneyTransferController::class, 'uploadUsdtProof']);
        Route::post('/transfers/{moneyTransfer}/confirm-usdt', [MoneyTransferController::class, 'confirmUsdt']);
        Route::post('/transfers/{moneyTransfer}/payout-proof', [MoneyTransferController::class, 'uploadPayoutProof']);
        Route::patch('/transfers/{moneyTransfer}/status', [MoneyTransferController::class, 'updateStatus']);

        // Country Management
        Route::get('/countries', [AdminCountryController::class, 'index']);
        Route::get('/countries/{country}', [AdminCountryController::class, 'show']);
        Route::middleware('permission:manage_countries')->group(function () {
            Route::post('/countries', [AdminCountryController::class, 'store']);
            Route::put('/countries/{country}', [AdminCountryController::class, 'update']);
            Route::delete('/countries/{country}', [AdminCountryController::class, 'destroy']);
            Route::post('/countries/seed', function () {
                \Illuminate\Support\Facades\Artisan::call('countries:seed');
                return response()->json(['message' => 'Countries seeded successfully.']);
            })->name('countries.seed');
        });

        // Currencies
        Route::get('/currencies', [CurrencyController::class, 'index']);
        Route::get('/currencies/{currency}', [CurrencyController::class, 'show']);
        Route::get('/currencies/{currency}/exchange-rates', [CurrencyController::class, 'exchangeRates']);
        Route::middleware('permission:manage_currencies')->group(function () {
            Route::post('/currencies', [CurrencyController::class, 'store']);
            Route::put('/currencies/{currency}', [CurrencyController::class, 'update']);
            Route::delete('/currencies/{currency}', [CurrencyController::class, 'destroy']);
            Route::post('/exchange-rates', [CurrencyController::class, 'storeExchangeRate']);
            Route::delete('/exchange-rates/{exchangeRate}', [CurrencyController::class, 'destroyExchangeRate']);
        });

        // User / Agent Management (read routes open to all portal users)
        Route::get('/users/stats', [UserController::class, 'kycStats']);
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        // Write operations require manage_users permission
        Route::middleware('permission:manage_users')->group(function () {
            Route::post('/users/{user}/approve-kyc', [UserController::class, 'approveKyc']);
            Route::post('/users/{user}/suspend', [UserController::class, 'suspend']);
            Route::post('/users/{user}/assign-role', [UserController::class, 'assignRole']);
            Route::post('/users/{user}/kyc-tier', [UserController::class, 'setKycTier']);
            Route::post('/users/{user}/flag', [UserController::class, 'flag']);
            Route::post('/users/{user}/toggle-trading', [UserController::class, 'toggleTrading']);
        });

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

        // P2P Admin — Trades
        Route::get('/trades', [AdminTradeController::class, 'index']);
        Route::get('/trades/{trade}', [AdminTradeController::class, 'show']);
        Route::post('/trades/{trade}/cancel', [AdminTradeController::class, 'cancel']);
        Route::post('/trades/{trade}/resolve-dispute', [AdminTradeController::class, 'resolveDispute']);
        Route::get('/trades/{trade}/dispute-messages', [AdminDisputeMessageController::class, 'index']);
        Route::post('/trades/{trade}/dispute-messages', [AdminDisputeMessageController::class, 'store']);

        // P2P Admin — Platform Fees
        Route::get('/platform-fees', [AdminPlatformFeeController::class, 'index']);
        Route::post('/platform-fees', [AdminPlatformFeeController::class, 'store']);
        Route::get('/platform-fees/{platformFee}', [AdminPlatformFeeController::class, 'show']);
        Route::put('/platform-fees/{platformFee}', [AdminPlatformFeeController::class, 'update']);
        Route::delete('/platform-fees/{platformFee}', [AdminPlatformFeeController::class, 'destroy']);

        // P2P Admin — Reference Prices
        Route::get('/reference-prices', [AdminReferencePriceController::class, 'index']);
        Route::post('/reference-prices', [AdminReferencePriceController::class, 'store']);
        Route::get('/reference-prices/{referencePrice}', [AdminReferencePriceController::class, 'show']);
        Route::put('/reference-prices/{referencePrice}', [AdminReferencePriceController::class, 'update']);
        Route::delete('/reference-prices/{referencePrice}', [AdminReferencePriceController::class, 'destroy']);
        Route::get('/reference-prices/latest', [AdminReferencePriceController::class, 'latest']);

        // P2P Admin — Revenue Dashboard
        Route::get('/revenue/summary', [AdminRevenueController::class, 'summary']);
        Route::get('/revenue/by-pair', [AdminRevenueController::class, 'byPair']);
        Route::get('/revenue/totals', [AdminRevenueController::class, 'totals']);

        // P2P Admin — Ratings
        Route::get('/ratings', [AdminRatingController::class, 'index']);

        // P2P Admin — Announcements
        Route::get('/announcements', [AdminAnnouncementController::class, 'index']);
        Route::post('/announcements', [AdminAnnouncementController::class, 'store']);
        Route::get('/announcements/{announcement}', [AdminAnnouncementController::class, 'show']);
        Route::put('/announcements/{announcement}', [AdminAnnouncementController::class, 'update']);
        Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'destroy']);
        Route::post('/announcements/{announcement}/toggle-publish', [AdminAnnouncementController::class, 'togglePublish']);

        // P2P Admin — Exports
        Route::get('/exports/trades', [AdminExportController::class, 'trades']);

        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('/remittances', [AdminReportController::class, 'remittances']);
            Route::get('/remittances/export', [AdminReportController::class, 'exportRemittances']);
            Route::get('/debts', [AdminReportController::class, 'debts']);
            Route::get('/debts/export', [AdminReportController::class, 'exportDebts']);
            Route::get('/agent-performance', [AdminReportController::class, 'agentPerformance']);
            Route::get('/agent-performance/export', [AdminReportController::class, 'exportAgentPerformance']);
            Route::get('/platform-summary', [AdminReportController::class, 'platformSummary']);
            Route::get('/remittances/{moneyTransfer}/download', [AdminReportController::class, 'downloadRemittancePdf']);
        });

        // Support System
        Route::prefix('support')->group(function () {
            Route::get('categories', [SupportCategoryController::class, 'index']);
            Route::get('categories/all', [SupportCategoryController::class, 'all']);
            Route::post('categories', [SupportCategoryController::class, 'store']);
            Route::put('categories/{supportCategory}', [SupportCategoryController::class, 'update']);
            Route::delete('categories/{supportCategory}', [SupportCategoryController::class, 'destroy']);
            Route::get('tickets', [SupportTicketController::class, 'index']);
            Route::post('tickets', [SupportTicketController::class, 'store']);
            Route::get('tickets/{ticket}', [SupportTicketController::class, 'show']);
            Route::patch('tickets/{ticket}/status', [SupportTicketController::class, 'updateStatus']);
            Route::patch('tickets/{ticket}/assign', [SupportTicketController::class, 'assign']);
            Route::post('tickets/{ticket}/messages', [SupportTicketMessageController::class, 'store']);
        });
    });
});

// API Key-authenticated routes — use X-API-Key header or ?api_key= query param
Route::prefix('v1')->middleware('auth.api_key')->group(function () {
    Route::get('/keys', [ApiKeyController::class, 'index']);
    Route::post('/keys', [ApiKeyController::class, 'store']);
    Route::delete('/keys/{apiKey}', [ApiKeyController::class, 'destroy']);
});
