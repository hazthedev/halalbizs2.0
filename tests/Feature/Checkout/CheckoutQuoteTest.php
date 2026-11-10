<?php

use App\Enums\CoinTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Enums\TaxClass;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Exceptions\CheckoutException;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\SubOrder;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\CoinService;
use App\Settings\CodSettings;
use App\Settings\Ipay88Settings;
use App\Support\CheckoutQuote;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['coins.enabled' => true, 'coins.redemption_rate_sen' => 1, 'coins.max_redemption_sen' => 5000]);
});

/** @return array{0: User, 1: Address} */
function quoteBuyer(): array
{
    $buyer = User::factory()->create();
    $buyer->assignRole('buyer');
    $address = Address::factory()->default()->create(['user_id' => $buyer->id, 'state' => 'Selangor']);

    return [$buyer, $address];
}

function quoteProduct(int $priceSen, int $flatFeeSen = 500, bool $cod = true, bool $sst = false): Product
{
    $product = Product::factory()->create([
        'cod_enabled' => $cod,
        'tax_class' => $sst ? TaxClass::Standard : TaxClass::Exempt,
    ]);

    $product->store->update([
        'sst_registered' => $sst,
        'sst_number' => $sst ? 'W10-1234-56789' : null,
        'shipping_mode' => 'flat',
        'shipping_flat_fee_sen' => $flatFeeSen,
        'shipping_matrix' => null,
        'free_shipping_over_sen' => null,
    ]);

    $product->variants->first()->update([
        'price_sen' => $priceSen, 'sale_price_sen' => null, 'sale_starts_at' => null, 'sale_ends_at' => null, 'stock' => 10,
    ]);

    return $product;
}

function quoteVoucher(array $attributes): Voucher
{
    return Voucher::create(array_merge([
        'scope' => VoucherScope::Platform,
        'type' => VoucherType::Fixed,
        'min_spend_sen' => 0,
        'quota' => 5,
        'per_user_limit' => 1,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
    ], $attributes));
}

function quoteEnableIpay88(): void
{
    $settings = app(Ipay88Settings::class);
    $settings->merchant_code = 'M00001';
    $settings->merchant_key = 'TestKey123';
    $settings->save();
}

/** The quote's numbers must be the order's numbers, order-level and per store. */
function expectQuoteToMatchOrder(CheckoutQuote $quote, Order $order): void
{
    expect($quote->subtotalSen)->toBe((int) $order->subtotal_sen)
        ->and($quote->shippingTotalSen)->toBe((int) $order->shipping_total_sen)
        ->and($quote->taxTotalSen)->toBe((int) $order->tax_total_sen)
        ->and($quote->platformDiscountSen)->toBe((int) $order->discount_total_sen)
        ->and($quote->coinRedemptionSen)->toBe((int) $order->coin_redemption_sen)
        ->and($quote->grandTotalSen)->toBe((int) $order->grand_total_sen)
        ->and($quote->grandTotalSen)->toBe((int) $order->payment->amount_sen)
        ->and($quote->shopDiscountSen)->toBe((int) $order->subOrders->sum('shop_discount_sen'))
        ->and($quote->shippingSubsidySen)->toBe((int) $order->subOrders->sum('shipping_subsidy_sen'))
        ->and($quote->groups)->toHaveCount($order->subOrders->count());

    foreach ($quote->groups as $group) {
        $subOrder = $order->subOrders->firstWhere('store_id', $group->store->id);

        expect($subOrder)->not->toBeNull()
            ->and($group->itemsSubtotalSen)->toBe((int) $subOrder->items_subtotal_sen)
            ->and($group->taxSen)->toBe((int) $subOrder->tax_sen)
            ->and($group->shippingSubsidySen)->toBe((int) $subOrder->shipping_subsidy_sen)
            // The buyer-facing fee: a waived store ships free whoever funds it
            // (platform-funded keeps the fee on the sub-order, seller-funded zeroes it).
            ->and($group->shippingFeeSen)->toBe((int) $subOrder->shipping_subsidy_sen > 0 ? 0 : (int) $subOrder->shipping_fee_sen)
            ->and($group->lines)->toHaveCount($subOrder->items->count());

        foreach ($group->lines as $line) {
            $item = $subOrder->items->firstWhere('product_variant_id', $line->variant->id);

            expect($item)->not->toBeNull()
                ->and($line->qty)->toBe((int) $item->qty)
                ->and($line->unitPriceSen)->toBe((int) $item->unit_price_sen)
                ->and($line->unitPriceSen * $line->qty)->toBe((int) $item->line_total_sen)
                ->and($line->listPriceSen)->toBe((int) $item->list_price_sen)
                ->and($line->taxSen)->toBe((int) $item->tax_sen);
        }
    }

    $array = $quote->toArray();

    expect($array['grand_total_sen'])->toBe((int) $order->grand_total_sen)
        ->and($array['subtotal_sen'])->toBe((int) $order->subtotal_sen)
        ->and($array['coin_redemption_sen'])->toBe((int) $order->coin_redemption_sen);
}

