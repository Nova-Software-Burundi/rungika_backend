<?php

use App\Filament\Pages\TwoFactorChallenge;
use App\Http\Controllers\OrderPrintController;
use Illuminate\Support\Facades\Route;

Route::get('/test-2fa', function () {
    $user = auth()->user();
    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
    ])->save();

    return '2FA enabled for ' . $user->email;
})->middleware('auth');

Route::get('/admin/2fa-challenge', TwoFactorChallenge::class)
    ->middleware(['auth'])
    ->name('filament.2fa.challenge');

Route::get('/orders/{order}/print', OrderPrintController::class)
    ->name('orders.print');


Route::get('/', function () {
    return view('pages.home-alt');
});

Route::get('/about', function () {
    return view('pages.about');
});

Route::get('/contact', function () {
    return view('pages.contact');
});

Route::get('/services', function () {
    return view('pages.services');
});

Route::get('/tracking', function () {
    return view('pages.tracking');
});

//Portal
Route::view('/portal/{any?}', 'portal')->where('any', '.*');
