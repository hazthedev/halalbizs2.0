<?php

use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Livewire\Seller\Vouchers\Index;
use App\Models\Store;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function sellerVouchersSeller(): User
{
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('seller');

    Store::factory()->approved()->create(['user_id' => $user->id]);

    return $user;
}

function sellerVouchersVoucher(Store $store, array $attributes = []): Voucher
{
    return Voucher::create(array_merge([
        'scope' => VoucherScope::Shop,
        'store_id' => $store->id,
        'code' => 'OWN10',
        'type' => VoucherType::Fixed,
        'value_sen' => 1000,
        'min_spend_sen' => 0,
        'per_user_limit' => 1,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
    ], $attributes));
}

test('the page lists only the current store\'s vouchers', function () {
    $seller = sellerVouchersSeller();

    sellerVouchersVoucher($seller->store, ['code' => 'MINE-10']);
    sellerVouchersVoucher(Store::factory()->approved()->create(), ['code' => 'THEIRS-10']);

    $this->actingAs($seller)
        ->get(route('seller.vouchers.index'))
        ->assertOk()
        ->assertSee('MINE-10')
        ->assertDontSee('THEIRS-10');
});

test('creating a voucher stores it shop-scoped with RM converted to sen', function () {
    $seller = sellerVouchersSeller();

    Livewire::actingAs($seller)
        ->test(Index::class)
        ->call('create')
        ->set('code', 'raya15')
        ->set('type', 'fixed')
        ->set('value', '15.50')
        ->set('minSpend', '50')
        ->set('quota', '20')
        ->set('perUserLimit', '2')
        ->set('startsAt', now()->format('Y-m-d\TH:i'))
        ->set('endsAt', now()->addWeek()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    $voucher = Voucher::sole();

    expect($voucher->scope)->toBe(VoucherScope::Shop)
        ->and($voucher->store_id)->toBe($seller->store->id)
        ->and($voucher->code)->toBe('RAYA15')
        ->and($voucher->value_sen)->toBe(1550)
        ->and($voucher->min_spend_sen)->toBe(5000)
        ->and($voucher->quota)->toBe(20)
        ->and($voucher->per_user_limit)->toBe(2);
});

test('codes are unique per store — but two stores may share a code', function () {
    $seller = sellerVouchersSeller();
    sellerVouchersVoucher($seller->store, ['code' => 'SHARED']);

    // Another shop already using the code is fine.
    sellerVouchersVoucher(Store::factory()->approved()->create(), ['code' => 'TWICE']);

    $component = Livewire::actingAs($seller)
        ->test(Index::class)
        ->call('create')
        ->set('type', 'fixed')
        ->set('value', '5.00')
        ->set('startsAt', now()->format('Y-m-d\TH:i'))
        ->set('endsAt', now()->addWeek()->format('Y-m-d\TH:i'));

    $component->set('code', 'shared')
        ->call('save')
        ->assertHasErrors(['code']);

    $component->set('code', 'TWICE')
        ->call('save')
        ->assertHasNoErrors();

    expect(Voucher::where('code', 'TWICE')->count())->toBe(2)
        ->and(Voucher::where('code', 'TWICE')->where('store_id', $seller->store->id)->exists())->toBeTrue();
});

test('a seller cannot edit, toggle, or delete another store\'s voucher', function () {
    $seller = sellerVouchersSeller();
    $foreign = sellerVouchersVoucher(Store::factory()->approved()->create(), ['code' => 'FOREIGN']);

    expect(fn () => Livewire::actingAs($seller)->test(Index::class)->call('edit', $foreign->id))
        ->toThrow(ModelNotFoundException::class);

    expect(fn () => Livewire::actingAs($seller)->test(Index::class)->call('toggleActive', $foreign->id))
        ->toThrow(ModelNotFoundException::class);

    expect(fn () => Livewire::actingAs($seller)->test(Index::class)->call('delete', $foreign->id))
        ->toThrow(ModelNotFoundException::class);

    expect($foreign->fresh())->not->toBeNull();
});

test('a used voucher cannot be deleted — deactivate instead', function () {
    $seller = sellerVouchersSeller();
    $voucher = sellerVouchersVoucher($seller->store, ['used_count' => 3]);

    Livewire::actingAs($seller)
        ->test(Index::class)
        ->call('delete', $voucher->id)
        ->assertDispatched('toast', type: 'error');

    expect($voucher->fresh())->not->toBeNull();
});

test('toggleActive flips the voucher state', function () {
    $seller = sellerVouchersSeller();
    $voucher = sellerVouchersVoucher($seller->store);

    Livewire::actingAs($seller)
        ->test(Index::class)
        ->call('toggleActive', $voucher->id);

    expect($voucher->fresh()->is_active)->toBeFalse();
});

test('editing keeps the voucher in the same store and updates fields', function () {
    $seller = sellerVouchersSeller();
    $voucher = sellerVouchersVoucher($seller->store, ['code' => 'EDITME', 'value_sen' => 1000]);

    Livewire::actingAs($seller)
        ->test(Index::class)
        ->call('edit', $voucher->id)
        ->assertSet('code', 'EDITME')
        ->assertSet('value', '10.00')
        ->set('value', '12.34')
        ->call('save')
        ->assertHasNoErrors();

    expect($voucher->fresh()->value_sen)->toBe(1234)
        ->and($voucher->fresh()->store_id)->toBe($seller->store->id);
});
