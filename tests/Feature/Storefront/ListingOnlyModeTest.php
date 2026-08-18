<?php

use App\Enums\GroupBuyStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionInterval;
use App\Exceptions\CheckoutException;
use App\Livewire\Admin\System\Settings;
use App\Enums\CoinTransactionType;
use App\Livewire\Storefront\Account\Coins;
use App\Livewire\Storefront\CartPage;
use App\Livewire\Storefront\Checkout;
use App\Livewire\Storefront\Layout\MiniCart;
use App\Livewire\Storefront\ProductDetail;
use App\Models\Address;
use App\Models\GroupBuy;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\CoinService;
use App\Services\GroupBuyService;
use App\Services\SubscriptionService;
use App\Settings\GeneralSettings;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['groupbuy.enabled' => true, 'subscriptions.enabled' => true]);
});

function setListingOnlyMode(bool $listingOnly = true): void
{
    $settings = app(GeneralSettings::class);
    $settings->purchasing_enabled = ! $listingOnly;
    $settings->save();
}

function listingModeProduct(): Product
{
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update([
        'price_sen' => 10_000,
        'sale_price_sen' => null,
        'stock' => 10,
    ]);

    return $product->fresh('variants');
}

test('an admin can switch the marketplace into listing-only mode', function () {
    $this->seed(CurrencySeeder::class);
    $admin = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($admin);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->assertSet('purchasingEnabled', true)
        ->set('purchasingEnabled', false)
        ->call('saveGeneral')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    expect(app(GeneralSettings::class)->refresh()->purchasing_enabled)->toBeFalse();
});

test('listing-only product pages replace purchase actions and reject forged add-to-cart calls', function () {
    $buyer = User::factory()->create();
    $product = listingModeProduct();
    setListingOnlyMode();

    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('Listing only')
        ->assertSee('Purchasing is currently unavailable.')
        ->assertDontSee('data-testid="pdp-add-to-cart"', false)
        ->assertDontSee('open-mini-cart', false);

    Livewire::actingAs($buyer)
        ->test(ProductDetail::class, ['product' => $product])
        ->call('addToCart', $product->variants->first()->id)
        ->assertDispatched('toast', type: 'error')
        ->call('buyNow')
        ->assertDispatched('toast', type: 'error');

    expect(app(CartService::class)->itemCount($buyer))->toBe(0);
});

test('the cart service fails closed while listing-only mode is active', function () {
    $product = listingModeProduct();
    setListingOnlyMode();

    expect(fn () => app(CartService::class)->addItem(null, $product->variants->first(), 1))
        ->toThrow(CheckoutException::class, 'listing-only mode');
});

test('existing carts stay stored but quantity increases and checkout are blocked', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id]);
    $product = listingModeProduct();
    $variant = $product->variants->first();

    app(CartService::class)->addItem($buyer, $variant, 1);
    setListingOnlyMode();

    Livewire::actingAs($buyer)
        ->test(CartPage::class)
        ->assertSee('Listing-only mode')
        ->assertSee('Checkout unavailable')
        ->call('updateQty', $variant->id, 3)
        ->assertDispatched('toast', type: 'error');

    Livewire::actingAs($buyer)
        ->test(MiniCart::class)
        ->call('updateQty', $variant->id, 3)
        ->assertDispatched('toast', type: 'error');

    expect(app(CartService::class)->itemCount($buyer))->toBe(1);

    Livewire::actingAs($buyer)
        ->test(Checkout::class)
        ->assertRedirect(route('home'));

    expect(fn () => app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod))
        ->toThrow(CheckoutException::class, 'listing-only mode');

    expect(Order::query()->count())->toBe(0)
        ->and(app(CartService::class)->itemCount($buyer))->toBe(1);
});

test('recurring orders remain paused without advancing their schedule', function () {
    $buyer = User::factory()->create();
    $address = Address::factory()->default()->create(['user_id' => $buyer->id]);
    $product = listingModeProduct();
    $subscriptions = app(SubscriptionService::class);

    $subscription = $subscriptions->subscribe(
        $buyer,
        $product->variants->first(),
        $address,
        SubscriptionInterval::Weekly,
    );
    $subscription->update(['next_run_at' => now()->subMinute()]);
    $dueAt = $subscription->fresh()->next_run_at;

    setListingOnlyMode();

    expect($subscriptions->processDue())->toBe(0)
        ->and(Order::where('subscription_id', $subscription->id)->exists())->toBeFalse()
        ->and($subscription->fresh()->next_run_at->equalTo($dueAt))->toBeTrue();

    expect(fn () => $subscriptions->subscribe(
        $buyer,
        $product->variants->first(),
        $address,
        SubscriptionInterval::Monthly,
    ))->toThrow(CheckoutException::class, 'listing-only mode');
});

