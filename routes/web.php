<?php

use Illuminate\Support\Facades\Route;

// Storefront — buyer-facing pages built in M2.
Route::view('/', 'storefront.placeholder')->name('home');

// Placeholder until real auth lands in M2; named route needed by the auth middleware.
Route::view('/login', 'storefront.placeholder')->name('login');
