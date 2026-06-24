<?php

use App\Enums\CoinTransactionType;
use App\Livewire\Storefront\Account\Coins;
use App\Models\User;
use App\Services\CoinService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['coins.enabled' => true]);
});

function coinsHubBuyer(): User
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');

    return $buyer;
}

test('the coins hub shows the wallet balance', function () {
    $buyer = coinsHubBuyer();
    app(CoinService::class)->credit($buyer, 250, CoinTransactionType::Earn);

    Livewire::actingAs($buyer)
        ->test(Coins::class)
        ->assertOk()
        ->assertSee('250');
});

test('checking in awards coins and disables a second check-in', function () {
    $buyer = coinsHubBuyer();

    Livewire::actingAs($buyer)
        ->test(Coins::class)
        ->call('checkIn')
        ->assertSee(__('Checked in — see you tomorrow!'));

    expect(app(CoinService::class)->balance($buyer))->toBeGreaterThan(0);
});

test('spinning the wheel grants a reward and then locks for the day', function () {
    $buyer = coinsHubBuyer();

    Livewire::actingAs($buyer)
        ->test(Coins::class)
        ->call('spin')
        ->assertSet('reward', fn ($value) => is_string($value) && $value !== '')
        ->assertSee(__('Spun today — come back tomorrow!'));
});

test('the hub 404s when coins are disabled', function () {
    config(['coins.enabled' => false]);

    Livewire::actingAs(coinsHubBuyer())
        ->test(Coins::class)
        ->assertStatus(404);
});
