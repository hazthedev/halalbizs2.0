<?php

/**
 * ADVERSARIAL STRESS AUDIT — payment idempotency, sub-order FSM, and stock.
 *
 * Each test asserts the SAFE invariant. A FAILING assertion here is a WIN:
 * it means a real loophole exists. Passing = the invariant is guarded.
 *
 * Self-contained: no seeders beyond the buyer Role, no shared fixtures.
 * File lives under tests/Feature so Pest.php applies TestCase + RefreshDatabase.
 */

use App\Enums\ActorType;
use App\Enums\CoinTransactionType;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Enums\SubOrderStatus;
use App\Events\OrderPaid;
use App\Models\AffiliateReferral;
use App\Models\CoinTransaction;
use App\Models\EInvoiceDocument;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreLedgerEntry;
use App\Models\SubOrder;
use App\Models\User;
use App\Models\WebhookSubscription;
use App\Services\AffiliateService;
use App\Services\Ipay88Service;
use App\Services\OrderService;
use App\Services\StockService;
use App\Services\SubOrderStatusService;
use App\Settings\Ipay88Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Every outbound HTTP (webhooks, iPay88 requery) is faked so nothing strays.
    Http::fake(['*' => Http::response('OK', 200)]);
    Notification::fake();

    Role::firstOrCreate(['name' => 'buyer', 'guard_name' => 'web']);

    // Real gateway creds by default → isMock() is FALSE (H4 flips this locally).
    $settings = app(Ipay88Settings::class);
    $settings->merchant_code = 'M00001';
    $settings->merchant_key = 'TestKey123';
    $settings->sandbox = true;
    $settings->save();
});

/**
 * A Paid order + one Delivered sub-order (with a real line item) ready to be
 * driven to Completed. Optionally attaches an active affiliate to the order.
 *
 * @return array{0: Order, 1: SubOrder, 2: \App\Models\ProductVariant, 3: ?\App\Models\Affiliate}
 */
function stressDeliveredSubOrder(bool $withAffiliate = false): array
{
    $store = Store::factory()->approved()->create();
    $product = Product::factory()->create(['store_id' => $store->id]);
    $variant = $product->variants()->first();

    $buyer = User::factory()->create();

    $affiliate = null;
    if ($withAffiliate) {
        // Enrol a DIFFERENT user as the affiliate (self-referral would be skipped).
        $affiliate = app(AffiliateService::class)->enroll(User::factory()->create());
    }

    $order = Order::factory()->create([
        'user_id' => $buyer->id,
        'affiliate_id' => $affiliate?->id,
        'payment_method' => PaymentMethod::Ipay88,
        'payment_status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    $subOrder = SubOrder::factory()->create([
        'order_id' => $order->id,
        'store_id' => $store->id,
        'status' => SubOrderStatus::Delivered,
        'items_subtotal_sen' => 20000,
        'shipping_fee_sen' => 0,
        'shop_discount_sen' => 0,
        'tax_sen' => 0,
        'total_sen' => 20000,
        'commission_rate' => '5.00',
        'delivered_at' => now(),
    ]);

    $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_name' => 'Stress widget',
        'unit_price_sen' => 10000,
        'qty' => 2,
        'line_total_sen' => 20000,
    ]);

    return [$order->fresh(), $subOrder->fresh(), $variant, $affiliate];
}

