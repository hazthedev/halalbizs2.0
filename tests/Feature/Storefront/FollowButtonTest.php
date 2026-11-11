<?php

use App\Livewire\Storefront\Account\FollowedShopsPage;
use App\Livewire\Storefront\FollowButton;
use App\Models\Store;
use App\Models\StoreFollow;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

it('shows Follow and the follower count to a guest, and sends a guest tap to login', function () {
    $store = Store::factory()->approved()->create();
    StoreFollow::create(['user_id' => User::factory()->create()->id, 'store_id' => $store->id]);

    Livewire::test(FollowButton::class, ['store' => $store])
        ->assertSee('Follow')->assertSee('1 follower')
        ->call('toggle')->assertRedirect(route('login'));

    expect(StoreFollow::count())->toBe(1);
});

it('toggles follow and unfollow for a buyer and updates the count', function () {
    $buyer = User::factory()->create();
    $store = Store::factory()->approved()->create();

    Livewire::actingAs($buyer)->test(FollowButton::class, ['store' => $store])
        ->assertSee('0 followers')
        ->call('toggle')->assertSee('Following')->assertSee('1 follower')
        ->call('toggle')->assertSee('0 followers');

    expect(StoreFollow::count())->toBe(0);
});

it('hides the button on the seller\'s own store and writes nothing', function () {
    $owner = User::factory()->create();
    $store = Store::factory()->approved()->create(['user_id' => $owner->id]);

    Livewire::actingAs($owner)->test(FollowButton::class, ['store' => $store])
        ->assertDontSee('wire:click="toggle"', escape: false)
        ->call('toggle');

    expect(StoreFollow::count())->toBe(0);
});

it('renders on the store page and lists followed shops in the account area', function () {
    $buyer = User::factory()->create();
    $store = Store::factory()->approved()->create(['name' => 'Kedai Pak Mat']);
    $other = Store::factory()->approved()->create(['name' => 'Kedai Lain']);
    StoreFollow::create(['user_id' => $buyer->id, 'store_id' => $store->id]);

    $this->get('/s/'.$store->slug)->assertOk()->assertSee('data-testid="follow-button"', escape: false);

    Livewire::actingAs($buyer)->test(FollowedShopsPage::class)
        ->assertSee('Kedai Pak Mat')->assertDontSee('Kedai Lain')
        ->call('unfollow', $store->id)->assertDontSee('Kedai Pak Mat')->assertSee('No followed shops yet');

    $this->actingAs($buyer)->get(route('account.following'))->assertOk();
    expect($other)->not->toBeNull();
});
