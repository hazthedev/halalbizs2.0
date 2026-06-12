<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Enums\VoucherScope;
use App\Exceptions\CheckoutException;
use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Settings\CodSettings;
use App\Settings\OrderSettings;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * The checkout transaction (docs/06 §B). Hard rules enforced here:
 * integer sen money, atomic stock + voucher consumption under
 * lockForUpdate, snapshot order items, status only via the service.
 */
class CheckoutService
{
    public function __construct(
        private ShippingCalculator $shipping,
        private CommissionResolver $commission,
        private SubOrderStatusService $statusService,
        private CartService $cartService,
        private VoucherService $vouchers,
        private CodSettings $codSettings,
        private OrderSettings $orderSettings,
    ) {}

    /**
     * Stacking (docs/09 §B, Shopee model): one platform voucher + one shop
     * voucher per order. $platformCode keeps the old 4th-positional meaning.
     *
     * @param  array<int, string>  $sellerNotes  keyed by store_id
     *
     * @throws CheckoutException
     */
    public function place(
        User $buyer,
        Address $address,
        PaymentMethod $method,
        ?string $platformCode = null,
        ?string $shopCode = null,
        array $sellerNotes = [],
    ): Order {
        return DB::transaction(function () use ($buyer, $address, $method, $platformCode, $shopCode) {
            // 1. Reload selected cart lines with the variants locked.
            $items = $buyer->cart?->items()
                ->where('selected', true)
                ->get() ?? collect();

            if ($items->isEmpty()) {
                throw new CheckoutException(__('Your cart has no selected items.'));
            }

            $variants = ProductVariant::with(['product.store', 'product.category'])
                ->whereIn('id', $items->pluck('product_variant_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = collect();

            foreach ($items as $item) {
                $variant = $variants->get($item->product_variant_id);

                if ($variant === null || ! $variant->product->isLive()) {
                    throw new CheckoutException(__('An item in your cart is no longer available.'));
                }

                $store = $variant->product->store;

                if (! $store->isApproved() || $store->holiday_mode) {
                    throw new CheckoutException(__(':store is not accepting orders right now.', ['store' => $store->name]));
                }

                if ($variant->stock < $item->qty) {
                    $label = trim($variant->product->getTranslation('name', 'en').' '.($variant->options_label ?? ''));

                    throw new CheckoutException(__(':item just sold out — only :stock left.', ['item' => $label, 'stock' => $variant->stock]));
                }

                $lines->push((object) ['variant' => $variant, 'qty' => $item->qty]);
            }

            // COD constraints (docs/01 §3.5).
            if ($method === PaymentMethod::Cod) {
                if (! $this->codSettings->enabled) {
                    throw new CheckoutException(__('Cash on delivery is currently unavailable.'));
                }

                foreach ($lines as $line) {
                    if (! $line->variant->product->cod_enabled) {
                        throw new CheckoutException(__('Some items do not support cash on delivery.'));
                    }
                }
            }

            // 2. Totals per store, in sen.
            $byStore = $lines->groupBy(fn ($line) => $line->variant->product->store_id);

            $subtotalSen = 0;
            $shippingTotalSen = 0;
            $storeTotals = [];

            foreach ($byStore as $storeId => $storeLines) {
                $store = $storeLines->first()->variant->product->store;
                $itemsSubtotal = $storeLines->sum(fn ($line) => $line->variant->effectivePriceSen() * $line->qty);
                $shippingFee = $this->shipping->feeForStore($store, $address->state, $itemsSubtotal);

                $storeTotals[$storeId] = [
                    'store' => $store,
                    'lines' => $storeLines,
                    'items_subtotal_sen' => $itemsSubtotal,
                    'shipping_fee_sen' => $shippingFee,
                ];

                $subtotalSen += $itemsSubtotal;
                $shippingTotalSen += $shippingFee;
            }

            // 3. Vouchers (M8 engine, docs/09 §B): one platform + one shop
            //    code, each re-validated and consumed under lockForUpdate in
            //    THIS transaction — quota can never oversell.
            $storeSubtotals = [];

            foreach ($storeTotals as $storeId => $data) {
                $storeSubtotals[$storeId] = $data['items_subtotal_sen'];
            }

            $platformDiscount = $platformCode !== null && $platformCode !== ''
                ? $this->vouchers->validate($platformCode, $buyer, $storeSubtotals, VoucherScope::Platform, lock: true)
                : null;

            $shopDiscount = $shopCode !== null && $shopCode !== ''
                ? $this->vouchers->validate($shopCode, $buyer, $storeSubtotals, VoucherScope::Shop, lock: true)
                : null;

            // Free-shipping vouchers zero the flagged stores' fees. Track what
            // each slot actually waived for its usage row.
            $waivedSen = ['platform' => 0, 'shop' => 0];

            foreach (['platform' => $platformDiscount, 'shop' => $shopDiscount] as $slot => $discount) {
                foreach ($discount?->freeShippingStoreIds ?? [] as $storeId) {
                    $waivedSen[$slot] += $storeTotals[$storeId]['shipping_fee_sen'];
                    $shippingTotalSen -= $storeTotals[$storeId]['shipping_fee_sen'];
                    $storeTotals[$storeId]['shipping_fee_sen'] = 0;
                }
            }

            // Platform share lives at order level (discount_total_sen); the
            // shop discount lands on its sub-order's shop_discount_sen below.
            $discountTotalSen = min($platformDiscount?->totalDiscountSen ?? 0, $subtotalSen);
            $shopDiscountTotalSen = $shopDiscount?->totalDiscountSen ?? 0;

            foreach ([$platformDiscount, $shopDiscount] as $discount) {
                $discount?->voucher->increment('used_count');
            }

            $grandTotalSen = $subtotalSen + $shippingTotalSen - $discountTotalSen - $shopDiscountTotalSen;

            if ($grandTotalSen <= 0) {
                throw new CheckoutException(__('Order total must be above zero.'));
            }

            if ($method === PaymentMethod::Cod && $grandTotalSen > $this->codSettings->max_order_sen) {
                throw new CheckoutException(__('Cash on delivery is limited to orders up to :max.', [
                    'max' => Money::format($this->codSettings->max_order_sen),
                ]));
            }

            // 4. Parent order with the address snapshot.
            $order = Order::create([
                'order_no' => Order::generateOrderNo(),
                'user_id' => $buyer->id,
                'payment_method' => $method,
                'payment_status' => PaymentStatus::Pending,
                'shipping_address' => $address->toSnapshot(),
                'subtotal_sen' => $subtotalSen,
                'shipping_total_sen' => $shippingTotalSen,
                'discount_total_sen' => $discountTotalSen,
                'grand_total_sen' => $grandTotalSen,
                'display_currency' => session('display_currency', 'MYR'),
                'display_rate' => 1,
                'placed_at' => now(),
                'expires_at' => $method === PaymentMethod::Ipay88
                    ? now()->addMinutes($this->orderSettings->unpaid_expiry_minutes)
                    : null,
            ]);

            // Platform usage → order_id. For free shipping the "discount" is
            // the shipping it waived.
            if ($platformDiscount !== null) {
                $platformDiscount->voucher->usages()->create([
                    'user_id' => $buyer->id,
                    'order_id' => $order->id,
                    'discount_sen' => $discountTotalSen + $waivedSen['platform'],
                    'created_at' => now(),
                ]);
            }

            // 5–7. Sub-orders, snapshot items, stock decrement.
            foreach ($storeTotals as $storeId => $data) {
                /** @var Store $store */
                $store = $data['store'];

                // Commission snapshot: dominant category (largest items subtotal)
                // feeds the category chain of the resolver.
                $dominantCategory = $data['lines']
                    ->groupBy(fn ($line) => $line->variant->product->category_id)
                    ->map(fn ($group) => $group->sum(fn ($line) => $line->variant->effectivePriceSen() * $line->qty))
                    ->sortDesc()
                    ->keys()
                    ->first();

                $category = $data['lines']
                    ->first(fn ($line) => $line->variant->product->category_id === $dominantCategory)
                    ?->variant->product->category;

                $shopDiscountSen = $shopDiscount?->discountForStore((int) $storeId) ?? 0;

                $subOrder = SubOrder::create([
                    'sub_order_no' => SubOrder::generateSubOrderNo(),
                    'order_id' => $order->id,
                    'store_id' => $storeId,
                    'status' => $method === PaymentMethod::Cod
                        ? SubOrderStatus::Confirmed
                        : SubOrderStatus::PendingPayment,
                    'items_subtotal_sen' => $data['items_subtotal_sen'],
                    'shipping_fee_sen' => $data['shipping_fee_sen'],
                    'shop_discount_sen' => $shopDiscountSen,
                    'total_sen' => $data['items_subtotal_sen'] + $data['shipping_fee_sen'] - $shopDiscountSen,
                    'commission_rate' => $this->commission->resolve($store, $category),
                ]);

                // Shop usage → sub_order_id, written once the sub-order exists.
                if ($shopDiscount !== null && (int) $shopDiscount->voucher->store_id === (int) $storeId) {
                    $shopDiscount->voucher->usages()->create([
                        'user_id' => $buyer->id,
                        'order_id' => $order->id,
                        'sub_order_id' => $subOrder->id,
                        'discount_sen' => $shopDiscountSen + $waivedSen['shop'],
                        'created_at' => now(),
                    ]);
                }

                foreach ($data['lines'] as $line) {
                    $variant = $line->variant;
                    $unitPrice = $variant->effectivePriceSen();

                    $subOrder->items()->create([
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'product_name' => $variant->product->getTranslation('name', app()->getLocale())
                            ?: $variant->product->getTranslation('name', 'en'),
                        'variant_label' => $variant->options_label,
                        'unit_price_sen' => $unitPrice,
                        'qty' => $line->qty,
                        'line_total_sen' => $unitPrice * $line->qty,
                    ]);

                    $variant->decrement('stock', $line->qty);
                }

                $this->statusService->initial($subOrder, ActorType::Buyer, $buyer->id);
            }

            // 8. Payment row.
            Payment::create([
                'order_id' => $order->id,
                'gateway' => $method,
                'ref_no' => $order->order_no,
                'amount_sen' => $grandTotalSen,
                'currency' => 'MYR',
                'status' => GatewayPaymentStatus::Pending,
            ]);

            // 9. Purchased cart lines leave the cart.
            $buyer->cart?->items()->where('selected', true)->delete();

            return $order->fresh(['subOrders.items', 'payment']);
        });
    }
}