// ---------------------------------------------------------------------------
// H1 — illegal FSM transitions must throw (assert every illegal move is rejected)
// ---------------------------------------------------------------------------
test('H1 illegal sub-order transitions are all rejected', function () {
    $status = app(SubOrderStatusService::class);
    [, $subOrder] = stressDeliveredSubOrder();

    // [from-state, illegal target] — none of these appear in the TRANSITIONS map.
    $illegalMoves = [
        [SubOrderStatus::PendingPayment, SubOrderStatus::Delivered],
        [SubOrderStatus::Confirmed, SubOrderStatus::Delivered],
        [SubOrderStatus::Processing, SubOrderStatus::Completed],
        [SubOrderStatus::Shipped, SubOrderStatus::Cancelled],
        [SubOrderStatus::Delivered, SubOrderStatus::Shipped],
        [SubOrderStatus::Completed, SubOrderStatus::Processing],
        [SubOrderStatus::Cancelled, SubOrderStatus::Confirmed],
        [SubOrderStatus::Cancelled, SubOrderStatus::Refunded],
        [SubOrderStatus::Refunded, SubOrderStatus::Returned],
        [SubOrderStatus::Returned, SubOrderStatus::Completed],
    ];

    $wronglyAllowed = [];

    foreach ($illegalMoves as [$from, $to]) {
        $subOrder->forceFill(['status' => $from])->save();

        try {
            $status->transition($subOrder->fresh(), $to, ActorType::System);
            $wronglyAllowed[] = "{$from->value} -> {$to->value}";
        } catch (InvalidArgumentException) {
            // expected — guard held
        }
    }

    // SAFE invariant: no illegal move slipped through.
    expect($wronglyAllowed)->toBe([]);
});

// ---------------------------------------------------------------------------
// H2 — return_requested -> Completed must NOT double-book completion side-effects
// ---------------------------------------------------------------------------
test('H2 second completion via return_requested does not double-book money', function () {
    config(['coins.enabled' => true, 'affiliate.enabled' => true]);

    [, $subOrder] = stressDeliveredSubOrder(withAffiliate: true);
    $status = app(SubOrderStatusService::class);

    $ledgerSales = fn () => StoreLedgerEntry::where('sub_order_id', $subOrder->id)
        ->where('type', LedgerEntryType::Sale)->count();
    $coinEarns = fn () => CoinTransaction::where('type', CoinTransactionType::Earn)
        ->where('reference_type', $subOrder->getMorphClass())
        ->where('reference_id', $subOrder->id)->count();
    $affRefs = fn () => AffiliateReferral::where('sub_order_id', $subOrder->id)->count();

    // First completion books all three exactly once (harness sanity).
    $status->transition($subOrder->fresh(), SubOrderStatus::Completed, ActorType::System);
    expect($ledgerSales())->toBe(1)
        ->and($coinEarns())->toBe(1)
        ->and($affRefs())->toBe(1);

    // "Sided with seller": Completed -> ReturnRequested -> Completed
    // re-fires SubOrderStatusChanged(Completed) a SECOND time.
    $status->transition($subOrder->fresh(), SubOrderStatus::ReturnRequested, ActorType::Seller);
    $status->transition($subOrder->fresh(), SubOrderStatus::Completed, ActorType::Seller);

    // SAFE invariant: still exactly once each — no double payout/coins/commission.
    expect($ledgerSales())->toBe(1)
        ->and($coinEarns())->toBe(1)
        ->and($affRefs())->toBe(1);
});

