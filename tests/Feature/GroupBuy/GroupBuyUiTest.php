<?php

use App\Enums\GroupBuyStatus;
use App\Livewire\Seller\GroupBuys;
use App\Livewire\Storefront\GroupBuy\Panel;
use App\Livewire\Storefront\GroupBuy\Team;
use App\Models\GroupBuy;
use App\Models\GroupBuyTeam;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\GroupBuyService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['groupbuy.enabled' => true]);
});

function gbUiDeal(int $groupPriceSen = 6000, int $target = 2): GroupBuy
{
    $product = Product::factory()->create();
    $product->variants->first()->update(['price_sen' => 10_000, 'sale_price_sen' => null]);

    return GroupBuy::create([
        'store_id' => $product->store_id,
        'product_id' => $product->id,
        'product_variant_id' => $product->variants->first()->id,
        'group_price_sen' => $groupPriceSen,
        'target_size' => $target,
        'team_window_hours' => 24,
        'status' => GroupBuyStatus::Active,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addWeek(),
    ]);
}

test('the PDP panel shows a live deal', function () {
    $deal = gbUiDeal();

    Livewire::test(Panel::class, ['product' => $deal->product])
        ->assertSee(__('Start a group'))
        ->assertSee('RM 60.00');
});

test('starting a group from the panel creates a team and redirects', function () {
    $deal = gbUiDeal();
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');

    Livewire::actingAs($buyer)
        ->test(Panel::class, ['product' => $deal->product])
        ->call('start', $deal->id)
        ->assertRedirect();

    expect(GroupBuyTeam::where('group_buy_id', $deal->id)->count())->toBe(1);
});

test('the team page lets a shopper join', function () {
    $deal = gbUiDeal(6000, 3);
    $team = app(GroupBuyService::class)->startTeam(User::factory()->create(), $deal);

    $joiner = User::factory()->create();
    $joiner->assignRole('buyer');

    Livewire::actingAs($joiner)
        ->test(Team::class, ['team' => $team])
        ->call('join');

    expect($team->fresh()->members()->count())->toBe(2);
});

test('a seller creates a group-buy deal below the current price', function () {
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    $store = Store::factory()->create(['user_id' => $seller->id]);
    $product = Product::factory()->create(['store_id' => $store->id]);
    $product->variants->first()->update(['price_sen' => 10_000, 'sale_price_sen' => null]);

    Livewire::actingAs($seller)
        ->test(GroupBuys::class)
        ->call('create')
        ->set('productId', $product->id)
        ->set('variantId', $product->variants->first()->id)
        ->set('groupPrice', '60.00')
        ->set('targetSize', 2)
        ->set('windowHours', 24)
        ->set('endsAt', now()->addWeek()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    expect(GroupBuy::where('store_id', $store->id)->count())->toBe(1);
});

test('the group price must be below the current price', function () {
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    $store = Store::factory()->create(['user_id' => $seller->id]);
    $product = Product::factory()->create(['store_id' => $store->id]);
    $product->variants->first()->update(['price_sen' => 10_000, 'sale_price_sen' => null]);

    Livewire::actingAs($seller)
        ->test(GroupBuys::class)
        ->call('create')
        ->set('productId', $product->id)
        ->set('variantId', $product->variants->first()->id)
        ->set('groupPrice', '200.00') // above the RM100 price
        ->set('targetSize', 2)
        ->set('endsAt', now()->addWeek()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasErrors('groupPrice');
});
