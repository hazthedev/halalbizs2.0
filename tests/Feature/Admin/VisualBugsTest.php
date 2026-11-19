<?php

use App\Livewire\Admin\Content\Vouchers\Form as VouchersForm;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Finance\Commission;
use App\Livewire\Admin\Localization\Index as LocalizationIndex;
use App\Livewire\Admin\Orders\Detail as OrderDetail;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreDocument;
use App\Models\SubOrder;
use App\Models\User;
use App\Enums\DocumentStatus;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

function bugsAdmin(): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->create(['two_factor_method' => 'email']);
    makeAdmin($user);

    return $user;
}

test('an order with no status history says so instead of an empty Timeline box', function () {
    $subOrder = SubOrder::factory()->create([
        'order_id' => Order::factory()->create()->id,
        'store_id' => Store::factory()->approved()->create()->id,
    ]);
    $subOrder->statusHistories()->delete();

    Livewire::actingAs(bugsAdmin())->test(OrderDetail::class, ['subOrder' => $subOrder])
        ->assertSee('No status changes yet.');
});

test('the admin login page has an admin header and no storefront CTA', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('HalalBizs Admin')
        ->assertDontSee('Start Selling')
        ->assertDontSee('Back to shopping');
});

test('a missing seller document gives the admin message, not the storefront 404', function () {
    $doc = StoreDocument::create(['store_id' => Store::factory()->create()->id, 'type' => 'ic', 'status' => DocumentStatus::Pending]);

    $this->actingAs(bugsAdmin())
        ->get(route('admin.sellers.documents.show', $doc))
        ->assertNotFound()
        ->assertSee('This record or its file was not found')
        ->assertDontSee('delisted');
});

test('the storefront 404 keeps its product-search copy', function () {
    $this->get('/no-such-page-here')->assertNotFound()->assertSee('delisted');
});

test('localization shows visible Rate and Margin % labels and no roadmap text', function () {
    $this->seed(CurrencySeeder::class);

    Livewire::actingAs(bugsAdmin())->test(LocalizationIndex::class)
        ->assertSeeHtml('>Rate</label>')
        ->assertSeeHtml('>Margin %</label>')
        ->assertDontSee('M8')
        ->assertDontSee('rates:sync');
});

test('the commission tester copy does not name the PHP class', function () {
    Livewire::actingAs(bugsAdmin())->test(Commission::class)
        ->assertDontSee('CommissionResolver');
});

test('the voucher Code font applies to the input, not the label wrapper', function () {
    Livewire::actingAs(bugsAdmin())->test(VouchersForm::class)
        ->assertDontSeeHtml('<div class="font-mono">')
        ->assertSeeHtml('font-mono');
});

test('the new buyers chart carries a fixed height instead of the library auto ratio', function () {
    Livewire::actingAs(bugsAdmin())->test(Dashboard::class)
        ->assertViewHas('buyersChart', fn (array $chart) => ($chart['options']['chart']['height'] ?? null) === 240);
});
