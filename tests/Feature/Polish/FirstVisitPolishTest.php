<?php

use App\Livewire\Storefront\CartPage;
use App\Models\Product;
use App\Services\CartService;
use Livewire\Livewire;

test('the welcome tour renders on the storefront home only', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('hb_tour_done', false)   // localStorage gate
        ->assertSee('Welcome to HalalBizs')
        ->assertSee('Skip tour');

    $this->get('/cart')
        ->assertOk()
        ->assertDontSee('hb_tour_done');
});

test('guests with a cart see the saved-in-this-browser nudge under the login CTA', function () {
    $variant = Product::factory()->create()->variants()->first();
    $variant->update(['stock' => 5, 'sale_price_sen' => null]);

    app(CartService::class)->addItem(null, $variant, 1);

    Livewire::test(CartPage::class)
        ->assertSee('Log in to check out')
        ->assertSee('Your cart is saved in this browser — log in to keep it everywhere.');
});
