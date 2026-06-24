<?php

use App\Enums\EInvoiceStatus;
use App\Enums\EInvoiceType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Models\Address;
use App\Models\EInvoiceDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\EInvoiceService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['einvoice.individual_threshold_sen' => 1_000_000]); // RM10,000
});

function einvoiceBuyer(): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor', 'country' => 'MY']);

    return [$buyer, $address];
}

/** Place an iPay88 order (no COD cap) for one product, then force it Paid. */
function paidOrder(User $buyer, Address $address, int $priceSen, array $orderAttrs = []): Order
{
    $product = Product::factory()->create(['cod_enabled' => true]);
    $product->variants->first()->update(['price_sen' => $priceSen, 'sale_price_sen' => null, 'stock' => 100]);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    $order = app(CheckoutService::class)->place($buyer, $address, PaymentMethod::Ipay88);
    $order->forceFill(array_merge(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()], $orderAttrs))->save();

    return $order->fresh('subOrders');
}

test('an individual e-invoice is issued for a sub-order at or above the threshold', function () {
    [$buyer, $address] = einvoiceBuyer();
    $order = paidOrder($buyer, $address, 1_500_000); // RM15,000

    app(EInvoiceService::class)->issueForOrder($order);

    $sub = $order->subOrders->first();
    $doc = EInvoiceDocument::where('sub_order_id', $sub->id)->first();

    expect($doc)->not->toBeNull()
        ->and($doc->type)->toBe(EInvoiceType::Individual)
        ->and($doc->status)->toBe(EInvoiceStatus::Pending) // NullProvider default
        ->and($doc->total_sen)->toBe($sub->total_sen)
        ->and($doc->store_id)->toBe($sub->store_id);
});

test('a below-threshold B2C order is consolidated, not individually invoiced', function () {
    [$buyer, $address] = einvoiceBuyer();
    $order = paidOrder($buyer, $address, 5000); // RM50

    app(EInvoiceService::class)->issueForOrder($order);
    expect(EInvoiceDocument::count())->toBe(0);

    $period = now()->format('Y-m');
    $issued = app(EInvoiceService::class)->consolidate($period);

    $sub = $order->subOrders->first();
    $doc = EInvoiceDocument::where('store_id', $sub->store_id)->where('type', EInvoiceType::Consolidated)->first();

    expect($issued)->toBe(1)
        ->and($doc)->not->toBeNull()
        ->and($doc->period)->toBe($period)
        ->and($doc->sub_order_id)->toBeNull()
        ->and($doc->total_sen)->toBe($sub->total_sen);
});

test('consolidation is idempotent per store and period', function () {
    [$buyer, $address] = einvoiceBuyer();
    paidOrder($buyer, $address, 5000);

    $period = now()->format('Y-m');
    app(EInvoiceService::class)->consolidate($period);
    $again = app(EInvoiceService::class)->consolidate($period);

    expect($again)->toBe(0)
        ->and(EInvoiceDocument::where('type', EInvoiceType::Consolidated)->count())->toBe(1);
});

test('a buyer-requested e-invoice is issued individually even below the threshold', function () {
    [$buyer, $address] = einvoiceBuyer();
    $order = paidOrder($buyer, $address, 5000, ['einvoice_requested' => true]);

    app(EInvoiceService::class)->issueForOrder($order);

    expect(EInvoiceDocument::where('sub_order_id', $order->subOrders->first()->id)
        ->where('type', EInvoiceType::Individual)->exists())->toBeTrue();
});

test('individual issuance is idempotent', function () {
    [$buyer, $address] = einvoiceBuyer();
    $order = paidOrder($buyer, $address, 1_500_000);
    $sub = $order->subOrders->first();

    $service = app(EInvoiceService::class);
    $service->issueIndividual($sub);
    $service->issueIndividual($sub);

    expect(EInvoiceDocument::where('sub_order_id', $sub->id)->count())->toBe(1);
});

test('the OrderPaid event issues e-invoices through the listener', function () {
    [$buyer, $address] = einvoiceBuyer();
    $order = paidOrder($buyer, $address, 1_500_000);

    OrderPaid::dispatch($order);

    expect(EInvoiceDocument::where('sub_order_id', $order->subOrders->first()->id)->exists())->toBeTrue();
});

test('nothing is issued before an order is paid', function () {
    [$buyer, $address] = einvoiceBuyer();
    $order = paidOrder($buyer, $address, 1_500_000, ['payment_status' => PaymentStatus::Pending, 'paid_at' => null]);

    app(EInvoiceService::class)->issueForOrder($order);

    expect(EInvoiceDocument::count())->toBe(0);
});
