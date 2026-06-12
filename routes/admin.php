<?php

use Illuminate\Support\Facades\Route;

// Admin panel — built in M7. Guarded by EnsureAdmin.
Route::view('/', 'admin.placeholder')->name('dashboard');
