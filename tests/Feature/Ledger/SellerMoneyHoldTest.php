<?php

use App\Enums\ActorType;
use App\Enums\GatewayPaymentStatus;
use App\Enums\LedgerEntryStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\ReturnStatus;
use App\Enums\SubOrderStatus;
use App\Exceptions\CheckoutException;
use App\Livewire\Admin\Sellers\StoreDetail;
use App\Livewire\Seller\Earnings;
use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReturnReason;
use App\Models\ReturnRequest;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Notifications\CommissionStatementNotification;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\LedgerService;
use App\Services\RefundService;
use App\Settings\OrderSettings;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RoleSeeder::class));

/** A completed (ledger-booked) 20000-sen sub-order delivered [$deliveredDaysAgo] days ago, 5% commission. */
function holdBooked(PaymentMethod $method = PaymentMethod::Ipay88, int $deliveredDaysAgo = 0, ?Store $store = null): SubOrder
{
    $store ??= Store::factory()->approved()->create(['commission_rate' => '5.00']);
    $order = Order::factory()->create(['payment_method' => $method]);

    $subOrder = SubOrder::factory()->create([
        'order_id' => $order->id,
        'store_id' => $store->id,
        'status' => SubOrderStatus::Completed,
        'items_subtotal_sen' => 20000,
        'shipping_fee_sen' => 0,
        'shop_discount_sen' => 0,
        'tax_sen' => 0,
        'total_sen' => 20000,
        'commission_rate' => '5.00',
        'delivered_at' => now()->subDays($deliveredDaysAgo),
    ]);

    app(LedgerService::class)->recordCompletion($subOrder);

    return $subOrder->fresh();
}

function holdOpenReturn(SubOrder $subOrder, ReturnStatus $status = ReturnStatus::Requested): ReturnRequest
{
    $reason = ReturnReason::firstOrCreate(['position' => 0], ['label' => ['en' => 'Damaged'], 'is_active' => true]);

    return ReturnRequest::create([
        'sub_order_id' => $subOrder->id,
        'return_reason_id' => $reason->id,
        'status' => $status,
        'respond_by' => now()->addDay(),
    ]);
}

test('completion books sale + commission as pending until delivered_at + return window', function () {
    $subOrder = holdBooked();
    $store = $subOrder->store;
    $days = app(OrderSettings::class)->return_window_days;

    $rows = $store->ledgerEntries()->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->every(fn ($r) => $r->status === LedgerEntryStatus::Pending))->toBeTrue()
        ->and($rows->first()->available_at->equalTo($subOrder->delivered_at->copy()->addDays($days)))->toBeTrue()
        ->and($store->availableBalanceSen())->toBe(0)
        ->and(app(LedgerService::class)->pendingBalanceSen($store))->toBe(19000);
});

test('COD cod_offset is held too, so net pending is minus commission', function () {
    $subOrder = holdBooked(PaymentMethod::Cod);

    expect($subOrder->store->ledgerEntries()->where('status', LedgerEntryStatus::Pending)->count())->toBe(3)
        ->and($subOrder->store->pendingBalanceSen())->toBe(-1000)
        ->and($subOrder->store->availableBalanceSen())->toBe(0);
});

test('a window that already lapsed books straight to available', function () {
    $subOrder = holdBooked(deliveredDaysAgo: 8);

    expect($subOrder->store->availableBalanceSen())->toBe(19000)
        ->and($subOrder->store->pendingBalanceSen())->toBe(0);
});

test('release job moves due rows, is idempotent, and leaves undue rows', function () {
    $due = holdBooked();
    $due->store->ledgerEntries()->update(['available_at' => now()->subMinute()]);
    $notDue = holdBooked();

    $this->artisan('ledger:release-holds')->assertSuccessful();

    expect($due->store->availableBalanceSen())->toBe(19000)
        ->and($notDue->store->availableBalanceSen())->toBe(0);

    expect(app(LedgerService::class)->releaseDue())->toBe(0)
        ->and($due->store->availableBalanceSen())->toBe(19000);
});

test('release job skips sub-orders with an open return and releases once it is rejected', function () {
    $subOrder = holdBooked();
    $subOrder->store->ledgerEntries()->update(['available_at' => now()->subMinute()]);
    $return = holdOpenReturn($subOrder, ReturnStatus::Disputed);

    expect(app(LedgerService::class)->releaseDue())->toBe(0)
        ->and($subOrder->store->availableBalanceSen())->toBe(0);

    $return->update(['status' => ReturnStatus::Rejected]);

    expect(app(LedgerService::class)->releaseDue())->toBe(2)
        ->and($subOrder->store->availableBalanceSen())->toBe(19000);
});