test('a COD quote with a shop voucher, a platform voucher and coins equals the order place() writes', function () {
    [$buyer, $address] = quoteBuyer();
    $productA = quoteProduct(10000, flatFeeSen: 700);
    $productB = quoteProduct(5000, flatFeeSen: 900);
    quoteVoucher(['code' => 'PLAT10', 'value_sen' => 1000]);
    quoteVoucher(['scope' => VoucherScope::Shop, 'store_id' => $productA->store_id, 'code' => 'SHOP5', 'value_sen' => 500]);
    app(CoinService::class)->credit($buyer, 2000, CoinTransactionType::Earn);

    app(CartService::class)->addItem($buyer, $productA->variants->first(), 2);
    app(CartService::class)->addItem($buyer, $productB->variants->first(), 1);

    $checkout = app(CheckoutService::class);
    $quote = $checkout->quote($buyer, $address, null, 'PLAT10', 'SHOP5', coinsToRedeem: 1500, method: PaymentMethod::Cod);

    expect($quote->subtotalSen)->toBe(25000)
        ->and($quote->shippingTotalSen)->toBe(1600)
        ->and($quote->platformDiscountSen)->toBe(1000)
        ->and($quote->shopDiscountSen)->toBe(500)
        ->and($quote->coinsRedeemed)->toBe(1500)
        ->and($quote->coinRedemptionSen)->toBe(1500)
        ->and($quote->grandTotalSen)->toBe(25000 + 1600 - 1000 - 500 - 1500)
        ->and($quote->voucherErrors)->toBe([])
        ->and($quote->codUnavailableReason)->toBeNull()
        ->and($quote->paymentMethod)->toBe(PaymentMethod::Cod)
        ->and($quote->paymentMethodAvailable())->toBeTrue()
        ->and(app(CoinService::class)->balance($buyer))->toBe(2000); // a quote consumes nothing

    $order = $checkout->place($buyer, $address, PaymentMethod::Cod, 'PLAT10', 'SHOP5', [], null, 1500);

    expectQuoteToMatchOrder($quote, $order);
    expect(app(CoinService::class)->balance($buyer))->toBe(500);
});

test('an iPay88 quote with SST, a free-shipping voucher, a shop voucher and coins equals the order place() writes', function () {
    quoteEnableIpay88();
    [$buyer, $address] = quoteBuyer();
    $productA = quoteProduct(10000, flatFeeSen: 700, sst: true);
    $productB = quoteProduct(5000, flatFeeSen: 900);
    quoteVoucher(['code' => 'PLAT10', 'value_sen' => 1000]);
    quoteVoucher(['code' => 'FREESHIP', 'type' => VoucherType::FreeShipping]);
    quoteVoucher(['scope' => VoucherScope::Shop, 'store_id' => $productB->store_id, 'code' => 'SHOP5', 'value_sen' => 500]);
    app(CoinService::class)->credit($buyer, 900, CoinTransactionType::Earn);

    app(CartService::class)->addItem($buyer, $productA->variants->first(), 1);
    app(CartService::class)->addItem($buyer, $productB->variants->first(), 3);

    $checkout = app(CheckoutService::class);
    $quote = $checkout->quote($buyer, $address, 'FREESHIP', 'PLAT10', 'SHOP5', coinsToRedeem: 900, method: PaymentMethod::Ipay88);

    expect($quote->taxTotalSen)->toBe(1000)              // 10% SST on RM100.00 of store A
        ->and($quote->shippingTotalSen)->toBe(0)          // both stores waived
        ->and($quote->shippingSubsidySen)->toBe(1600)
        ->and($quote->groups->every(fn ($group) => $group->shippingFeeSen === 0))->toBeTrue()
        ->and($quote->paymentMethod)->toBe(PaymentMethod::Ipay88)
        ->and($quote->ipay88Enabled)->toBeTrue();

    $order = $checkout->place($buyer, $address, PaymentMethod::Ipay88, 'PLAT10', 'SHOP5', [], 'FREESHIP', 900);

    expectQuoteToMatchOrder($quote, $order);
    expect($order->subOrders->first()->status)->toBe(SubOrderStatus::PendingPayment);
});