// ---------------------------------------------------------------------------
// H3a — duplicate OrderPaid must issue the e-invoice only once
// ---------------------------------------------------------------------------
test('H3a duplicate OrderPaid issues the e-invoice only once', function () {
    config(['einvoice.individual_threshold_sen' => 1_000_000]);

    [$order, $subOrder] = stressDeliveredSubOrder();
    $order->forceFill(['einvoice_requested' => true])->save(); // force individual invoice

    OrderPaid::dispatch($order->fresh());
    OrderPaid::dispatch($order->fresh());

    // SAFE invariant: one document per sub-order, regardless of duplicate events.
    expect(EInvoiceDocument::where('order_id', $order->id)->count())->toBe(1)
        ->and(EInvoiceDocument::where('sub_order_id', $subOrder->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// H3b — duplicate OrderPaid must NOT double-deliver webhooks
// ---------------------------------------------------------------------------
test('H3b duplicate OrderPaid does not duplicate webhook deliveries', function () {
    config(['einvoice.individual_threshold_sen' => 1_000_000]);

    [$order] = stressDeliveredSubOrder();
    $order->forceFill(['einvoice_requested' => true])->save();

    $url = 'https://hook.stress.test/order-paid';
    WebhookSubscription::create([
        'store_id' => null,               // platform-level subscription
        'url' => $url,
        'secret' => 'stress-secret',
        'events' => ['order.paid'],
        'is_active' => true,
    ]);

    OrderPaid::dispatch($order->fresh());
    OrderPaid::dispatch($order->fresh());

    $deliveries = collect(Http::recorded())
        ->filter(fn ($pair) => $pair[0]->url() === $url)
        ->count();

    // SAFE invariant: the order.paid webhook is delivered once, not once-per-emit.
    expect($deliveries)->toBe(1);
});

// ---------------------------------------------------------------------------
// H4 — blank merchant_code leaves the free-order mock path live (reachability)
// ---------------------------------------------------------------------------
test('H4 blank merchant_code makes isMock() true (mock confirm path reachable)', function () {
    $settings = app(Ipay88Settings::class);
    $settings->merchant_code = ''; // as an unseeded production boot would leave it
    $settings->save();

    app()->forgetInstance(Ipay88Settings::class);
    app()->forgetInstance(Ipay88Service::class);

    // There is NO boot-time guard forbidding a blank merchant_code in production;
    // isMock() true means the /mock-confirm route (a free settlement) is live.
    expect(app(Ipay88Service::class)->isMock())->toBeTrue();
});

// ---------------------------------------------------------------------------
// H5 — StockService::apply must never drive stock below zero
// ---------------------------------------------------------------------------
test('H5 stock never goes negative (oversell floor)', function () {
    $product = Product::factory()->create();
    $variant = $product->variants()->first();
    $variant->update(['stock' => 1]);

    // PR #36 made this fail-closed rather than clamping: a decrement past the
    // balance throws instead of silently writing a negative. Either shape
    // satisfies "stock is never < 0"; assert the one that actually shipped.
    expect(fn () => app(StockService::class)->apply($variant->fresh(), -5, StockMovementType::Sale, 'stress-oversell'))
        ->toThrow(RuntimeException::class);

    // SAFE invariant: the rejected decrement left the balance untouched, not negative.
    expect((int) $variant->fresh()->stock)->toBe(1);
});

// ---------------------------------------------------------------------------
// H6 — cancel restocks the variant but must also release flash allocation
// ---------------------------------------------------------------------------
test('H6 cancel releases flash-sale sold_qty (not only variant stock)', function () {
    $store = Store::factory()->approved()->create();
    $product = Product::factory()->create(['store_id' => $store->id]);
    $variant = $product->variants()->first();
    $variant->update(['stock' => 8]);

    $flashSale = FlashSale::create([
        'title' => 'Stress flash',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'is_active' => true,
    ]);
    $flashItem = FlashSaleItem::create([
        'flash_sale_id' => $flashSale->id,
        'product_variant_id' => $variant->id,
        'promo_price_sen' => 5000,
        'allocated_qty' => 10,
        'per_buyer_limit' => 5,
        'sold_qty' => 3, // the 3 units this order claimed from the allocation
    ]);

    $buyer = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $buyer->id,
        'payment_method' => PaymentMethod::Ipay88,
        'payment_status' => PaymentStatus::Pending,
    ]);
    $subOrder = SubOrder::factory()->create([
        'order_id' => $order->id,
        'store_id' => $store->id,
        'status' => SubOrderStatus::Confirmed,
        'items_subtotal_sen' => 15000,
        'shipping_fee_sen' => 0,
        'shop_discount_sen' => 0,
        'tax_sen' => 0,
        'total_sen' => 15000,
        'commission_rate' => '5.00',
    ]);
    $subOrder->items()->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        // PR #35 added this link — the release keys off it, because an order
        // item alone cannot tell WHICH flash allocation it drew from.
        'flash_sale_item_id' => $flashItem->id,
        'product_name' => 'Flash widget',
        'unit_price_sen' => 5000,
        'qty' => 3,
        'line_total_sen' => 15000,
    ]);

    app(OrderService::class)->cancel($subOrder, ActorType::Buyer, $buyer->id, 'changed mind');

    // Variant stock IS restocked (+3) — this half works.
    expect((int) $variant->fresh()->stock)->toBe(11);

    // SAFE invariant: the flash allocation this order held must be released too.
    expect((int) $flashItem->fresh()->sold_qty)->toBe(0);
});