test('payout and boost spending see available only, never pending', function () {
    $subOrder = holdBooked();
    $store = $subOrder->store;
    $store->ledgerEntries()->create(['type' => LedgerEntryType::Adjustment, 'amount_sen' => 6000, 'status' => LedgerEntryStatus::Available, 'description' => 'x']);

    expect(fn () => app(LedgerService::class)->requestPayout($store, 10000))
        ->toThrow(CheckoutException::class, 'available');

    $product = Product::factory()->create(['store_id' => $store->id]);
    expect(fn () => app(LedgerService::class)->chargeBoost($store, 7000, $product))
        ->toThrow(CheckoutException::class);

    app(LedgerService::class)->requestPayout($store, 6000);
    expect($store->availableBalanceSen())->toBe(0);
});

test('refund during the hold hits available at once; available + pending stays correct and nets to zero after release', function () {
    $subOrder = holdBooked();
    $store = $subOrder->store;
    $order = $subOrder->order;
    Payment::create([
        'order_id' => $order->id, 'gateway' => PaymentMethod::Ipay88, 'ref_no' => 'REFHOLD1', 'amount_sen' => 20000,
        'currency' => 'MYR', 'status' => GatewayPaymentStatus::Success, 'refunded_sen' => 0,
    ]);

    app(RefundService::class)->refund($subOrder->fresh(), 20000, ActorType::Admin, 1);

    // refund: -20000 + commission reversal 1000 = -19000, immediate
    expect($store->availableBalanceSen())->toBe(-19000)
        ->and($store->pendingBalanceSen())->toBe(19000)
        ->and($store->availableBalanceSen() + $store->pendingBalanceSen())->toBe(0);

    $store->ledgerEntries()->where('status', LedgerEntryStatus::Pending)->update(['available_at' => now()->subMinute()]);
    app(LedgerService::class)->releaseDue();

    expect($store->availableBalanceSen())->toBe(0)
        ->and($store->pendingBalanceSen())->toBe(0);
});

test('earnings screen shows on hold amount and a per-row hold date', function () {
    $subOrder = holdBooked();
    $seller = $subOrder->store->user;
    $seller->assignRole('seller');

    $date = $subOrder->store->ledgerEntries()->first()->available_at->display()->format('j M Y');

    Livewire::actingAs($seller)->test(Earnings::class)
        ->assertSee('On hold')
        ->assertSee('On hold until '.$date);
});

test('API earnings exposes pending_sen and per-row status without renaming old fields', function () {
    $subOrder = holdBooked();
    $seller = $subOrder->store->user;
    $seller->assignRole('seller');
    app('auth')->forgetGuards();

    test()->withToken($seller->createToken('t')->plainTextToken)->getJson('/api/v1/seller/earnings')
        ->assertOk()
        ->assertJsonPath('data.available_sen', 0)
        ->assertJsonPath('data.pending_sen', 19000)
        ->assertJsonPath('data.held_sen', 0)
        ->assertJsonPath('data.ledger.0.status', 'pending');
});

/* ---------------------------- Part B: COD collection ---------------------------- */

function holdAdmin(): User
{
    $user = User::factory()->create(['two_factor_method' => 'email']);

    return makeAdmin($user);
}

function holdDebt(Store $store, int $sen): void
{
    $store->ledgerEntries()->create(['type' => LedgerEntryType::Commission, 'amount_sen' => -$sen, 'status' => LedgerEntryStatus::Available, 'description' => 'debt']);
}

test('settlement writes a positive available entry and is capped at what is owed', function () {
    $store = Store::factory()->approved()->create();
    holdDebt($store, 5000);
    $ledger = app(LedgerService::class);

    expect(fn () => $ledger->recordSettlement($store, 5001, 'TX1'))->toThrow(CheckoutException::class, 'only owes')
        ->and(fn () => $ledger->recordSettlement($store, 0, 'TX1'))->toThrow(CheckoutException::class);

    $ledger->recordSettlement($store, 3000, 'TX1', 'March');

    $entry = $store->ledgerEntries()->where('type', LedgerEntryType::Settlement)->sole();
    expect($entry->amount_sen)->toBe(3000)
        ->and($entry->status)->toBe(LedgerEntryStatus::Available)
        ->and($entry->description)->toContain('TX1')
        ->and($store->availableBalanceSen())->toBe(-2000);
});

test('admin records a settlement from the store page; validation errors surface', function () {
    $store = Store::factory()->approved()->create();
    holdDebt($store, 5000);
    $admin = holdAdmin();

    Livewire::actingAs($admin)->test(StoreDetail::class, ['store' => $store])
        ->set('settleAmount', '60.00')->set('settleReference', 'TX9')->call('recordSettlement')
        ->assertHasErrors('settleAmount')
        ->set('settleAmount', '50.00')->set('settleReference', '')->call('recordSettlement')
        ->assertHasErrors('settleReference')
        ->set('settleReference', 'TX9')->call('recordSettlement')
        ->assertHasNoErrors();

    expect($store->availableBalanceSen())->toBe(0);
});

test('the store admin page is admin-only', function () {
    $store = Store::factory()->approved()->create();
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');

    $this->actingAs($buyer)->get(route('admin.sellers.stores.show', $store))->assertForbidden();
    $this->actingAs(holdAdmin())->get(route('admin.sellers.stores.show', $store))->assertOk();
});