test('group-buy creation is disabled with the rest of purchasing', function () {
    $product = listingModeProduct();
    $deal = GroupBuy::create([
        'store_id' => $product->store_id,
        'product_id' => $product->id,
        'product_variant_id' => $product->variants->first()->id,
        'group_price_sen' => 8000,
        'target_size' => 2,
        'team_window_hours' => 24,
        'status' => GroupBuyStatus::Active,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay(),
    ]);
    setListingOnlyMode();

    $service = app(GroupBuyService::class);

    expect($service->enabled())->toBeFalse();
    expect(fn () => $service->startTeam(User::factory()->create(), $deal))
        ->toThrow(CheckoutException::class, 'listing-only mode');
});

test('listing-only buyer copy is translated in Malay and Vietnamese', function () {
    app()->setLocale('ms');
    expect(__('Listing only'))->toBe('Penyenaraian sahaja')
        ->and(__('Checkout unavailable'))->toBe('Pembayaran tidak tersedia');

    app()->setLocale('vi');
    expect(__('Listing only'))->toBe('Chỉ niêm yết')
        ->and(__('Checkout unavailable'))->toBe('Không thể thanh toán');
});

test('product structured data stops advertising availability while listing-only mode is active', function () {
    $product = listingModeProduct();

    // JSON_encode escapes the slashes, so the page carries `https:\/\/schema.org\/...`.
    $inStock = 'https:\/\/schema.org\/InStock';
    $inStoreOnly = 'https:\/\/schema.org\/InStoreOnly';

    // CONTROL: with purchasing on, an in-stock product is still InStock.
    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee($inStock, false)
        ->assertDontSee($inStoreOnly, false);

    setListingOnlyMode();

    // Stock is unchanged — only the checkout went away — so OutOfStock would be
    // false. InStoreOnly is the "not orderable online" signal search engines read.
    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee($inStoreOnly, false)
        ->assertDontSee('"availability":"'.$inStock.'"', false);
});

test('purchase-assuming chrome is withdrawn from the product and cart pages', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $product = listingModeProduct();
    $variant = $product->variants->first();
    app(CartService::class)->addItem($buyer, $variant, 1);

    // CONTROL: with purchasing on, the stepper and the checkout-shipping line are both there.
    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertSee('Increase quantity')
        ->assertSee('Shipping calculated at checkout');

    Livewire::actingAs($buyer)->test(CartPage::class)
        ->assertSee('Shipping calculated at checkout');

    setListingOnlyMode();

    // The stepper has nothing to add a quantity to; the shipping line points at a
    // checkout that cannot be reached. The stock badge and "Ships from" stay —
    // both are catalogue facts that listing-only mode does not change.
    $this->get(route('product.show', $product->slug))
        ->assertOk()
        ->assertDontSee('Increase quantity')
        ->assertDontSee('Shipping calculated at checkout')
        ->assertSee('Ships from');

    // Same line of copy, same defect, one page over: it sat directly above the
    // disabled "Checkout unavailable" button.
    Livewire::actingAs($buyer)->test(CartPage::class)
        ->assertSee('Checkout unavailable')
        ->assertDontSee('Shipping calculated at checkout');
});

test('the coin expiry notice stops telling buyers to spend at a checkout that is gone', function () {
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    app(CoinService::class)->credit($buyer, 500, CoinTransactionType::Earn, expiryDays: 21);

    // CONTROL: with purchasing on, the notice keeps its instruction.
    Livewire::actingAs($buyer)->test(Coins::class)
        ->assertSee('spend them at checkout');

    setListingOnlyMode();

    // The expiry is still real, so the warning stays — only the instruction goes.
    Livewire::actingAs($buyer)->test(Coins::class)
        ->assertDontSee('spend them at checkout')
        ->assertSee('Some coins expire');
});

test('the shortened coin expiry notice is translated in Malay and Vietnamese', function () {
    app()->setLocale('ms');
    expect(__('Some coins expire :when.', ['when' => 'esok']))->toBe('Sebahagian syiling luput esok.');

    app()->setLocale('vi');
    expect(__('Some coins expire :when.', ['when' => 'ngày mai']))->toBe('Một số xu sẽ hết hạn ngày mai.');
});
