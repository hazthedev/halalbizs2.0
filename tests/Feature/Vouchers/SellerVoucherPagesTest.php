<?php

use App\Enums\VoucherScope;
use App\Livewire\Seller\Vouchers\Form;
use App\Models\Store;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seller = User::factory()->create();
    $this->seller->assignRole('seller');
    $this->store = Store::factory()->approved()->create(['user_id' => $this->seller->id]);
    $this->actingAs($this->seller);
});

test('shop voucher list links to separate create and edit pages', function () {
    $voucher = pageShopVoucher($this->store, ['code' => 'LIST-ONLY']);
    $this->get(route('seller.vouchers.index'))->assertOk()
        ->assertSee(route('seller.vouchers.create'), false)
        ->assertSee(route('seller.vouchers.edit', $voucher), false)
        ->assertDontSee('wire:submit="save"', false);
    $this->get(route('seller.vouchers.create'))->assertOk()->assertSee('wire:submit="save"', false)->assertDontSee('LIST-ONLY');
    $this->get(route('seller.vouchers.edit', $voucher))->assertOk()->assertDontSee('<table', false);
});

test('shop voucher page preserves the complete create and edit payload', function () {
    $this->travelTo(now()->startOfMinute());
    $form = Livewire::test(Form::class)
        ->set('code', 'page10')->set('value', '12.34')->set('minSpend', '50.00')
        ->set('quota', '20')->set('perUserLimit', '2')->set('isActive', false)
        ->set('startsAt', '2026-11-18T10:00')->set('endsAt', '2026-11-25T18:00')
        ->call('save')->assertHasNoErrors()->assertRedirect(route('seller.vouchers.index'));
    $voucher = Voucher::sole();
    $expected = [
        'scope' => 'shop', 'store_id' => $this->store->id, 'code' => 'PAGE10', 'type' => 'fixed',
        'value_sen' => 1234, 'percent' => null, 'max_discount_sen' => null, 'min_spend_sen' => 5000,
        'quota' => 20, 'per_user_limit' => 2, 'is_active' => 0, 'used_count' => 0,
        'starts_at' => '2026-11-18 02:00:00', 'ends_at' => '2026-11-25 10:00:00',
    ];
    expect($voucher->getRawOriginal())->toMatchArray($expected);
    Livewire::test(Form::class, ['voucher' => $voucher])->call('save')
        ->assertHasNoErrors()->assertRedirect(route('seller.vouchers.index'));
    expect($voucher->fresh()->getRawOriginal())->toMatchArray($expected);
    $this->get(route('seller.vouchers.index'))->assertSee('PAGE10')->assertSee(__('Voucher updated'));
});

test('shop voucher edit rejects a foreign store or platform voucher', function () {
    $foreign = pageShopVoucher(Store::factory()->approved()->create());
    $platform = pageShopVoucher($this->store, ['scope' => VoucherScope::Platform, 'store_id' => null]);
    $this->get(route('seller.vouchers.edit', $foreign))->assertNotFound();
    $this->get(route('seller.vouchers.edit', $platform))->assertNotFound();
});

function pageShopVoucher(Store $store, array $attributes = []): Voucher
{
    return Voucher::create(array_merge([
        'scope' => VoucherScope::Shop, 'store_id' => $store->id, 'code' => 'PAGE-VOUCHER',
        'type' => 'fixed', 'value_sen' => 1000, 'min_spend_sen' => 0, 'per_user_limit' => 1,
        'starts_at' => now(), 'ends_at' => now()->addWeek(), 'is_active' => true,
    ], $attributes));
}