test('a seller-funded free-shipping voucher zeroes the buyer-facing fee in the quote exactly as on the sub-order', function () {
    [$buyer, $address] = quoteBuyer();
    $productA = quoteProduct(10000, flatFeeSen: 700);
    $productB = quoteProduct(5000, flatFeeSen: 900);
    quoteVoucher(['scope' => VoucherScope::Shop, 'store_id' => $productA->store_id, 'code' => 'SHIPFREEA', 'type' => VoucherType::FreeShipping]);

    app(CartService::class)->addItem($buyer, $productA->variants->first(), 1);
    app(CartService::class)->addItem($buyer, $productB->variants->first(), 1);

    $checkout = app(CheckoutService::class);
    $quote = $checkout->quote($buyer, $address, shopCode: 'SHIPFREEA', method: PaymentMethod::Cod);
    $groupA = $quote->groups->firstWhere('store.id', $productA->store_id);

    expect($quote->shippingTotalSen)->toBe(900)
        ->and($quote->shippingSubsidySen)->toBe(700)
        ->and($groupA->shippingFeeSen)->toBe(0)
        ->and($groupA->shippingSubsidySen)->toBe(700);

    $order = $checkout->place($buyer, $address, PaymentMethod::Cod, null, 'SHIPFREEA');
    $subA = $order->subOrders->firstWhere('store_id', $productA->store_id);

    expect((int) $subA->shipping_fee_sen)->toBe(0)          // seller absorbs it
        ->and((int) $subA->shipping_subsidy_sen)->toBe(700);

    expectQuoteToMatchOrder($quote, $order);
});

test('a quote without an address has no shipping or tax yet', function () {
    [$buyer] = quoteBuyer();
    $product = quoteProduct(10000, sst: true);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    $quote = app(CheckoutService::class)->quote($buyer, null);

    expect($quote->groups->first()->shippingFeeSen)->toBeNull()
        ->and($quote->shippingTotalSen)->toBe(0)
        ->and($quote->taxTotalSen)->toBe(0)
        ->and($quote->grandTotalSen)->toBe(10000)
        ->and($quote->storeSubtotals)->toBe([$product->store_id => 10000]);
});

test('an applied code that no longer validates is dropped with its reason, not thrown', function () {
    [$buyer, $address] = quoteBuyer();
    $product = quoteProduct(10000);
    quoteVoucher(['code' => 'MIN150', 'value_sen' => 500, 'min_spend_sen' => 15000]);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    $quote = app(CheckoutService::class)->quote($buyer, $address, platformCode: 'MIN150', shopCode: 'NOPE');

    expect($quote->platformDiscount)->toBeNull()
        ->and($quote->shopDiscount)->toBeNull()
        ->and($quote->platformDiscountSen)->toBe(0)
        ->and($quote->voucherErrors)->toHaveKeys(['platform', 'shop'])
        ->and($quote->voucherErrors['platform'])->toContain('RM 150.00 minimum')
        ->and($quote->voucherErrors['shop'])->toContain("can't find that voucher")
        ->and($quote->grandTotalSen)->toBe(10500);
});

test('COD block reasons: switched off, item not eligible, over the cap, open-order limit', function () {
    [$buyer, $address] = quoteBuyer();
    $checkout = app(CheckoutService::class);

    $product = quoteProduct(10000, flatFeeSen: 0);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    expect($checkout->quote($buyer, $address, method: PaymentMethod::Cod)->codUnavailableReason)->toBeNull();

    // Open-order limit — ignores delivered and cancelled orders, like place().
    foreach ([SubOrderStatus::Confirmed, SubOrderStatus::Processing, SubOrderStatus::Shipped, SubOrderStatus::Delivered, SubOrderStatus::Cancelled] as $status) {
        $order = Order::factory()->create(['user_id' => $buyer->id, 'payment_method' => PaymentMethod::Cod]);
        SubOrder::factory()->status($status)->create(['order_id' => $order->id]);
    }

    $quote = $checkout->quote($buyer, $address, method: PaymentMethod::Cod);

    expect($quote->codUnavailableReason)->toBe('You have open cash-on-delivery orders; receive them before placing another.')
        ->and($quote->paymentMethod)->toBeNull()          // iPay88 is not configured either
        ->and($quote->paymentMethodAvailable())->toBeFalse();

    quoteEnableIpay88();
    expect($checkout->quote($buyer, $address, method: PaymentMethod::Cod)->paymentMethod)->toBe(PaymentMethod::Ipay88);

    // Over the cap wins over the open-order limit (same order as the web page).
    $big = quoteProduct(60000, flatFeeSen: 0);
    app(CartService::class)->addItem($buyer, $big->variants->first(), 1);
    expect($checkout->quote($buyer, $address)->codUnavailableReason)->toBe('COD unavailable: order exceeds RM 500.00');

    // Item not eligible wins over the cap.
    $noCod = quoteProduct(1000, cod: false);
    app(CartService::class)->addItem($buyer, $noCod->variants->first(), 1);
    expect($checkout->quote($buyer, $address)->codUnavailableReason)->toBe("COD unavailable: some selected items don't support cash on delivery.");

    // The global switch wins over everything.
    $cod = app(CodSettings::class);
    $cod->enabled = false;
    $cod->save();
    expect($checkout->quote($buyer, $address)->codUnavailableReason)->toBe('COD unavailable: cash on delivery is switched off right now.');
});

