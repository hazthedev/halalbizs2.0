<?php

use App\Enums\LiveSessionStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreStatus;
use App\Livewire\Seller\LiveSessions;
use App\Livewire\Storefront\Live\Room;
use App\Models\LiveSession;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\LiveSessionService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['live.enabled' => true]);
});

function liveStoreSeller(): array
{
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    $store = Store::factory()->create(['user_id' => $seller->id, 'status' => StoreStatus::Approved]);

    return [$seller, $store];
}

test('a session is created with a unique slug and scheduled status', function () {
    [, $store] = liveStoreSeller();

    $session = app(LiveSessionService::class)->create($store, ['title' => 'Ramadan Bazaar']);

    expect($session->status)->toBe(LiveSessionStatus::Scheduled)
        ->and($session->slug)->toBe('ramadan-bazaar');
});

test('going live stamps started_at and ending stamps ended_at', function () {
    [, $store] = liveStoreSeller();
    $svc = app(LiveSessionService::class);
    $session = $svc->create($store, ['title' => 'Eid Live']);

    $svc->goLive($session);
    expect($session->fresh()->status)->toBe(LiveSessionStatus::Live)
        ->and($session->fresh()->started_at)->not->toBeNull();

    $svc->end($session);
    expect($session->fresh()->status)->toBe(LiveSessionStatus::Ended)
        ->and($session->fresh()->ended_at)->not->toBeNull();
});

test('an ended session cannot be reopened', function () {
    [, $store] = liveStoreSeller();
    $svc = app(LiveSessionService::class);
    $session = $svc->create($store, ['title' => 'Done']);
    $svc->end($session);

    $svc->goLive($session);

    expect($session->fresh()->status)->toBe(LiveSessionStatus::Ended);
});

test('only rail products can be featured, and only own-store products join the rail', function () {
    [, $store] = liveStoreSeller();
    $svc = app(LiveSessionService::class);
    $session = $svc->create($store, ['title' => 'Rail']);

    $mine = Product::factory()->create(['store_id' => $store->id]);
    $other = Product::factory()->create(); // different store

    $svc->addProduct($session, $other);
    expect($session->products()->count())->toBe(0); // foreign product rejected

    $svc->feature($session, $mine);
    expect($session->fresh()->featured_product_id)->toBeNull(); // not on the rail yet

    $svc->addProduct($session, $mine);
    $svc->feature($session, $mine);
    expect($session->fresh()->featured_product_id)->toBe($mine->id);

    $svc->removeProduct($session, $mine);
    expect($session->fresh()->featured_product_id)->toBeNull()
        ->and($session->products()->count())->toBe(0);
});

test('the just-sold feed shows masked buyers of paid orders only', function () {
    [, $store] = liveStoreSeller();
    $product = Product::factory()->create(['store_id' => $store->id, 'name' => ['en' => 'Kurma Premium', 'ms' => 'Kurma']]);
    $session = app(LiveSessionService::class)->create($store, ['title' => 'Sold']);
    $session->products()->attach($product->id, ['position' => 0]);
    $session->load('products');

    $buyer = User::factory()->create(['name' => 'Aisyah Rahman']);
    $order = Order::factory()->create(['user_id' => $buyer->id, 'payment_status' => PaymentStatus::Paid, 'paid_at' => now()]);
    $sub = SubOrder::factory()->create(['order_id' => $order->id, 'store_id' => $store->id]);
    $sub->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $product->variants->first()->id,
        'product_name' => 'Kurma Premium',
        'variant_label' => null,
        'unit_price_sen' => 1000, 'qty' => 1, 'line_total_sen' => 1000, 'tax_sen' => 0, 'tax_rate_bp' => 0,
    ]);

    $sold = app(LiveSessionService::class)->recentlySold($session);

    expect($sold)->toHaveCount(1)
        ->and($sold->first()['product'])->toBe('Kurma Premium')
        ->and($sold->first()['buyer'])->toBe('A***'); // masked
});

test('the live hub lists a live session', function () {
    [, $store] = liveStoreSeller();
    $session = app(LiveSessionService::class)->create($store, ['title' => 'Bazaar Stream']);
    app(LiveSessionService::class)->goLive($session);

    $this->get(route('live.index'))->assertOk()->assertSee('Bazaar Stream');
});

test('the live room renders and a buyer can add a rail product to cart', function () {
    [, $store] = liveStoreSeller();
    $product = Product::factory()->create(['store_id' => $store->id]);
    $session = app(LiveSessionService::class)->create($store, ['title' => 'Shop Live']);
    $session->products()->attach($product->id, ['position' => 0]);
    app(LiveSessionService::class)->goLive($session);

    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');

    Livewire::actingAs($buyer)
        ->test(Room::class, ['session' => $session])
        ->assertOk()
        ->assertSee('Shop Live')
        ->call('addToCart', $product->variants->first()->id);

    expect($buyer->fresh()->cart->items()->count())->toBe(1);
});

test('the seller studio builds a rail, features a product and goes live', function () {
    [$seller, $store] = liveStoreSeller();
    $product = Product::factory()->create(['store_id' => $store->id]);

    $component = Livewire::actingAs($seller)
        ->test(LiveSessions::class)
        ->call('create')
        ->set('title', 'Eid Live')
        ->call('save')
        ->assertHasNoErrors()
        ->set('addProductId', $product->id)
        ->call('addProduct');

    $session = LiveSession::where('store_id', $store->id)->firstOrFail();

    $component->call('feature', $session->id, $product->id)
        ->call('goLive', $session->id);

    $session->refresh();
    expect($session->status)->toBe(LiveSessionStatus::Live)
        ->and($session->featured_product_id)->toBe($product->id)
        ->and($session->products()->count())->toBe(1);
});

test('live pages 404 when the feature is disabled', function () {
    config(['live.enabled' => false]);

    $this->get(route('live.index'))->assertNotFound();
});
