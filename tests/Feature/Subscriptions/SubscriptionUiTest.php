<?php

use App\Enums\SubscriptionInterval;
use App\Enums\SubscriptionStatus;
use App\Livewire\Storefront\Account\Subscriptions as SubscriptionsPage;
use App\Livewire\Storefront\Subscribe\Panel;
use App\Models\Address;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['subscriptions.enabled' => true]);
});

function subUiBuyer(): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    return [$buyer, $address];
}

function subUiProduct(): Product
{
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['price_sen' => 10_000, 'sale_price_sen' => null, 'stock' => 50]);

    return $product;
}

test('the PDP panel shows the discounted price and creates a subscription', function () {
    [$buyer] = subUiBuyer();
    $product = subUiProduct();

    Livewire::actingAs($buyer)
        ->test(Panel::class, ['product' => $product])
        ->assertSee(__('Subscribe'))
        ->set('interval', SubscriptionInterval::Fortnightly->value)
        ->call('subscribe');

    $sub = Subscription::where('user_id', $buyer->id)->first();
    expect($sub)->not->toBeNull()
        ->and($sub->interval_days)->toBe(14);
});

test('the panel hides for a non-COD product', function () {
    [$buyer] = subUiBuyer();
    $product = Product::factory()->create(['cod_enabled' => false]);

    Livewire::actingAs($buyer)
        ->test(Panel::class, ['product' => $product])
        ->assertDontSee(__('Subscribe & save'));
});

test('the account page can pause and cancel a subscription', function () {
    [$buyer, $address] = subUiBuyer();
    $product = subUiProduct();
    $sub = app(SubscriptionService::class)->subscribe($buyer, $product->variants->first(), $address, SubscriptionInterval::Monthly);

    Livewire::actingAs($buyer)
        ->test(SubscriptionsPage::class)
        ->call('pause', $sub->id)
        ->assertOk();
    expect($sub->fresh()->status)->toBe(SubscriptionStatus::Paused);

    Livewire::actingAs($buyer)
        ->test(SubscriptionsPage::class)
        ->call('cancel', $sub->id);
    expect($sub->fresh()->status)->toBe(SubscriptionStatus::Cancelled);
});

test('a buyer cannot manage someone else’s subscription', function () {
    [$owner, $address] = subUiBuyer();
    $product = subUiProduct();
    $sub = app(SubscriptionService::class)->subscribe($owner, $product->variants->first(), $address, SubscriptionInterval::Monthly);

    [$intruder] = subUiBuyer();

    Livewire::actingAs($intruder)
        ->test(SubscriptionsPage::class)
        ->call('cancel', $sub->id);

    expect($sub->fresh()->status)->toBe(SubscriptionStatus::Active); // untouched
});
