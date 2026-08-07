<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Enums\SubOrderStatus;
use App\Enums\TaxClass;
use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Exceptions\CheckoutException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Settings\CodSettings;
use App\Settings\OrderSettings;
use App\Support\CoinRedemptionResult;
use App\Support\Money;
use Illuminate\Support\Carbon;
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
        private TaxService $tax,
        private StockService $stock,
        private FlashSaleService $flash,
        private CoinService $coins,
        private GroupBuyService $groupBuy,
        private CodSettings $codSettings,
        private OrderSettings $orderSettings,
    ) {}

    /**
     * Stacking (docs/09 §B + M1.1, Shopee model): one platform discount + one
     * free-shipping + one shop voucher per order. $platformCode/$shopCode keep
     * their old positions; $shippingCode is the dedicated free-shipping slot.
     *
     * @param  array<int, string>  $sellerNotes  keyed by store_id
     * @param  int  $coinsToRedeem  Loyalty Coins to spend (M2.1); 0 = none. Capped
     *                              and consumed under the wallet lock inside this txn.
     * @param  array<int, array{variant_id: int, qty: int, price_sen?: int}>|null  $explicitLines
     *                                                                                             When given (M2.8 subscriptions / programmatic orders) these replace
     *                                                                                             the cart entirely — the cart is neither read nor cleared. A line's
     *                                                                                             optional price_sen forces the unit price (else normal pricing). When
     *                                                                                             null, behaviour is exactly the original cart checkout.
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
        ?string $shippingCode = null,
        int $coinsToRedeem = 0,
        ?array $explicitLines = null,
    ): Order {
        // Three attempts, because the stock lock at :133 is a genuine collision
        // point: two buyers checking out the SAME variant in the same instant is
        // ordinary traffic (a popular SKU, a flash sale), and InnoDB answers the
        // loser's `SELECT … FOR UPDATE` with 1020 "Record has changed since last
        // read … try restarting transaction" rather than blocking. With one
        // attempt that reached the buyer as a raw QueryException at the moment of
        // paying. Laravel already classifies 1020 as retryable
        // (ConcurrencyErrorDetector), so it only ever needed asking.
        //
        // Retry is side-effect-safe here: this service sends nothing itself, and
        // the queue is `database` on the SAME connection, so a rolled-back
        // attempt takes its queued jobs with it and the retry re-inserts exactly
        // one. A CheckoutException is not a concurrency error, so a genuine
        // refusal (quota gone, stock gone) still surfaces on the first pass.
        return DB::transaction(function () use ($buyer, $address, $method, $platformCode, $shopCode, $shippingCode, $sellerNotes, $coinsToRedeem, $explicitLines) {
            // 0. AL-M4 idempotency guard (cart-based checkout only — an
            //    explicit-lines/subscription order never touches a cart).
            //    Canonical lock order, extended: cart → variants → flash →
            //    group-buy → vouchers → wallet. The cart lock is taken FIRST,
            //    before the lines are even read, and it is a resource type no
            //    other step in this transaction ever locks — a strict prefix
            //    of the existing chain, so it can never introduce a deadlock
            //    cycle against variants/flash/group-buy/vouchers/wallet.
            //
            //    Locking here (instead of reading $buyer->cart unlocked) also
            //    closes the original TOCTOU: a concurrent duplicate submit for
            //    the SAME cart now blocks on this lock until the first
            //    submission commits, then sees its own lines already cleared
            //    and — if that happened very recently — returns the order the
            //    winner just placed instead of racing the stock check again.
            $cart = $explicitLines === null
                ? Cart::where('user_id', $buyer->id)->lockForUpdate()->first()
                : null;

            if ($cart !== null) {
                // checkout_locked_at isn't cast to a Carbon instance on the
                // Cart model (App\Models\Cart is outside this task's declared
                // surface) — parse the raw DB value explicitly instead.
                $isRecentDuplicate = $cart->checkout_lock_order_id !== null
                    && $cart->checkout_locked_at !== null
                    && Carbon::parse($cart->checkout_locked_at)->gt(now()->subMinute())
                    && $cart->items()->where('selected', true)->doesntExist();

                if ($isRecentDuplicate) {
                    $duplicate = Order::find($cart->checkout_lock_order_id);

                    if ($duplicate !== null) {
                        return $duplicate->fresh(['subOrders.items', 'payment']);
                    }
                }
            }

            // 1. Source the lines: an explicit set (subscriptions) or the selected
            //    cart (normal checkout). Synthetic items mirror cart-item fields.
            $items = $explicitLines !== null
                ? collect($explicitLines)->map(fn (array $line) => (object) [
                    'product_variant_id' => (int) $line['variant_id'],
                    'qty' => (int) $line['qty'],
                    'forced_price_sen' => isset($line['price_sen']) ? (int) $line['price_sen'] : null,
                ])
                : ($cart?->items()->where('selected', true)->get() ?? collect());

            if ($items->isEmpty()) {
                throw new CheckoutException(__('Your cart has no selected items.'));
            }

            $variants = ProductVariant::with(['product.store', 'product.category'])
                ->whereIn('id', $items->pluck('product_variant_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Live flash-sale deal lines, locked so allocation can't oversell.
            $flashItems = $this->flash->liveItemsFor($items->pluck('product_variant_id')->all(), lock: true);

            // Unlocked group-buy memberships the buyer can redeem (M2.6), locked
            // here — extends the canonical order to variants → flash → group-buy
            // → vouchers → wallet. Keyed by variant_id; supersedes flash on a line.
            $groupMemberships = $this->groupBuy->lockRedeemableFor($buyer, $items->pluck('product_variant_id')->all());

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

                $lines->push((object) [
                    'variant' => $variant,
                    'qty' => $item->qty,
                    'forcedPriceSen' => $item->forced_price_sen ?? null,
                ]);
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

            // 2. Totals per store, in sen. Tax (worldwide, jurisdiction-aware)
            //    is computed per line from destination × seller registration ×
            //    product tax class, and snapshotted onto the order (Hard Rule 5).
            $byStore = $lines->groupBy(fn ($line) => $line->variant->product->store_id);
            $jurisdiction = $this->tax->jurisdictionFor($address->country);

            $subtotalSen = 0;
            $shippingTotalSen = 0;
            $taxTotalSen = 0;
            $storeTotals = [];

            foreach ($byStore as $storeId => $storeLines) {
                $store = $storeLines->first()->variant->product->store;
                $registered = (bool) $store->sst_registered;
                $itemsSubtotal = 0;
                $taxSen = 0;
                $weightGrams = 0;

                foreach ($storeLines as $line) {
                    // Shelf price BEFORE any campaign price (flash / group-buy /
                    // forced subscription price) — snapshotted per line so a GROSS
                    // commission basis has something to charge on. unit_price_sen
                    // is already net of whichever campaign applied, so without this
                    // the pre-campaign price is simply not recoverable later.
                    //
                    // A standing sale_price counts as the shelf price, not a
                    // discount: effectivePriceSen() is what this line would have
                    // sold at today with no campaign attached.
                    $line->listPriceSen = $line->variant->effectivePriceSen();

                    // Programmatic forced price (M2.8 subscription) wins outright —
                    // no flash/group-buy stacking on an automated replenishment.
                    if (($line->forcedPriceSen ?? null) !== null) {
                        $line->groupMembership = null;
                        $line->flashItem = null;
                        $line->unitPriceSen = (int) $line->forcedPriceSen;
                        $lineTotal = $line->unitPriceSen * $line->qty;
                        $class = $line->variant->product->tax_class ?? TaxClass::Exempt;
                        $line->taxRateBp = $this->tax->rateBpFor($class, $registered, $jurisdiction);
                        $line->taxSen = $this->tax->lineTaxSen($lineTotal, $class, $registered, $jurisdiction);
                        $itemsSubtotal += $lineTotal;
                        $taxSen += $line->taxSen;
                        $weightGrams += (int) ($line->variant->product->weight_grams ?? 0) * $line->qty;

                        continue;
                    }

                    // Group-buy: a redeemed (unlocked) membership prices the whole
                    // line at the deal price and supersedes any flash deal.
                    $membership = $groupMemberships->get($line->variant->id);

                    if ($membership !== null) {
                        $line->groupMembership = $membership;
                        $line->flashItem = null;
                        $line->unitPriceSen = (int) $membership->team->groupBuy->group_price_sen;
                    } else {
                        // Flash price applies only when the deal is live and the whole
                        // line fits the per-buyer limit + remaining allocation; else
                        // the normal price (and no allocation is consumed).
                        $line->groupMembership = null;
                        $flashItem = $flashItems->get($line->variant->id);
                        // per_buyer_limit is CUMULATIVE across the buyer's orders,
                        // not per-order — otherwise splitting into N orders repeats
                        // the promo indefinitely.
                        $priorFlashQty = $flashItem !== null
                            ? $this->flash->buyerPurchasedQty($flashItem, $buyer)
                            : 0;
                        $useFlash = $flashItem !== null
                            && ($priorFlashQty + $line->qty) <= $flashItem->per_buyer_limit
                            && $flashItem->remaining() >= $line->qty;
                        $line->flashItem = $useFlash ? $flashItem : null;
                        $line->unitPriceSen = $useFlash ? $flashItem->promo_price_sen : $line->variant->effectivePriceSen();
                    }

                    $lineTotal = $line->unitPriceSen * $line->qty;
                    $class = $line->variant->product->tax_class ?? TaxClass::Exempt;

                    // Stash the per-line tax on the line object — reused when the
                    // order_items snapshot is written below.
                    $line->taxRateBp = $this->tax->rateBpFor($class, $registered, $jurisdiction);
                    $line->taxSen = $this->tax->lineTaxSen($lineTotal, $class, $registered, $jurisdiction);

                    $itemsSubtotal += $lineTotal;
                    $taxSen += $line->taxSen;
                    $weightGrams += (int) ($line->variant->product->weight_grams ?? 0) * $line->qty;
                }

                $shippingFee = $this->shipping->feeForStore($store, $address->state, $itemsSubtotal, $address->postcode, $weightGrams);

                $storeTotals[$storeId] = [
                    'store' => $store,
                    'lines' => $storeLines,
                    'items_subtotal_sen' => $itemsSubtotal,
                    'shipping_fee_sen' => $shippingFee,
                    'shipping_subsidy_sen' => 0,
                    'tax_sen' => $taxSen,
                ];

                $subtotalSen += $itemsSubtotal;
                $shippingTotalSen += $shippingFee;
                $taxTotalSen += $taxSen;
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

            // M1.1: the dedicated free-shipping slot — stacks with the platform
            // discount + shop voucher (Shopee model). Must be a FreeShipping voucher.
            $shippingDiscount = $shippingCode !== null && $shippingCode !== ''
                ? $this->vouchers->validate($shippingCode, $buyer, $storeSubtotals, null, lock: true)
                : null;

            if ($shippingDiscount !== null && $shippingDiscount->voucher->type !== VoucherType::FreeShipping) {
                throw new CheckoutException(__('That voucher is not a free-shipping voucher.'));
            }

            // Free-shipping vouchers zero the flagged stores' fees (once each).
            // Track what each slot waived for its usage row + the sub-order subsidy.
            $waivedSen = ['platform' => 0, 'shop' => 0, 'shipping' => 0];

            foreach (['platform' => $platformDiscount, 'shop' => $shopDiscount, 'shipping' => $shippingDiscount] as $slot => $discount) {
                foreach ($discount?->freeShippingStoreIds ?? [] as $storeId) {
                    $fee = $storeTotals[$storeId]['shipping_fee_sen'];

                    // Nothing to waive, or an earlier slot already waived it —
                    // never double-count. The subsidy check is what catches the
                    // second slot on a platform-funded waiver, where the fee the
                    // seller still earns deliberately stays on the sub-order.
                    if ($fee <= 0 || $storeTotals[$storeId]['shipping_subsidy_sen'] > 0) {
                        continue;
                    }

                    $waivedSen[$slot] += $fee;
                    $shippingTotalSen -= $fee;
                    $storeTotals[$storeId]['shipping_subsidy_sen'] = $fee;

                    // Who pays for the waiver. A seller-funded voucher zeroes the
                    // fee — that IS the seller's cost. A PLATFORM-funded one must
                    // not be charged to the seller: the buyer stops paying it
                    // (order shipping_total above), the sub-order keeps it, and
                    // the ledger credits it as part of the sale.
                    if (! $discount->voucher->isPlatformFunded()) {
                        $storeTotals[$storeId]['shipping_fee_sen'] = 0;
                    }
                }
            }

            // Platform share lives at order level (discount_total_sen); the
            // shop discount lands on its sub-order's shop_discount_sen below.
            $shopDiscountTotalSen = $shopDiscount?->totalDiscountSen ?? 0;
            $discountTotalSen = $this->vouchers->platformDiscountSen($platformDiscount, $shopDiscount, $subtotalSen);

            // AL-L1: the same voucher ROW can fill two slots at once — e.g. a
            // shop free-shipping code entered as both $shopCode and
            // $shippingCode, which VoucherService::lookup() resolves to the
            // same row under either scope (no buyer gain, since the waiver
            // loop above already skips an already-zeroed fee, but quota +
            // per_user_limit accounting must never count one applied voucher
            // twice). Consume each distinct voucher id at most once: the
            // first slot (platform, then shop, then shipping) to reference a
            // given id is its owner; a later slot pointing at the same row is
            // skipped entirely — no extra used_count increment, no extra
            // voucher_usages row.
            $consumedVoucherIds = [];
            $slotOwnsVoucher = [];

            foreach (['platform' => $platformDiscount, 'shop' => $shopDiscount, 'shipping' => $shippingDiscount] as $slot => $discount) {
                if ($discount === null) {
                    $slotOwnsVoucher[$slot] = false;

                    continue;
                }

                $slotOwnsVoucher[$slot] = ! in_array($discount->voucher->id, $consumedVoucherIds, true);

                if ($slotOwnsVoucher[$slot]) {
                    $consumedVoucherIds[] = $discount->voucher->id;
                    $discount->voucher->increment('used_count');
                }
            }

            // Coins (M2.1): the LAST link in the canonical lock order
            // (variants → flash-sale → vouchers → wallet). Capped to the wallet
            // balance + per-order ceiling and to leave ≥ 1 sen payable, so it can
            // never zero the bill or oversell the wallet. Default 0 → no-op.
            $preCoinTotalSen = $subtotalSen + $shippingTotalSen + $taxTotalSen - $discountTotalSen - $shopDiscountTotalSen;
            $coinRedemption = $coinsToRedeem > 0
                ? $this->coins->redeemForCheckout($buyer, $coinsToRedeem, $preCoinTotalSen)
                : CoinRedemptionResult::none();

            $grandTotalSen = $preCoinTotalSen - $coinRedemption->sen;

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
                'coin_redemption_sen' => $coinRedemption->sen,
                'tax_total_sen' => $taxTotalSen,
                'grand_total_sen' => $grandTotalSen,
                'display_currency' => session('display_currency', 'MYR'),
                'display_rate' => 1,
                'placed_at' => now(),
                'expires_at' => $method === PaymentMethod::Ipay88
                    ? now()->addMinutes($this->orderSettings->unpaid_expiry_minutes)
                    : null,
            ]);

            // Tie the coin debit to the order it paid for (snapshot reference).
            $coinRedemption->transaction?->forceFill([
                'reference_type' => $order->getMorphClass(),
                'reference_id' => $order->id,
            ])->save();

            // Platform usage → order_id. For free shipping the "discount" is
            // the shipping it waived. Gated on $slotOwnsVoucher (AL-L1) — a
            // slot whose voucher id was already consumed by an earlier slot
            // writes no usage row of its own.
            if ($platformDiscount !== null && $slotOwnsVoucher['platform']) {
                $platformDiscount->voucher->usages()->create([
                    'user_id' => $buyer->id,
                    'order_id' => $order->id,
                    'discount_sen' => $discountTotalSen + $waivedSen['platform'],
                    'created_at' => now(),
                ]);
            }

            // Free-shipping slot usage → order level (the waived shipping is the "discount").
            if ($shippingDiscount !== null && $slotOwnsVoucher['shipping']) {
                $shippingDiscount->voucher->usages()->create([
                    'user_id' => $buyer->id,
                    'order_id' => $order->id,
                    'discount_sen' => $waivedSen['shipping'],
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
                    ->map(fn ($group) => $group->sum(fn ($line) => $line->unitPriceSen * $line->qty))
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
                    'shipping_subsidy_sen' => $data['shipping_subsidy_sen'],
                    'tax_sen' => $data['tax_sen'],
                    'total_sen' => $data['items_subtotal_sen'] + $data['shipping_fee_sen'] - $shopDiscountSen + $data['tax_sen'],
                    'commission_rate' => $this->commission->resolve($store, $category),
                ]);

                // Shop usage → sub_order_id, written once the sub-order exists.
                if ($shopDiscount !== null && $slotOwnsVoucher['shop'] && (int) $shopDiscount->voucher->store_id === (int) $storeId) {
                    $shopDiscount->voucher->usages()->create([
                        'user_id' => $buyer->id,
                        'order_id' => $order->id,
                        'sub_order_id' => $subOrder->id,
                        'discount_sen' => $shopDiscountSen + $waivedSen['shop'],
                        'created_at' => now(),
                    ]);
                }

                // AL-L2: the buyer's per-store checkout note, if provided —
                // persisted via forceFill (not create()'s mass assignment) so
                // this doesn't require SubOrder::$fillable, which is outside
                // this task's declared surface.
                $buyerNote = trim((string) ($sellerNotes[$storeId] ?? ''));

                if ($buyerNote !== '') {
                    $subOrder->forceFill(['buyer_note' => $buyerNote])->save();
                }

                foreach ($data['lines'] as $line) {
                    $variant = $line->variant;
                    $unitPrice = $line->unitPriceSen; // flash-resolved above

                    $subOrder->items()->create([
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'group_buy_id' => $line->groupMembership?->team->group_buy_id,
                        'flash_sale_item_id' => $line->flashItem?->id,
                        'product_name' => $variant->product->getTranslation('name', app()->getLocale())
                            ?: $variant->product->getTranslation('name', 'en'),
                        'variant_label' => $variant->options_label,
                        'unit_price_sen' => $unitPrice,
                        'list_price_sen' => $line->listPriceSen,
                        'qty' => $line->qty,
                        'line_total_sen' => $unitPrice * $line->qty,
                        'tax_sen' => $line->taxSen,
                        'tax_rate_bp' => $line->taxRateBp,
                    ]);

                    $this->stock->apply($variant, -$line->qty, StockMovementType::Sale, $order->order_no);

                    // Consume the flash allocation under the same lock.
                    $line->flashItem?->increment('sold_qty', $line->qty);

                    // Burn the group-buy membership (one redemption per member).
                    if ($line->groupMembership !== null) {
                        $this->groupBuy->markPurchased($line->groupMembership, $subOrder);
                    }
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

            // 9. Purchased cart lines leave the cart (cart checkout only — an
            //    explicit-lines order never touches the buyer's cart). Stamp
            //    the idempotency marker (AL-M4) in the same breath, still
            //    inside this transaction and still under the cart lock taken
            //    in step 0.
            if ($cart !== null) {
                $cart->items()->where('selected', true)->delete();
                $cart->forceFill([
                    'checkout_lock_order_id' => $order->id,
                    'checkout_locked_at' => now(),
                ])->save();
            }

            return $order->fresh(['subOrders.items', 'payment']);
        }, attempts: 3);
    }
}
