<?php

use App\Enums\StockMovementType;
use App\Livewire\Storefront\ProductDetail;
use App\Models\ProductVariant;
use App\Models\StockSubscription;
use App\Models\User;
use App\Notifications\BackInStockNotification;
use App\Services\StockService;
use Livewire\Livewire;
use Illuminate\Support\Facades\Notification;

test('a buyer subscribes to an out-of-stock variant and is alerted once on restock', function () {
    Notification::fake();

    $variant = ProductVariant::factory()->create(['stock' => 0]);
    $buyer = User::factory()->create();
    StockSubscription::create(['user_id' => $buyer->id, 'product_variant_id' => $variant->id]);

    app(StockService::class)->apply($variant, 5, StockMovementType::Restock);

    Notification::assertSentTo($buyer, BackInStockNotification::class);
    expect(StockSubscription::count())->toBe(0); // one-shot — cleared after the alert
});

test('no alert fires when stock moves positive → higher (not a restock)', function () {
    Notification::fake();

    $variant = ProductVariant::factory()->create(['stock' => 3]);
    $buyer = User::factory()->create();
    StockSubscription::create(['user_id' => $buyer->id, 'product_variant_id' => $variant->id]);

    app(StockService::class)->apply($variant, 5, StockMovementType::Restock);

    Notification::assertNotSentTo($buyer, BackInStockNotification::class);
    expect(StockSubscription::count())->toBe(1);
});

test('the PDP notify button creates one subscription, idempotently', function () {
    $variant = ProductVariant::factory()->create(['stock' => 0]);
    $buyer = User::factory()->create();

    Livewire::actingAs($buyer)
        ->test(ProductDetail::class, ['product' => $variant->product])
        ->call('notifyWhenAvailable', $variant->id)
        ->call('notifyWhenAvailable', $variant->id); // second tap must not duplicate

    expect(StockSubscription::where('user_id', $buyer->id)->where('product_variant_id', $variant->id)->count())->toBe(1);
});

test('a guest tapping notify is redirected to login, not subscribed', function () {
    $variant = ProductVariant::factory()->create(['stock' => 0]);

    Livewire::test(ProductDetail::class, ['product' => $variant->product])
        ->call('notifyWhenAvailable', $variant->id)
        ->assertRedirect(route('login'));

    expect(StockSubscription::count())->toBe(0);
});
