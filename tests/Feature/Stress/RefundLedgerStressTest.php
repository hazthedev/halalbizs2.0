<?php

use App\Enums\ActorType;
use App\Enums\GatewayPaymentStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\PayoutStatus;
use App\Enums\SubOrderStatus;
use App\Exceptions\CheckoutException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Services\LedgerService;
use App\Services\RefundService;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Adversarial money-integrity stress for RefundService / LedgerService.
 *
 * Each test asserts the SAFE invariant. A failing assertion therefore CONFIRMS a
 * real refund/ledger loophole; a passing test REFUTES the hypothesis. Self-contained
 * (no seeding beyond the seller role), in-memory SQLite + RefreshDatabase.
 */
beforeEach(function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
});

/** Paid iPay88 order with explicit money fields (no random amounts). */
function stressOrder(int $grandTotalSen, int $subtotalSen, int $discountSen = 0): Order
{
    return Order::factory()->paid()->create([
        'payment_method' => PaymentMethod::Ipay88,
        'subtotal_sen' => $subtotalSen,
        'shipping_total_sen' => 0,
        'discount_total_sen' => $discountSen,
        'coin_redemption_sen' => 0,
        'grand_total_sen' => $grandTotalSen,
    ]);
}

/** The single payment row RefundService tracks refunds against. */
function stressPayment(Order $order): Payment
{
    return Payment::create([
        'order_id' => $order->id,
        'gateway' => PaymentMethod::Ipay88,
        'ref_no' => 'REF'.strtoupper(Str::random(8)),
        'amount_sen' => $order->grand_total_sen,
        'currency' => 'MYR',
        'status' => GatewayPaymentStatus::Success,
        'refunded_sen' => 0,
    ]);
}

/**
 * A sub-order whose Sale ledger entry is already booked (via recordCompletion),
 * so RefundService's ledger reversal step actually fires. total = items only
 * (shipping/tax/discount 0) to keep the arithmetic exact. 5% commission.
 */
function stressBookedSubOrder(Order $order, int $totalSen, SubOrderStatus $status = SubOrderStatus::Completed): SubOrder
{
    $store = Store::factory()->approved()->create(['commission_rate' => '5.00']);

    $subOrder = SubOrder::factory()->create([
        'order_id' => $order->id,
        'store_id' => $store->id,
        'status' => $status,
        'items_subtotal_sen' => $totalSen,
        'shipping_fee_sen' => 0,
        'shop_discount_sen' => 0,
        'tax_sen' => 0,
        'total_sen' => $totalSen,
        'commission_rate' => '5.00',
    ]);

    app(LedgerService::class)->recordCompletion($subOrder); // books +sale / -commission

    return $subOrder->fresh();
}

/*
|--------------------------------------------------------------------------
| H1 — Refund idempotency
|--------------------------------------------------------------------------
| RefundService has no idempotency guard: the reversal + refunded_sen bump run
| on EVERY call. A duplicate full refund (admin double-submit) must be a no-op.
*/
test('H1: two identical full refunds must not double-apply', function () {
    $order = stressOrder(grandTotalSen: 20000, subtotalSen: 20000);
    $payment = stressPayment($order);
    $subOrder = stressBookedSubOrder($order, totalSen: 20000);
    $store = $subOrder->store;

    $refund = app(RefundService::class);
    $refund->refund($subOrder->fresh(), 20000, ActorType::Admin, 1);
    $refund->refund($subOrder->fresh(), 20000, ActorType::Admin, 1); // duplicate

    $payment->refresh();
    $adjustmentCount = $store->ledgerEntries()->where('type', LedgerEntryType::Adjustment)->count();

    expect((int) $payment->refunded_sen)->toBe(20000)                               // NOT 40000
        ->and($adjustmentCount)->toBe(1)                                             // exactly ONE reversal
        ->and(app(LedgerService::class)->availableBalanceSen($store))->toBe(0);      // not double-reduced
});

/*
|--------------------------------------------------------------------------
| H2 — Over-refund vs. amount actually charged
|--------------------------------------------------------------------------
| An order-level platform voucher (discount_total_sen) lowers grand_total but
| NOT the per-sub_order total_sen. So Σ(sub_order.total_sen) > grand_total_sen,
| and fully refunding each sub-order returns more cash than was ever collected.
*/
test('H2: total cash refunded must not exceed the order grand total', function () {
    $order = stressOrder(grandTotalSen: 15000, subtotalSen: 20000, discountSen: 5000);
    $payment = stressPayment($order);

    $subA = stressBookedSubOrder($order, totalSen: 10000);
    $subB = stressBookedSubOrder($order, totalSen: 10000);

    $refund = app(RefundService::class);
    $refund->refund($subA->fresh(), 10000, ActorType::Admin, 1);
    $refund->refund($subB->fresh(), 10000, ActorType::Admin, 1);

    $payment->refresh();

    // Σ refunds = 20000 but only 15000 was charged.
    expect((int) $payment->refunded_sen)->toBeLessThanOrEqual($order->grand_total_sen);
});

