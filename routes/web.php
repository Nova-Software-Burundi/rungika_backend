<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('pages.home-alt'));
Route::get('/about', fn () => view('pages.about'));
Route::get('/contact', fn () => view('pages.contact'));
Route::get('/services', fn () => view('pages.services'));
Route::get('/tracking', fn () => view('pages.tracking'));

Route::view('/portal/{any?}', 'portal')->where('any', '.*');
