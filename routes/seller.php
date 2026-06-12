<?php

use App\Livewire\Seller;
use Illuminate\Support\Facades\Route;

// Seller centre — guarded by EnsureSeller (role + approved store).
Route::get('/', Seller\Dashboard::class)->name('dashboard');

Route::get('/products', Seller\Products\Index::class)->name('products.index');
Route::get('/products/create', Seller\Products\Form::class)->name('products.create');
Route::get('/products/{product}/edit', Seller\Products\Form::class)->name('products.edit');

Route::get('/settings', Seller\Settings::class)->name('settings');