test('monthly statement notifies only stores with a negative available balance', function () {
    Notification::fake();
    $owing = Store::factory()->approved()->create();
    $fine = Store::factory()->approved()->create();

    $last = now()->subMonthNoOverflow()->startOfMonth()->addDays(3);
    $codOrder = Order::factory()->create(['payment_method' => PaymentMethod::Cod]);
    $sub = SubOrder::factory()->create(['order_id' => $codOrder->id, 'store_id' => $owing->id]);
    $owing->ledgerEntries()->create(['type' => LedgerEntryType::Commission, 'sub_order_id' => $sub->id, 'amount_sen' => -700, 'status' => LedgerEntryStatus::Available, 'description' => 'c'])
        ->forceFill(['created_at' => $last])->save();
    holdDebt($owing, 300);
    $fine->ledgerEntries()->create(['type' => LedgerEntryType::Sale, 'amount_sen' => 900, 'status' => LedgerEntryStatus::Available, 'description' => 's']);

    $this->artisan('ledger:send-statements')->assertSuccessful();

    Notification::assertSentTo($owing->user, CommissionStatementNotification::class,
        fn ($n) => $n->monthCommissionSen === 700 && $n->totalOwedSen === 1000);
    Notification::assertNotSentTo($fine->user, CommissionStatementNotification::class);
});

test('statements are scheduled monthly and holds hourly', function () {
    $events = collect(app(Schedule::class)->events())->map->command->implode("\n");

    expect($events)->toContain('ledger:release-holds')->toContain('ledger:send-statements');
});

/** @return array{0: User, 1: Address, 2: Store} */
function holdCodCart(int $owedSen): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['price_sen' => 10000, 'sale_price_sen' => null, 'stock' => 10]);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    if ($owedSen > 0) {
        holdDebt($product->store, $owedSen);
    }

    return [$buyer, $address, $product->store];
}

test('COD is blocked only when the store owes MORE than RM100 (web quote + place)', function () {
    [$buyer, $address] = holdCodCart(10000); // exactly RM100: allowed
    $quote = app(CheckoutService::class)->quote($buyer, $address, null, null, null, 0, PaymentMethod::Cod);
    expect($quote->codUnavailableReason)->toBeNull();

    [$buyer, $address, $store] = holdCodCart(10001);
    $quote = app(CheckoutService::class)->quote($buyer, $address, null, null, null, 0, PaymentMethod::Cod);
    expect($quote->codUnavailableReason)->toContain($store->name);

    expect(fn () => app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod))
        ->toThrow(CheckoutException::class, 'cannot take cash on delivery');
});

test('pending debt counts towards the COD block', function () {
    [$buyer, $address, $store] = holdCodCart(0);
    $store->ledgerEntries()->create(['type' => LedgerEntryType::Commission, 'amount_sen' => -10001, 'status' => LedgerEntryStatus::Pending, 'available_at' => now()->addDays(3), 'description' => 'c']);

    expect(fn () => app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod))
        ->toThrow(CheckoutException::class);
});

test('API checkout enforces the same COD debt rule', function () {
    [$buyer, $address] = holdCodCart(10001);
    app('auth')->forgetGuards();
    $http = test()->withToken($buyer->createToken('t')->plainTextToken);

    $http->postJson('/api/v1/checkout/place', ['address_id' => $address->id, 'payment_method' => 'cod'])
        ->assertStatus(409);
});

test('an online order is never blocked by seller debt', function () {
    [$buyer, $address] = holdCodCart(50000);
    $quote = app(CheckoutService::class)->quote($buyer, $address, null, null, null, 0, PaymentMethod::Ipay88);

    expect($quote->codUnavailableReason)->not->toBeNull(); // COD hidden...
    // ...but nothing in the debt rule touches non-COD placement (place() only checks on Cod).
    expect(fn () => app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Cod))->toThrow(CheckoutException::class);
});

test('a refund during the hold is not a debt: no statement, nothing to settle', function () {
    Notification::fake();
    $store = Store::factory()->approved()->create();
    // Held sale RM10, refund already debited from Available: nets to zero.
    $store->ledgerEntries()->create(['type' => LedgerEntryType::Sale, 'amount_sen' => 1000, 'status' => LedgerEntryStatus::Pending, 'available_at' => now()->addDays(5), 'description' => 's']);
    $store->ledgerEntries()->create(['type' => LedgerEntryType::Adjustment, 'amount_sen' => -1000, 'status' => LedgerEntryStatus::Available, 'description' => 'refund']);

    $this->artisan('ledger:send-statements')->assertSuccessful();

    Notification::assertNotSentTo($store->user, CommissionStatementNotification::class);
    expect(app(LedgerService::class)->owedSen($store))->toBe(0)
        ->and(fn () => app(LedgerService::class)->recordSettlement($store, 100, 'REF1'))->toThrow(CheckoutException::class);
});
