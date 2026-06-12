<?php

use App\Enums\ProductStatus;
use App\Livewire\Storefront\CartPage;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use Livewire\Livewire;

/** A live product's default variant with a deterministic price and stock. */
function cartPageVariant(int $priceSen, int $stock = 10): ProductVariant
{
    $variant = Product::factory()->create()->variants()->first();

    $variant->update([
        'price_sen' => $priceSen,
        'sale_price_sen' => null,
        'sale_starts_at' => null,
        'sale_ends_at' => null,
        'stock' => $stock,
    ]);

    return $variant->refresh();
}

test('cart page renders the empty state', function () {
    $this->get(route('cart'))
        ->assertOk()
        ->assertSee('Your cart is empty')
        ->assertSee('Start shopping');
});

test('a buyer sees items grouped by store and a total of selected lines only', function () {
    $user = User::factory()->create();
    $kept = cartPageVariant(10000);      // RM 100.00
    $unticked = cartPageVariant(2500);   // RM 25.00

    $service = app(CartService::class);
    $service->addItem($user, $kept, 2);
    $service->addItem($user, $unticked, 1);

    CartItem::where('product_variant_id', $unticked->id)->update(['selected' => false]);

    Livewire::actingAs($user)
        ->test(CartPage::class)
        ->assertSee($kept->product->store->name)
        ->assertSee($unticked->product->store->name)
        ->assertSee($kept->product->getTranslation('name', 'en'))
        ->assertSee($unticked->product->getTranslation('name', 'en'))
        ->assertSee('RM 200.00')
        ->assertDontSee('RM 225.00');
});

test('updating quantity changes the line and the items total', function () {
    $user = User::factory()->create();
    $variant = cartPageVariant(10000);

    app(CartService::class)->addItem($user, $variant, 1);

    Livewire::actingAs($user)
        ->test(CartPage::class)
        ->assertSee('RM 100.00')
        ->call('updateQty', $variant->id, 3)
        ->assertSee('RM 300.00')
        ->assertDispatched('cart-updated', count: 3);

    expect(CartItem::where('product_variant_id', $variant->id)->first()->qty)->toBe(3);
});

test('deselecting a line excludes it from the items total', function () {
    $user = User::factory()->create();
    $a = cartPageVariant(10000);
    $b = cartPageVariant(2500);

    $service = app(CartService::class);
    $service->addItem($user, $a, 1);
    $service->addItem($user, $b, 1);

    Livewire::actingAs($user)
        ->test(CartPage::class)
        ->assertSee('RM 125.00')
        ->call('toggleSelected', $b->id)
        ->assertDontSee('RM 125.00')
        ->assertSee('RM 100.00');

    expect(CartItem::where('product_variant_id', $b->id)->first()->selected)->toBeFalse();
});

test('removing a line dispatches cart-updated and an undoable toast', function () {
    $user = User::factory()->create();
    $variant = cartPageVariant(10000);

    app(CartService::class)->addItem($user, $variant, 2);

    Livewire::actingAs($user)
        ->test(CartPage::class)
        ->call('removeLine', $variant->id)
        ->assertDispatched('cart-updated', count: 0)
        ->assertDispatched('toast', actionEvent: 'undo-remove')
        ->assertSee('Your cart is empty');

    expect(CartItem::count())->toBe(0);
});

test('undo restores the removed line with its original quantity', function () {
    $user = User::factory()->create();
    $variant = cartPageVariant(10000);

    app(CartService::class)->addItem($user, $variant, 2);

    Livewire::actingAs($user)
        ->test(CartPage::class)
        ->call('removeLine', $variant->id)
        ->call('undoRemove', $variant->id)
        ->assertSee('RM 200.00');

    expect(CartItem::where('product_variant_id', $variant->id)->first()->qty)->toBe(2);
});

test('quantities above stock are clamped, persisted, and flagged', function () {
    $user = User::factory()->create();
    $variant = cartPageVariant(10000, stock: 10);

    app(CartService::class)->addItem($user, $variant, 5);

    $variant->update(['stock' => 3]);

    Livewire::actingAs($user)
        ->test(CartPage::class)
        ->assertSee('Only 3 left')
        ->assertSee('quantity adjusted')
        ->assertSee('RM 300.00');

    expect(CartItem::where('product_variant_id', $variant->id)->first()->qty)->toBe(3);
});

test('lines whose product is no longer live are excluded from the total', function () {
    $user = User::factory()->create();
    $live = cartPageVariant(10000);
    $gone = cartPageVariant(2500);

    $service = app(CartService::class);
    $service->addItem($user, $live, 1);
    $service->addItem($user, $gone, 1);

    $gone->product->update(['status' => ProductStatus::Delisted]);

    Livewire::actingAs($user)
        ->test(CartPage::class)
        ->assertSee('No longer available')
        ->assertSee('RM 100.00')
        ->assertDontSee('RM 125.00');
});

test('a guest sees the session cart and is pointed to log in to check out', function () {
    $variant = cartPageVariant(10000);

    app(CartService::class)->addItem(null, $variant, 2);

    Livewire::test(CartPage::class)
        ->assertSee('RM 200.00')
        ->assertSee('Log in to check out')
        ->assertDontSee('Select all');
});
