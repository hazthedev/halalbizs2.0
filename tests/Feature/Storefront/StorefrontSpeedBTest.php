<?php

use App\Livewire\Storefront\CartPage;
use App\Livewire\Storefront\Checkout;
use App\Livewire\Storefront\FlashSale;
use App\Livewire\Storefront\ProductDetail;
use App\Models\Address;
use App\Models\FlashSale as FlashSaleModel;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function speedBQueries(string $label, Closure $action): array
{
    DB::enableQueryLog();
    DB::flushQueryLog();
    $action();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    fwrite(STDERR, "SPEED {$label}: ".count($queries)." queries\n");

    return $queries;
}

function speedBPurchasing(): void
{
    $settings = app(GeneralSettings::class);
    $settings->purchasing_enabled = true;
    $settings->save();
}

it('picks variant and qty in the browser: no server call, add and buy send the picked variant', function () {
    speedBPurchasing();
    $product = Product::factory()->withVariants(colour: 2, size: 1)->create();
    $product->load(['options.values', 'variants']);
    $product->variants->each->update(['stock' => 5]);
    $page = Livewire::test(ProductDetail::class, ['product' => $product]);

    // BEFORE (main): each variant click or qty step was this server round trip + full re-render.
    speedBQueries('pdp variant click, old server trip', fn () => $page->call('selectValue', $product->options->first()->id, $product->options->first()->values->first()->id));

    $html = $page->html();
    expect($html)->not->toContain('selectValue(')->not->toContain('incrementQty')->not->toContain('decrementQty');
    expect($html)->toContain('data-value-id');

    // AFTER: add/buy are renderless and the variant must belong to this product.
    $user = User::factory()->create();
    $variant = $product->variants->first();
    $page = Livewire::actingAs($user)->test(ProductDetail::class, ['product' => $product]);
    $page->call('addToCart', $variant->id, 2)->assertReturned(true);
    expect($page->effects)->not->toHaveKey('html');
    expect(app(CartService::class)->itemCount($user))->toBe(2);

    $foreign = Product::factory()->create()->variants->first();
    $foreign->update(['stock' => 5]);
    $page->call('buyNow', $foreign->id, 1)->assertNoRedirect();
    expect(app(CartService::class)->itemCount($user))->toBe(2);
    $page->call('buyNow', $variant->id, 1)->assertRedirect(route('cart'));
});

it('writes the stock clamp when the cart loads, never while rendering', function () {
    speedBPurchasing();
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $variant = $product->variants->first();
    $variant->update(['stock' => 10]);
    app(CartService::class)->addItem($user, $variant, 6);
    $variant->update(['stock' => 3]);

    $page = Livewire::actingAs($user)->test(CartPage::class);
    expect(app(CartService::class)->itemCount($user))->toBe(3);

    $variant->update(['stock' => 1]);
    $queries = speedBQueries('cart render', fn () => $page->call('$refresh'));
    expect(collect($queries)->filter(fn ($q) => preg_match('/^(update|insert|delete)/i', $q['query'])))->toBeEmpty();
    expect(app(CartService::class)->itemCount($user))->toBe(3);
});

it('reads the checkout addresses once per render', function () {
    speedBPurchasing();
    $user = User::factory()->create();
    Address::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create();
    $product->variants->first()->update(['stock' => 5]);
    app(CartService::class)->addItem($user, $product->variants->first(), 1);

    $page = Livewire::actingAs($user)->test(Checkout::class);
    $queries = speedBQueries('checkout render', fn () => $page->call('$refresh'));
    expect(collect($queries)->filter(fn ($q) => str_contains($q['query'], 'from `addresses`')))->toHaveCount(1);
});

it('shares the flash sale read between viewers for 30 s and polls only when visible', function () {
    Cache::flush();
    $variant = Product::factory()->create()->variants->first();
    $sale = FlashSaleModel::create(['title' => '11.11', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true]);
    FlashSaleItem::create(['flash_sale_id' => $sale->id, 'product_variant_id' => $variant->id, 'promo_price_sen' => 100, 'allocated_qty' => 5, 'per_buyer_limit' => 1, 'sold_qty' => 0]);

    speedBQueries('flash sale cold (= every poll on main)', fn () => Livewire::test(FlashSale::class));
    $page = null;
    $queries = speedBQueries('flash sale warm', function () use (&$page) { $page = Livewire::test(FlashSale::class); });
    expect(collect($queries)->filter(fn ($q) => str_contains($q['query'], 'flash_sale_items')))->toBeEmpty();
    expect($page->html())->toContain('wire:poll.30s.visible');
});
