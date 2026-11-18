<?php

use App\Enums\SubscriptionInterval;
use App\Models\Address;
use App\Models\AffiliateReferral;
use App\Models\CoinWallet;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\SubscriptionService;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

function assertLoadingAction(string $html, string $target): void
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $found = false;
    foreach ($dom->getElementsByTagName('button') as $button) {
        if ($button->getAttribute('wire:target') !== $target) {
            continue;
        }
        $found = true;
        expect($button->getAttribute('wire:loading.attr'))->toBe('disabled');
        expect($dom->saveHTML($button))->toContain('wire:loading.inline-flex', 'motion-safe:animate-spin');
    }
    expect($found)->toBeTrue("Missing loading button for {$target}");
}

beforeEach(fn () => $this->seed(RoleSeeder::class));

it('guards withdrawal and both admin payout forms', function () {
    $user = User::factory()->create();
    $affiliate = app(AffiliateService::class)->enroll($user);
    AffiliateReferral::create([
        'affiliate_id' => $affiliate->id, 'sub_order_id' => SubOrder::factory()->create()->id,
        'buyer_id' => User::factory()->create()->id, 'items_subtotal_sen' => 200000,
        'commission_sen' => 10000, 'status' => App\Enums\AffiliateReferralStatus::Confirmed,
    ]);
    $page = Livewire::actingAs($user)->test(App\Livewire\Storefront\Account\Affiliate::class);
    assertLoadingAction($page->html(), 'requestPayout');
    expect($page->html())->toContain("\$wire.entangle('showWithdraw')", 'x-show="showWithdraw"', 'x-on:click="showWithdraw = ! showWithdraw"')
        ->not->toContain('wire:click="$toggle(\'showWithdraw\'');
    $payout = app(AffiliateService::class)->requestPayout($affiliate, 5000, ['details' => 'Test bank']);
    $admin = makeAdmin(User::factory()->create());
    $page = Livewire::actingAs($admin)->test(App\Livewire\Admin\Affiliates\Index::class);
    assertLoadingAction($page->call('openPay', $payout->id)->html(), 'markPaid');
    assertLoadingAction($page->call('openReject', $payout->id)->html(), 'reject');
});

it('guards coin adjustment', function () {
    $user = User::factory()->create();
    CoinWallet::create(['user_id' => $user->id, 'balance' => 100]);
    $admin = makeAdmin(User::factory()->create());
    $page = Livewire::actingAs($admin)->test(App\Livewire\Admin\Coins\Index::class)->call('openAdjust', $user->id);
    assertLoadingAction($page->html(), 'adjust');
});

it('guards both flash sale forms', function () {
    $admin = makeAdmin(User::factory()->create());
    $sale = App\Models\FlashSale::create(['title' => 'Test', 'starts_at' => now(), 'ends_at' => now()->addDay()]);
    $page = Livewire::actingAs($admin)->test(App\Livewire\Admin\Content\FlashSales::class)->call('openAddItem', $sale->id);
    assertLoadingAction($page->html(), 'createSale');
    assertLoadingAction($page->html(), 'addItem');
});

it('guards live session creation and adding products', function () {
    config(['live.enabled' => true]);
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    Store::factory()->approved()->create(['user_id' => $seller->id]);
    $page = Livewire::actingAs($seller)->test(App\Livewire\Seller\LiveSessions::class)->call('create');
    assertLoadingAction($page->html(), 'save');
    $page->set('title', 'Loading test')->call('save');
    assertLoadingAction($page->html(), 'addProduct');
});

it('guards bulk stock actions', function () {
    $seller = User::factory()->create();
    $seller->assignRole('seller');
    $store = Store::factory()->approved()->create(['user_id' => $seller->id]);
    $product = Product::factory()->create(['store_id' => $store->id]);
    $page = Livewire::actingAs($seller)->test(App\Livewire\Seller\Products\Index::class)->set('selected', [$product->id]);
    assertLoadingAction($page->html(), 'bulkDelist');
    assertLoadingAction($page->html(), 'bulkDelete');
});

it('guards subscription pause resume and cancel', function () {
    config(['subscriptions.enabled' => true]);
    $buyer = User::factory()->create();
    $address = Address::factory()->create(['user_id' => $buyer->id]);
    $product = Product::factory()->create(['cod_enabled' => true]);
    $sub = app(SubscriptionService::class)->subscribe($buyer, $product->variants->first(), $address, SubscriptionInterval::Monthly);
    $page = Livewire::actingAs($buyer)->test(App\Livewire\Storefront\Account\Subscriptions::class);
    assertLoadingAction($page->html(), 'pause');
    assertLoadingAction($page->html(), 'cancel');
    $page->call('pause', $sub->id);
    assertLoadingAction($page->html(), 'resume');
});

it('renders the delete account panel before opening it in Alpine', function () {
    $user = User::factory()->create();
    $html = Livewire::actingAs($user)->test(App\Livewire\Storefront\Account\Profile::class)->html();
    expect($html)->toContain("\$wire.entangle('showDeleteModal')", 'x-show="showDeleteModal"', 'x-on:click="showDeleteModal = true"', 'delete-account-title')
        ->not->toContain('wire:click="$set(\'showDeleteModal\'');
});


it('renders checkout pickers before opening them locally', function () {
    $buyer = User::factory()->create();
    Address::factory()->default()->create(['user_id' => $buyer->id]);
    $product = Product::factory()->create();
    $this->actingAs($buyer);
    app(App\Services\CartService::class)->addItem($buyer, $product->variants->first(), 1);
    $html = Livewire::test(App\Livewire\Storefront\Checkout::class)->html();
    foreach (['changingAddress', 'voucherPanelOpen'] as $panel) {
        expect($html)->toContain("\$wire.entangle('{$panel}')", 'x-show="'.$panel.'"', 'x-on:click="'.$panel.' = ! '.$panel.'"');
    }
    expect($html)->toContain('checkout-address', 'Platform vouchers');
});


it('keeps the order panels server side because they load reasons, with a spinner', function () {
    $order = SubOrder::factory()->create(['status' => App\Enums\SubOrderStatus::Confirmed]);
    $page = Livewire::actingAs($order->order->user)->test(App\Livewire\Storefront\Account\OrderDetail::class, ['subOrder' => $order]);
    assertLoadingAction($page->html(), 'cancelling');
    assertLoadingAction($page->set('cancelling', true)->html(), 'cancelling');
});