test('a quote drops exactly the lines place() refuses and names each with its reason', function () {
    [$buyer, $address] = quoteBuyer();
    $kept = quoteProduct(10000, flatFeeSen: 0);
    $unticked = quoteProduct(2500, flatFeeSen: 0);
    $soldOut = quoteProduct(4000, flatFeeSen: 0);
    $short = quoteProduct(3000, flatFeeSen: 0);
    $closed = quoteProduct(2000, flatFeeSen: 0);

    $cart = app(CartService::class);
    $cart->addItem($buyer, $kept->variants->first(), 1);
    $cart->addItem($buyer, $unticked->variants->first(), 1);
    $cart->addItem($buyer, $soldOut->variants->first(), 1);
    $cart->addItem($buyer, $short->variants->first(), 2);
    $cart->addItem($buyer, $closed->variants->first(), 1);
    $cart->setSelected($buyer, $unticked->variants->first(), false);
    $soldOut->variants->first()->update(['stock' => 0]);
    $short->variants->first()->update(['stock' => 1]);     // qty 2 > stock 1
    $closed->store->update(['holiday_mode' => true]);

    $checkout = app(CheckoutService::class);
    $blocked = $checkout->blockedLines($buyer)->keyBy('variant.id');

    expect($checkout->quote($buyer, $address)->grandTotalSen)->toBe(10000)
        ->and($checkout->selectedGroups($buyer))->toHaveCount(1)
        ->and($blocked)->toHaveCount(3)
        ->and($blocked[$soldOut->variants->first()->id]->reason)->toContain('just sold out — only 0 left')
        ->and($blocked[$short->variants->first()->id]->reason)->toContain('just sold out — only 1 left')
        ->and($blocked[$closed->variants->first()->id]->reason)->toBe($closed->store->name.' is not accepting orders right now.');

    // The same three refusals place() makes — same wording, because it is the same code.
    $cart->setSelected($buyer, $kept->variants->first(), false);
    $cart->setSelected($buyer, $soldOut->variants->first(), false);
    $cart->setSelected($buyer, $short->variants->first(), false);

    expect(fn () => $checkout->place($buyer, $address, PaymentMethod::Cod))
        ->toThrow(CheckoutException::class, $closed->store->name.' is not accepting orders right now.');
});

test('a quote names the outright blockers place() throws: nothing selected, total not above zero', function () {
    [$buyer, $address] = quoteBuyer();
    $checkout = app(CheckoutService::class);

    expect($checkout->quote($buyer, $address)->blockReason)->toBe('Your cart has no selected items.')
        ->and(fn () => $checkout->place($buyer, $address, PaymentMethod::Cod))
        ->toThrow(CheckoutException::class, 'Your cart has no selected items.');

    $product = quoteProduct(10000, flatFeeSen: 0);
    quoteVoucher(['code' => 'ALLOFF', 'type' => VoucherType::Percent, 'percent' => 100, 'value_sen' => null]);
    app(CartService::class)->addItem($buyer, $product->variants->first(), 1);

    $quote = $checkout->quote($buyer, $address, platformCode: 'ALLOFF');

    expect($quote->platformDiscountSen)->toBe(10000)
        ->and($quote->grandTotalSen)->toBe(0)
        ->and($quote->blockReason)->toBe('Order total must be above zero.')
        ->and($quote->toArray()['block_reason'])->toBe('Order total must be above zero.')
        ->and(fn () => $checkout->place($buyer, $address, PaymentMethod::Cod, 'ALLOFF'))
        ->toThrow(CheckoutException::class, 'Order total must be above zero.');
});