/*
|--------------------------------------------------------------------------
| H3 — Partial refund mislabels the sub-order as terminally Refunded
|--------------------------------------------------------------------------
| Refunded is only reachable from return_requested/returned. With markRefunded
| = true, RefundService flips to Refunded without checking amount == total, so a
| HALF refund terminally marks the sub-order Refunded (money-side underpaid,
| status wrong, and 'refunded' is a terminal state).
*/
test('H3: a partial refund must not flip the sub-order to terminal Refunded', function () {
    $order = stressOrder(grandTotalSen: 20000, subtotalSen: 20000);
    $payment = stressPayment($order);
    $subOrder = stressBookedSubOrder($order, totalSen: 20000, status: SubOrderStatus::ReturnRequested);

    app(RefundService::class)->refund($subOrder->fresh(), 10000, ActorType::Admin, 1, null, true); // HALF

    $subOrder->refresh();
    $payment->refresh();

    expect((int) $payment->refunded_sen)->toBe(10000)                    // only half returned
        ->and($subOrder->status)->not->toBe(SubOrderStatus::Refunded);   // must not be terminal
});

/*
|--------------------------------------------------------------------------
| H4 — a negative balance is a DEBT, not a bug (resolved 2026-07-27)
|--------------------------------------------------------------------------
| The original H4 asserted `available >= 0` and called the missing floor a
| finding. It was mislabelled. A negative balance is a designed, routine state
| here: every COD completion writes +sale, -commission and -cod_offset, netting
| to MINUS the commission, because the seller already collected the cash. A
| COD-only seller lives permanently negative by design, and the seller Earnings
| screen already renders it in red and explains it.
|
| A >= 0 floor would therefore be the actual bug — it would silently forgive
| commission on every COD order, erasing the debt from the only place that
| records it.
|
| What genuinely matters is that a store in debt cannot TAKE money out. These
| tests assert that instead.
*/
test('H4: a store balance may legitimately go negative — that is a recorded debt', function () {
    $store = Store::factory()->approved()->create();
    $ledger = app(LedgerService::class);

    $ledger->adjustment($store, 3000, 'seed small balance');
    $ledger->adjustment($store, -100000, 'oversized clawback');

    // The debt is recorded in full, not clamped away.
    expect($ledger->availableBalanceSen($store))->toBe(-97000);
});

test('H4a: a store in debt cannot withdraw', function () {
    $ledger = app(LedgerService::class);

    // Guard: the SAME request succeeds for a solvent store, so the refusal below
    // is the debt doing it — not the minimum-payout rule or some other gate.
    $solvent = Store::factory()->approved()->create();
    $ledger->adjustment($solvent, 20000, 'earned');
    expect($ledger->requestPayout($solvent, 8000))->not->toBeNull();

    $indebted = Store::factory()->approved()->create();
    $ledger->adjustment($indebted, 20000, 'earned');
    $ledger->adjustment($indebted, -100000, 'oversized clawback');

    // SAFE invariant: no withdrawing money you owe.
    expect(fn () => $ledger->requestPayout($indebted, 8000))->toThrow(CheckoutException::class);
});

test('H4b: a store in debt cannot spend on boosts either', function () {
    $store = Store::factory()->approved()->create();
    $product = Product::factory()->create(['store_id' => $store->id]);
    $ledger = app(LedgerService::class);

    $ledger->adjustment($store, 3000, 'seed small balance');
    $ledger->adjustment($store, -100000, 'oversized clawback');

    // Same invariant by the other door — boosts are paid from available earnings.
    expect(fn () => $ledger->chargeBoost($store, 1000, $product))->toThrow(CheckoutException::class);
});

test('H4c: earning past zero restores the ability to withdraw — the debt self-heals', function () {
    $store = Store::factory()->approved()->create();
    $ledger = app(LedgerService::class);

    $ledger->adjustment($store, -5000, 'commission owed on COD sales');
    expect(fn () => $ledger->requestPayout($store, 8000))->toThrow(CheckoutException::class);

    $ledger->adjustment($store, 25000, 'later online-payment sales');

    expect($ledger->availableBalanceSen($store))->toBe(20000)
        ->and($ledger->requestPayout($store, 8000))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| H5 — Payout guards (expected to hold / REFUTED)
|--------------------------------------------------------------------------
*/
test('H5: payout guards hold — min threshold, over-available, one-open', function () {
    $store = Store::factory()->approved()->create();
    $ledger = app(LedgerService::class);
    $ledger->adjustment($store, 10000, 'seed available'); // available = 10000; min threshold = 5000

    // below the minimum threshold
    expect(fn () => $ledger->requestPayout($store, 100))->toThrow(CheckoutException::class);

    // more than is available
    expect(fn () => $ledger->requestPayout($store, 20000))->toThrow(CheckoutException::class);

    // a valid request succeeds
    $payout = $ledger->requestPayout($store, 6000);
    expect($payout->status)->toBe(PayoutStatus::Requested);

    // a second request while one is open is blocked
    expect(fn () => $ledger->requestPayout($store, 6000))->toThrow(CheckoutException::class);
});
