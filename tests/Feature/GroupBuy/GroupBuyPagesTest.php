<?php

use App\Livewire\Seller\GroupBuys\Form;
use App\Models\GroupBuy;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\DisplayTime;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seller = User::factory()->create();
    $this->seller->assignRole('seller');
    $this->store = Store::factory()->approved()->create(['user_id' => $this->seller->id]);
    $this->actingAs($this->seller);
});

test('group buy list opens a separate form and save returns the unchanged payload to the list', function () {
    $this->get(route('seller.group-buys.index'))->assertOk()
        ->assertSee(route('seller.group-buys.create'), false)->assertDontSee('wire:submit="save"', false);
    $this->get(route('seller.group-buys.create'))->assertOk()
        ->assertSee('wire:submit="save"', false)->assertDontSee(__('No group-buy deals yet.'));
    $this->travelTo(now()->startOfMinute());
    $product = Product::factory()->create(['store_id' => $this->store->id]);
    $variant = $product->variants->first();
    $variant->update(['price_sen' => 10000, 'sale_price_sen' => null]);
    $ends = DisplayTime::now()->addWeek()->format('Y-m-d\TH:i');
    Livewire::test(Form::class)->set('productId', $product->id)->set('variantId', $variant->id)
        ->set('groupPrice', '60.25')->set('targetSize', 3)->set('windowHours', 48)->set('endsAt', $ends)
        ->call('save')->assertHasNoErrors()->assertRedirect(route('seller.group-buys.index'));
    expect(GroupBuy::sole()->getRawOriginal())->toMatchArray([
        'store_id' => $this->store->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id,
        'group_price_sen' => 6025, 'target_size' => 3, 'team_window_hours' => 48, 'status' => 'active',
        'starts_at' => now()->toDateTimeString(), 'ends_at' => DisplayTime::parse($ends)->toDateTimeString(),
    ]);
    $this->get(route('seller.group-buys.index'))->assertSee($product->getTranslation('name', 'en'))->assertSee(__('Group-buy deal created.'));
});

test('the group buy form refuses products and variants from another store', function () {
    $product = Product::factory()->create();
    Livewire::test(Form::class)->set('productId', $product->id)->set('variantId', $product->variants->first()->id)
        ->set('groupPrice', '1.00')->call('save')->assertHasErrors('productId');
    expect(GroupBuy::count())->toBe(0);
});

test('group buy create respects the feature switch', function () {
    config(['groupbuy.enabled' => false]);
    $this->get(route('seller.group-buys.create'))->assertNotFound();
});
