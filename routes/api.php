<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileTripController;
use App\Http\Controllers\Api\MoneyTransferController;
use App\Http\Controllers\Api\Support\SupportCategoryController;
use App\Http\Controllers\Api\Support\SupportTicketController;
use App\Http\Controllers\Api\Support\SupportTicketMessageController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ComplianceSummaryController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ControlTowerController;
use App\Http\Controllers\Api\PortalControlTowerController;
use App\Http\Controllers\Api\DispatchController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\FineCheckController;
use App\Http\Controllers\Api\FinesAnalyticsController;
use App\Http\Controllers\Api\FinesController;
use App\Http\Controllers\Api\FirebaseVerificationController;
use App\Http\Controllers\Api\FleetDashboardController;
use App\Http\Controllers\Api\InspectionController;
use App\Http\Controllers\Api\InsuranceController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\WhatsAppVerificationController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\RoutesController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\MockDispatchController;
use App\Http\Controllers\Api\DashboardController;

// Public Routes
Route::post('/login', [AuthController::class, 'login']); // Token-based
Route::post('/portal/login', [AuthController::class, 'portalLogin']); // Session-based
Route::get('/dispatch/secure-print', [DispatchController::class, 'printStatus'])
    ->name('dispatch.print.secure')
    ->middleware('signed'); // Laravel 12 handles this alias automatically
Route::get('/search/global', [SearchController::class, 'search']);

// Mobile Auth Routes
Route::prefix('mobile/auth')->group(function () {
    Route::post('/request-whatsapp-otp', [MobileAuthController::class, 'requestWhatsAppOtp']);
    Route::post('/verify-whatsapp-otp', [MobileAuthController::class, 'verifyWhatsAppOtp']);
    Route::post('/verify-firebase-phone', [MobileAuthController::class, 'verifyFirebasePhone']);
    Route::post('/logout', [MobileAuthController::class, 'logout'])->middleware('auth:sanctum');
});

// Mobile App Routes (Protected)
Route::prefix('mobile')->middleware('auth:sanctum')->group(function () {
    Route::prefix('trips')->group(function () {
        Route::get('/current', [MobileTripController::class, 'current']);
        Route::post('/{trip}/status', [MobileTripController::class, 'updateStatus']);
    });
});

// Protected Routes
Route::middleware('auth')->group(function () {

    // Initial Session Check (Critical for UI protection on refresh)
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    // --- Portal Routes (Session-based via Vue) ---
    Route::prefix('portal')->group(function () {

        // Fines & Analytics
        Route::get('/fines', [FinesController::class, 'index']);
        Route::get('/fines/export', [FinesController::class, 'export']);
        Route::get('/fines/stats', [FinesController::class, 'stats']);
        Route::get('/fines/recent/{plate}', [FinesController::class, 'recent']);
        Route::post('/fines/check', [FinesController::class, 'forceCheck']);
        Route::get('/fines/analytics', [FinesAnalyticsController::class, 'index']);
        Route::get('/fines/by-day', [FinesAnalyticsController::class, 'byDay']);
        Route::get('/fines/export-day', [FinesAnalyticsController::class, 'exportDay']);
        Route::get('/fines/by-violation', [FinesAnalyticsController::class, 'byViolation']);

        //Mock data
        Route::prefix('/mock')->group(function () {
            Route::get('/clients', [MockDispatchController::class, 'searchClients']);
            Route::get('/clients/{id}/order', [MockDispatchController::class, 'getClientOrder']);
            Route::get('/routes', [MockDispatchController::class, 'getRoutes']);
            Route::get('/assignments', [MockDispatchController::class, 'searchAssignments']);
        });

        // Operations
        Route::get('/drivers', [DriverController::class, 'index']);
        Route::get('/drivers/search-users', [DriverController::class, 'searchUsers']);
        Route::post('/drivers', [DriverController::class, 'store']);
        Route::post('/trips', [TripController::class, 'store']);

        Route::get('/vehicles', [VehicleController::class, 'index']);

        Route::get('/dispatch', [DispatchController::class, 'index']);
        Route::post('/dispatch/pair', [DispatchController::class, 'pair']);
        Route::get('/dispatch/export', [DispatchController::class, 'export']);
        Route::get('/dispatch/history/{id}', [DispatchController::class, 'history']);
        Route::post('/dispatch/maintenance', [DispatchController::class, 'toggleMaintenance']);
        Route::post('/dispatch/activate', [DispatchController::class, 'activateVehicle']);
        Route::get('/dispatch/print-url', [DispatchController::class, 'getPrintUrl']);
        Route::post('/dispatch/toggle-status', [DispatchController::class, 'toggleStatus']);

        Route::get('/insurances', [InsuranceController::class, 'index']);
        Route::post('/insurances', [InsuranceController::class, 'store']);
        Route::get('/inspections', [InsuranceController::class, 'index']);
        Route::post('/inspections', [InsuranceController::class, 'store']);

        // Routes
        Route::get('/routes', [RoutesController::class, 'index']);
        Route::get('/routes/{route}', [RoutesController::class, 'show']);
        Route::post('/routes/store', [RoutesController::class, 'store']);
        Route::put('/routes/{route}', [RoutesController::class, 'update']);
        Route::delete('/routes/{route}', [RoutesController::class, 'destroy']);

        Route::get('/compliance-summary', [ComplianceSummaryController::class, 'complianceSummary']);
        Route::get('/control-tower', [ControlTowerController::class, 'index']);
        Route::get('/report/{type}', [FleetDashboardController::class, 'getDetailedReport']);

        // Dashboard
        Route::get('/dashboard/overview', [DashboardController::class, 'getOverview']);
        Route::get('/dashboard/analytics', [DashboardController::class, 'getAnalytics']);
        Route::get('/dashboard/operational-status', [DashboardController::class, 'getOperationalStatus']);

        // Money Transfer Workflow
        Route::get('/transfers/stats', [MoneyTransferController::class, 'stats']);
        Route::get('/transfers', [MoneyTransferController::class, 'index']);
        Route::post('/transfers', [MoneyTransferController::class, 'store']);
        Route::get('/transfers/{moneyTransfer}', [MoneyTransferController::class, 'show']);
        Route::post('/transfers/{moneyTransfer}/usdt-proof', [MoneyTransferController::class, 'uploadUsdtProof']);
        Route::post('/transfers/{moneyTransfer}/confirm-usdt', [MoneyTransferController::class, 'confirmUsdt']);
        Route::post('/transfers/{moneyTransfer}/payout-proof', [MoneyTransferController::class, 'uploadPayoutProof']);
        Route::patch('/transfers/{moneyTransfer}/status', [MoneyTransferController::class, 'updateStatus']);

        Route::get('/drivers/{driver}', [SearchController::class, 'showDriver']);
        Route::get('/vehicles/{vehicle}', [SearchController::class, 'showVehicle']);

        // ** MOVED THIS ROUTE HIGHER **
        // Order Search (For the left sidebar)
        Route::get('/orders/search', [SearchController::class, 'searchOrders']);

        Route::get('/orders/{order}', [TripController::class, 'showOrder']);

        // Trip Lifecycle
        Route::post('/trips', [TripController::class, 'store']);
        Route::get('/trips/search-assignments', [TripController::class, 'searchAssignments']);


        // Support System Nested Group
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
