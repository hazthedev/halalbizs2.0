<?php

use App\Enums\GroupBuyStatus;
use App\Enums\LedgerEntryStatus;
use App\Enums\LedgerEntryType;
use App\Livewire\Seller\Earnings;
use App\Livewire\Seller\GroupBuys;
use App\Livewire\Seller\Vouchers\Index as Vouchers;
use App\Models\GroupBuy;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

// HB-G152: group buys, vouchers and earnings use the standard seller table.

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['groupbuy.enabled' => true]);
    $this->seller = User::factory()->create();
    $this->seller->assignRole('seller');
    $this->store = Store::factory()->approved()->create(['user_id' => $this->seller->id]);
    $this->actingAs($this->seller);
});

function stdDeal(Store $store, int $price): GroupBuy
{
    $product = Product::factory()->create(['store_id' => $store->id]);

    return GroupBuy::create([
        'store_id' => $store->id, 'product_id' => $product->id,
        'product_variant_id' => $product->variants->first()->id,
        'group_price_sen' => $price, 'target_size' => 3, 'team_window_hours' => 24,
        'status' => GroupBuyStatus::Active, 'starts_at' => now()->subHour(), 'ends_at' => now()->addWeek(),
    ]);
}

function stdVoucher(Store $store, string $code, array $extra = []): Voucher
{
    return Voucher::create($extra + [
        'scope' => 'shop', 'store_id' => $store->id, 'code' => $code, 'type' => 'fixed', 'value_sen' => 100,
        'min_spend_sen' => 0, 'per_user_limit' => 1, 'starts_at' => now(), 'ends_at' => now()->addWeek(), 'is_active' => true,
    ]);
}

function stdAssertTable($html, array $heads): void
{
    expect($html)->toContain('<thead')->toContain('aria-sort=')->toContain('wire:click="sortBy(')->toContain('wire:key=')->toContain('Showing 1 to');
    foreach ($heads as $head) {
        expect($html)->toContain($head);
    }
}

test('group buys is a standard sortable table with footer and end action', function () {
    $cheap = stdDeal($this->store, 5000);
    stdDeal($this->store, 9000);

    $c = Livewire::test(GroupBuys::class);
    stdAssertTable($c->html(), ['Product', 'Size', 'Team price', 'People needed', 'Teams', 'Status', 'Actions']);
    $c->call('sortBy', 'price')->assertSeeInOrder(['RM 50.00', 'RM 90.00'])
        ->call('sortBy', 'price')->assertSeeInOrder(['RM 90.00', 'RM 50.00'])
        ->call('sortBy', 'id; drop table')->assertSet('sort', 'price')
        ->call('end', $cheap->id);
    expect($cheap->fresh()->status)->toBe(GroupBuyStatus::Ended);
});

test('group buys empty state shows', function () {
    Livewire::test(GroupBuys::class)->assertSee('No group-buy deals yet');
});

test('vouchers is a standard sortable table with footer', function () {
    foreach ([['A1', 100], ['B2', 900]] as [$code, $sen]) {
        stdVoucher($this->store, $code, ['value_sen' => $sen]);
    }

    $c = Livewire::test(Vouchers::class);
    stdAssertTable($c->html(), ['Code', 'Type', 'Value', 'Min spend', 'Window', 'Used / quota', 'Active', 'Actions']);
    $c->call('sortBy', 'value')->call('sortBy', 'value')->assertSeeInOrder(['B2', 'A1'])
        ->call('sortBy', 'password')->assertSet('sort', 'value');
});

test('earnings ledger and payouts are standard tables with their own pages and sorts', function () {
    foreach (range(1, 21) as $i) {
        $this->store->ledgerEntries()->create([
            'type' => LedgerEntryType::Sale, 'amount_sen' => $i * 100,
            'status' => LedgerEntryStatus::Available, 'description' => "Entry {$i}",
        ]);
    }

    $c = Livewire::test(Earnings::class);
    stdAssertTable($c->html(), ['Date', 'Description', 'Amount', 'Showing 1 to 20 of 21']);
    expect($c->html())->toContain('No payouts yet');
    $c->call('sortBy', 'ledger:amount')->assertSet('ledgerSort', 'ledger:amount')->assertSet('payoutSort', '')
        ->call('sortBy', 'amount_sen; x')->assertSet('ledgerSort', 'ledger:amount')
        ->call('gotoPage', 2, 'ledgerPage')->assertSee('Showing 21 to 21 of 21');
});

test('group buys search and bulk end ignore a foreign store id', function () {
    $mine = stdDeal($this->store, 5000);
    $other = Store::factory()->approved()->create();
    $foreign = stdDeal($other, 6000);

    $c = Livewire::test(GroupBuys::class)->assertSee('type="checkbox"', false)
        ->set('search', 'zzz-nothing')->assertSee('No deals match')
        ->set('search', '')
        ->set('selected', [(string) $mine->id, (string) $foreign->id])->call('bulkEnd');
    expect($mine->fresh()->status)->toBe(GroupBuyStatus::Ended)
        ->and($foreign->fresh()->status)->toBe(GroupBuyStatus::Active);
});

test('vouchers search and bulk actions ignore a foreign store id', function () {
    $mk = fn ($store, $code, $extra = []) => stdVoucher($store, $code, $extra);
    $a = $mk($this->store, 'ALPHA');
    $b = $mk($this->store, 'BRAVO', ['used_count' => 2]);
    $foreign = $mk(Store::factory()->approved()->create(), 'FOREIGN');

    Livewire::test(Vouchers::class)->set('search', 'alp')->assertSee('ALPHA')->assertDontSee('BRAVO')
        ->set('search', '')->set('selected', [(string) $a->id, (string) $foreign->id])->call('bulkDisable');
    expect($a->fresh()->is_active)->toBeFalse()->and($foreign->fresh()->is_active)->toBeTrue();

    Livewire::test(Vouchers::class)->set('selected', [(string) $a->id, (string) $b->id, (string) $foreign->id])->call('bulkDelete');
    expect(Voucher::find($a->id))->toBeNull()->and(Voucher::find($b->id))->not->toBeNull()->and(Voucher::find($foreign->id))->not->toBeNull();
});

test('earnings searches ledger and payouts separately', function () {
    foreach (['Sale SO100', 'Commission SO100', 'Boost fee'] as $d) {
        $this->store->ledgerEntries()->create(['type' => LedgerEntryType::Sale, 'amount_sen' => 100, 'status' => LedgerEntryStatus::Available, 'description' => $d]);
    }

    Livewire::test(Earnings::class)->set('ledgerSearch', 'boost')->assertSee('Boost fee')->assertDontSee('Commission SO100')
        ->set('payoutSearch', 'zzz')->assertSee('Boost fee');
});
