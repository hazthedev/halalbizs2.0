<?php

use App\Enums\ActorType;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\PayoutStatus;
use App\Enums\SubOrderStatus;
use App\Exceptions\CheckoutException;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\SubOrder;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\LedgerService;
use App\Services\OrderService;
use App\Services\SubOrderStatusService;
use Database\Seeders\RoleSeeder;

beforeEach(fn () => $this->seed(RoleSeeder::class));

/**
 * Walk a fresh COD or iPay88 order to completed and return the sub-order.
 */
function completedSubOrder(PaymentMethod $method = PaymentMethod::Cod, int $priceSen = 10000, float $commission = 5.0): SubOrder
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->store->update(['commission_rate' => $commission, 'shipping_flat_fee_sen' => 500]);
    $product->variants->first()->update(['price_sen' => $priceSen, 'sale_price_sen' => null, 'stock' => 10]);

    app(CartService::class)->addItem($buyer, $product->variants->first(), 2);
    $order = app(CheckoutService::class)->place($buyer, $address, $method);

    $subOrder = $order->subOrders->first();
    $statusService = app(SubOrderStatusService::class);

    if ($method === PaymentMethod::Ipay88) {
        $statusService->transition($subOrder, SubOrderStatus::Confirmed, ActorType::System);
    }

    $statusService->transition($subOrder->fresh(), SubOrderStatus::Processing, ActorType::Seller);
    $statusService->transition($subOrder->fresh(), SubOrderStatus::Shipped, ActorType::Seller);
    app(OrderService::class)->markDelivered($subOrder->fresh(), ActorType::System);
    app(OrderService::class)->confirmReceived($subOrder->fresh(), $buyer->id);

    return $subOrder->fresh();
}

test('completion writes sale and commission entries and bumps sold_count', function () {
    $subOrder = completedSubOrder(PaymentMethod::Ipay88, priceSen: 10000, commission: 5.0);
    $store = $subOrder->store;

    // sale = items 20000 + shipping 500; commission = 5% × 20000 = 1000
    $entries = $store->ledgerEntries()->get()->keyBy(fn ($entry) => $entry->type->value);

    expect($entries['sale']->amount_sen)->toBe(20500)
        ->and($entries['commission']->amount_sen)->toBe(-1000)
        ->and($entries->has('cod_offset'))->toBeFalse()
        ->and($subOrder->commission_sen)->toBe(1000)
        ->and($store->availableBalanceSen())->toBe(19500)
        ->and($subOrder->items->first()->product->fresh()->sold_count)
        ->toBeGreaterThanOrEqual(2);
});

test('COD completion nets to commission owed via cod_offset', function () {
    $subOrder = completedSubOrder(PaymentMethod::Cod, priceSen: 10000, commission: 5.0);
    $store = $subOrder->store;

    $entries = $store->ledgerEntries()->get()->keyBy(fn ($entry) => $entry->type->value);

    expect($entries['sale']->amount_sen)->toBe(20500)
        ->and($entries['commission']->amount_sen)->toBe(-1000)
        ->and($entries['cod_offset']->amount_sen)->toBe(-20500)
        ->and($store->availableBalanceSen())->toBe(-1000); // commission owed — negative allowed
});

test('completion hook is idempotent', function () {
    $subOrder = completedSubOrder();

    app(LedgerService::class)->recordCompletion($subOrder);

    expect($subOrder->store->ledgerEntries()->where('type', LedgerEntryType::Sale)->count())->toBe(1);
});

test('payout request earmarks, enforces minimum and available balance, and blocks concurrent requests', function () {
    $subOrder = completedSubOrder(PaymentMethod::Ipay88); // balance 19500
    $store = $subOrder->store;
    $ledger = app(LedgerService::class);

    // Below minimum (RM50 default).
    expect(fn () => $ledger->requestPayout($store, 1000))->toThrow(CheckoutException::class, 'Minimum payout');

    // Above available.
    expect(fn () => $ledger->requestPayout($store, 99999))->toThrow(CheckoutException::class, 'available');

    $payout = $ledger->requestPayout($store, 15000);

    expect($payout->status)->toBe(PayoutStatus::Requested)
        ->and($payout->bank_snapshot)->not->toBeNull()
        ->and($store->availableBalanceSen())->toBe(4500);

    // One open request at a time.
    expect(fn () => $ledger->requestPayout($store, 4500))->toThrow(CheckoutException::class, 'in progress');
});

test('payout rejection releases the earmark', function () {
    $subOrder = completedSubOrder(PaymentMethod::Ipay88);
    $store = $subOrder->store;
    $ledger = app(LedgerService::class);

    $payout = $ledger->requestPayout($store, 15000);
    expect($store->availableBalanceSen())->toBe(4500);

    $ledger->rejectPayout($payout, 'Bank details mismatch');

    expect($payout->fresh()->status)->toBe(PayoutStatus::Rejected)
        ->and($store->availableBalanceSen())->toBe(19500);
});

test('negative balance blocks payout requests', function () {
    $subOrder = completedSubOrder(PaymentMethod::Cod); // balance −1000
    $store = $subOrder->store;

    expect(fn () => app(LedgerService::class)->requestPayout($store, 5000))
        ->toThrow(CheckoutException::class);
});

test('admin adjustment reverses sale and commission proportionally', function () {
    $subOrder = completedSubOrder(PaymentMethod::Ipay88);
    $store = $subOrder->store;
    $before = $store->availableBalanceSen(); // 19500

    // Refund after completion: reverse sale (−20500) and commission (+1000).
    app(LedgerService::class)->adjustment($store, -20500 + 1000, 'Refund after completion', $subOrder);

    expect($store->availableBalanceSen())->toBe($before - 19500)
        ->and($store->ledgerEntries()->where('type', LedgerEntryType::Adjustment)->first()->amount_sen)->toBe(-19500);
});
