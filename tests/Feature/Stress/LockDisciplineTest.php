<?php

use App\Console\Commands\ExpireUnpaidOrders;
use App\Enums\ActorType;
use App\Enums\CoinTransactionType;
use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\CoinService;
use App\Services\Ipay88Service;
use App\Services\LedgerService;
use App\Services\OrderService;
use App\Services\RefundService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

// Audit M-7 to M-11.
//
// ⚠ WHAT THESE TESTS ARE AND ARE NOT. Nothing here runs two connections at
// once, so none of it PROVES the absence of a race — that needs a concurrency
// harness this suite does not have (M-32, knowingly left open). What they do is
// pin the structure a race depends on: which rows get locked, in what order,
// and whether the terminal write re-reads. Deleting a lock makes them fail,
// which is the property that was missing entirely.
//
// They also only mean something on MySQL: SQLiteGrammar::compileLock() returns
// an empty string, so `lockForUpdate()` compiles to nothing and every one of
// these would pass vacuously.
beforeEach(function () {
    expect(DB::connection()->getDriverName())->toBe('mysql');
});

/** @return array{0: list<string>, 1: mixed} */
function capturingQueries(Closure $work): array
{
    $sql = [];
    DB::listen(function ($query) use (&$sql) {
        $sql[] = $query->sql;
    });

    $result = $work();

    return [$sql, $result];
}

function lockedTables(array $sql): array
{
    return collect($sql)
        ->filter(fn (string $q) => str_contains(strtolower($q), 'for update'))
        ->map(function (string $q) {
            preg_match('/from\s+`?(\w+)`?/i', $q, $m);

            return $m[1] ?? $q;
        })
        ->values()
        ->all();
}

// ── M-7 · the deadlock pair ──────────────────────────────────────────────
test('coin expiry locks the wallet before the lot, like every other path', function () {
    $user = User::factory()->create();
    $wallet = $user->coinWallet()->create(['balance' => 100]);
    $wallet->transactions()->create([
        'type' => CoinTransactionType::Earn,
        'amount' => 100,
        'remaining' => 100,
        'description' => 'seed',
        'expires_at' => now()->subDay(),
    ]);

    [$sql] = capturingQueries(fn () => app(CoinService::class)->expireDue());

    $locks = lockedTables($sql);

    // Wallet FIRST. Reversed, an expiry sweep and a checkout redemption each
    // hold what the other waits for.
    expect($locks)->toContain('coin_wallets')
        ->and(array_search('coin_wallets', $locks, true))
        ->toBeLessThan(array_search('coin_transactions', $locks, true));
});

// ── M-8 · the asymmetric store lock ──────────────────────────────────────
test('a negative ledger adjustment locks the store row', function () {
    $store = Store::factory()->approved()->create();

    [$sql] = capturingQueries(fn () => app(LedgerService::class)->adjustment($store, -5000, 'Refund test'));

    // requestPayout and chargeBoost both locked the store before reading the
    // derived balance; the writer that REDUCES it did not.
    expect(lockedTables($sql))->toContain('stores');
});

// ── M-9 · COD settlement asked an unlocked question about siblings ───────
test('COD delivery locks the parent order before settling it', function () {
    $order = Order::factory()->create([
        'payment_method' => PaymentMethod::Cod,
        'payment_status' => PaymentStatus::Pending,
    ]);
    $store = Store::factory()->approved()->create();
    Product::factory()->create(['store_id' => $store->id]);
    $subOrder = SubOrder::factory()->status(SubOrderStatus::Shipped)->create([
        'order_id' => $order->id, 'store_id' => $store->id,
        'items_subtotal_sen' => 5000, 'shipping_fee_sen' => 0, 'shop_discount_sen' => 0,
        'total_sen' => 5000, 'commission_rate' => '5.00',
    ]);

    [$sql] = capturingQueries(fn () => app(OrderService::class)->markDelivered($subOrder, ActorType::Seller, null));

    expect(lockedTables($sql))->toContain('orders')
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

// ── M-10 · the real race, and this one IS simulated ──────────────────────
test('an order that settles during the requery is not expired underneath it', function () {
    $order = Order::factory()->create([
        'payment_method' => PaymentMethod::Ipay88,
        'payment_status' => PaymentStatus::Pending,
        'expires_at' => now()->subMinute(),
    ]);
    $store = Store::factory()->approved()->create();
    Product::factory()->create(['store_id' => $store->id]);
    SubOrder::factory()->status(SubOrderStatus::PendingPayment)->create([
        'order_id' => $order->id, 'store_id' => $store->id,
        'items_subtotal_sen' => 5000, 'shipping_fee_sen' => 0, 'shop_discount_sen' => 0,
        'total_sen' => 5000, 'commission_rate' => '5.00',
    ]);
    Payment::create([
        'order_id' => $order->id, 'gateway' => PaymentMethod::Ipay88, 'ref_no' => $order->order_no,
        'amount_sen' => 5000, 'currency' => 'MYR', 'status' => GatewayPaymentStatus::Pending,
    ]);

    // The requery is the blocking window — up to 10 seconds of real time. A
    // gateway callback landing inside it is exactly the race, so settle the
    // order from inside the mocked call and return "not paid" afterwards.
    $this->mock(Ipay88Service::class, function ($mock) use ($order) {
        $mock->shouldReceive('requery')->andReturnUsing(function () use ($order) {
            Order::whereKey($order->id)->update(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()]);

            return '99'; // gateway still says unpaid — the stale answer
        });
    });

    $this->artisan(ExpireUnpaidOrders::class)->assertSuccessful();

    // Before the re-read, the command wrote Expired over the callback's Paid
    // and restocked a paid order.
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->subOrders()->first()->status)->toBe(SubOrderStatus::PendingPayment);
});

