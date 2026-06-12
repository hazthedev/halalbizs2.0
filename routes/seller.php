<?php

use Illuminate\Support\Facades\Route;

// Seller centre — built in M3. Guarded by EnsureSeller (role + approved store).
Route::view('/', 'seller.placeholder')->name('dashboard');