// ── M-11 · the only mutating command that can outrun its own interval ────
test('the expiry sweep cannot overlap itself', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($e) => str_contains((string) $e->command, 'orders:expire-unpaid'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->withoutOverlapping)->toBeTrue();
});

// ── H-1 · the refund cap, and the lock that makes it mean anything ───────
//
// RefundService re-fetches the sub-order under lockForUpdate and recomputes the
// cap from THAT row. Before it did (audit H-1), two concurrent refunds both read
// refunded_sen = 0, both passed the cap and both paid out — the cap was read
// before the transaction even opened, so neither refund ever saw the other's
// write.
//
// Nothing in the suite held that in place. Every existing refund test refunds
// once, from an instance it just built, so deleting the lock AND the re-read
// left all of them green. The two below are the structural pair: the first
// fails if the lock goes, the second if the re-read goes. Neither alone covers
// both, because `->lockForUpdate()` and `->first()` can be removed separately.

/** A paid online sub-order sitting in Returned, with a payment row to track against. */
function lockDisciplineRefundable(int $totalSen = 5000): SubOrder
{
    $order = Order::factory()->create([
        'payment_method' => PaymentMethod::Ipay88,
        'payment_status' => PaymentStatus::Paid,
        'paid_at' => now(),
        'grand_total_sen' => $totalSen,
    ]);

    Payment::create([
        'order_id' => $order->id,
        'gateway' => PaymentMethod::Ipay88,
        'ref_no' => $order->order_no,
        'amount_sen' => $totalSen,
        'currency' => 'MYR',
        'status' => GatewayPaymentStatus::Success,
        'refunded_sen' => 0,
        'paid_at' => now(),
    ]);

    $store = Store::factory()->approved()->create();
    Product::factory()->create(['store_id' => $store->id]);

    return SubOrder::factory()->status(SubOrderStatus::Returned)->create([
        'order_id' => $order->id, 'store_id' => $store->id,
        'items_subtotal_sen' => $totalSen, 'shipping_fee_sen' => 0, 'shop_discount_sen' => 0,
        'total_sen' => $totalSen, 'commission_rate' => '5.00',
        // Set explicitly so the returned instance HOLDS a 0 rather than leaving
        // the attribute absent — a concurrent request is holding a loaded model,
        // not a half-hydrated one, and the test must simulate that exactly.
        'refunded_sen' => 0,
    ]);
}

test('a refund locks the sub-order row it is about to decrement', function () {
    $subOrder = lockDisciplineRefundable();

    [$sql] = capturingQueries(fn () => app(RefundService::class)
        ->refund($subOrder, 5000, ActorType::Admin, null, null, markRefunded: false));

    // Without this the cap below is read from a row anyone else may be writing.
    expect(lockedTables($sql))->toContain('sub_orders');
});

test('the refund cap is recomputed from the locked row, not from the instance handed in', function () {
    $subOrder = lockDisciplineRefundable();

    // Refund it fully through a SEPARATE instance — which is what a concurrent
    // request is, from this one's point of view.
    app(RefundService::class)->refund($subOrder->fresh(), 5000, ActorType::Admin, null, null, markRefunded: false);

    // The instance we still hold has not noticed. This is the hazard in one line.
    expect($subOrder->refunded_sen)->toBe(0)
        ->and($subOrder->fresh()->refunded_sen)->toBe(5000);

    // Hand that stale object to refund(). It must re-read, find nothing left to
    // refund, and no-op — rather than trust refunded_sen = 0 and pay out twice.
    app(RefundService::class)->refund($subOrder, 5000, ActorType::Admin, null, null, markRefunded: false);

    expect($subOrder->fresh()->refunded_sen)->toBe(5000)
        ->and($subOrder->fresh()->refunded_sen)->toBeLessThanOrEqual((int) $subOrder->total_sen);
});
