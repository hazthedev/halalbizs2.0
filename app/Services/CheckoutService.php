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
use App\Exceptions\PurchasingDisabledException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\FlashSaleItem;
use App\Models\GroupBuyMember;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Models\Voucher;
use App\Settings\CodSettings;
use App\Settings\GeneralSettings;
use App\Settings\OrderSettings;
use App\Support\CheckoutQuote;
use App\Support\CoinRedemptionResult;
use App\Support\Money;
use App\Support\VoucherDiscount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The checkout transaction (docs/06 §B). Hard rules enforced here:
 * integer sen money, atomic stock + voucher consumption under
 * lockForUpdate, snapshot order items, status only via the service.
 */
class CheckoutService
{
    /** Open COD orders a buyer may hold before the next one is refused (docs/01 §3.5). */
    public const MAX_OPEN_COD_ORDERS = 3;

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
        private GeneralSettings $generalSettings,
        private Ipay88Service $ipay88,
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
        if (! $this->generalSettings->purchasing_enabled) {
            throw new PurchasingDisabledException(__('This marketplace is currently in listing-only mode. Purchasing is unavailable.'));
        }

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
            //    Canonical lock order, extended: cart → buyer (COD only) →
            //    variants → flash → group-buy → vouchers → wallet. The cart lock is taken FIRST,
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

            if ($method === PaymentMethod::Cod) {
                User::query()->whereKey($buyer->getKey())->lockForUpdate()->firstOrFail();

                if ($this->openCodOrderCount($buyer) >= self::MAX_OPEN_COD_ORDERS) {
                    throw new CheckoutException(__('You have open cash-on-delivery orders; receive them before placing another.'));
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
                $line = (object) [
                    'variant' => $variants->get($item->product_variant_id),
                    'qty' => $item->qty,
                    'forcedPriceSen' => $item->forced_price_sen ?? null,
                ];

                // Same refusals the quote drops a line for (blockReason() is
                // the one home): withdrawn product, closed store, short stock.
                $reason = $this->blockReason($line);

                if ($reason !== null) {
                    throw new CheckoutException($reason);
                }

                $lines->push($line);
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
            //    priceByStore() is the one home for these numbers — quote()
            //    previews with exactly the same code.
            $storeTotals = $this->priceByStore($lines, $buyer, $address, $flashItems, $groupMemberships);

            // 3. Vouchers (M8 engine, docs/09 §B): one platform + one shop
            //    code, each re-validated and consumed under lockForUpdate in
            //    THIS transaction — quota can never oversell.
            $storeSubtotals = $this->storeSubtotals($storeTotals);

            $platformDiscount = $this->voucherFor($platformCode, $buyer, $storeSubtotals, VoucherScope::Platform, lock: true);
            $shopDiscount = $this->voucherFor($shopCode, $buyer, $storeSubtotals, VoucherScope::Shop, lock: true);

            // M1.1: the dedicated free-shipping slot — stacks with the platform
            // discount + shop voucher (Shopee model). Must be a FreeShipping voucher.
            $shippingDiscount = $this->voucherFor($shippingCode, $buyer, $storeSubtotals, null, lock: true);

            // Order-level sums after the free-shipping waiver (once per store,
            // tracked per slot for its usage row + the sub-order subsidy). The
            // platform share lives at order level (discount_total_sen); the
            // shop discount lands on its sub-order's shop_discount_sen below.
            [
                'subtotal_sen' => $subtotalSen,
                'shipping_total_sen' => $shippingTotalSen,
                'tax_total_sen' => $taxTotalSen,
                'waived_sen' => $waivedSen,
                'platform_discount_sen' => $discountTotalSen,
                'shop_discount_sen' => $shopDiscountTotalSen,
                'pre_coin_total_sen' => $preCoinTotalSen,
            ] = $this->totals($storeTotals, [
                'platform' => $platformDiscount, 'shop' => $shopDiscount, 'shipping' => $shippingDiscount,
            ]);

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
                ]);
            }

            // Free-shipping slot usage → order level (the waived shipping is the "discount").
            if ($shippingDiscount !== null && $slotOwnsVoucher['shipping']) {
                $shippingDiscount->voucher->usages()->create([
                    'user_id' => $buyer->id,
                    'order_id' => $order->id,
                    'discount_sen' => $waivedSen['shipping'],
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

    /**
     * Preview exactly what place() would charge for the buyer's selected cart
     * lines — same pricing, shipping, tax, voucher, free-shipping and coin
     * code paths, read without locks and without consuming anything. The web
     * checkout page paints from this; the API returns its toArray().
     *
     * Applied voucher codes that no longer validate are dropped and reported
     * in CheckoutQuote::$voucherErrors (keyed platform/shop/shipping) instead
     * of throwing, so the page can show the reason and keep the rest.
     *
     * @param  int  $coinsToRedeem  Loyalty Coins the buyer wants to spend; capped like place()
     * @param  PaymentMethod|null  $method  the buyer's pick; falls back when it is unavailable
     */
    public function quote(
        User $buyer,
        ?Address $address,
        ?string $shippingCode = null,
        ?string $platformCode = null,
        ?string $shopCode = null,
        int $coinsToRedeem = 0,
        ?PaymentMethod $method = null,
    ): CheckoutQuote {
        $lines = $this->selectedGroups($buyer)->flatten(1);
        $variantIds = $lines->map(fn ($line) => (int) $line->variant->id)->all();

        $storeTotals = $this->priceByStore(
            $lines,
            $buyer,
            $address,
            $this->flash->liveItemsFor($variantIds),
            $this->groupBuy->redeemableFor($buyer, $variantIds),
        );

        $storeSubtotals = $this->storeSubtotals($storeTotals);

        // Re-validate every applied voucher on each quote so the discount can
        // never go stale between apply and submit.
        $discounts = [];
        $voucherErrors = [];

        foreach ([
            'platform' => [$platformCode, VoucherScope::Platform],
            'shop' => [$shopCode, VoucherScope::Shop],
            'shipping' => [$shippingCode, null],
        ] as $slot => [$code, $scope]) {
            try {
                $discounts[$slot] = $this->voucherFor($code, $buyer, $storeSubtotals, $scope, lock: false);
            } catch (CheckoutException $e) {
                $discounts[$slot] = null;
                $voucherErrors[$slot] = $e->getMessage();
            }
        }

        [
            'subtotal_sen' => $subtotalSen,
            'shipping_total_sen' => $shippingTotalSen,
            'tax_total_sen' => $taxTotalSen,
            'waived_sen' => $waivedSen,
            'platform_discount_sen' => $platformDiscountSen,
            'shop_discount_sen' => $shopDiscountSen,
            'pre_coin_total_sen' => $preCoinTotalSen,
        ] = $this->totals($storeTotals, $discounts);
        $shippingSubsidySen = (int) array_sum($waivedSen);

        // The same refusals place() makes before it writes anything.
        $blockReason = match (true) {
            $lines->isEmpty() => __('Your cart has no selected items.'),
            $preCoinTotalSen <= 0 => __('Order total must be above zero.'),
            default => null,
        };

        $coinsEnabled = $this->coins->enabled();
        $coinBalance = $coinsEnabled ? $this->coins->balance($buyer) : 0;
        $coinRedemption = $this->coins->previewRedemption($buyer, $coinsToRedeem, $preCoinTotalSen);

        $grandTotalSen = $preCoinTotalSen - $coinRedemption->sen;

        $codUnavailableReason = $this->codUnavailableReason($buyer, $lines, $grandTotalSen);
        $ipay88Enabled = $this->ipay88->isEnabled();

        if ($method === PaymentMethod::Ipay88 && ! $ipay88Enabled) {
            $method = PaymentMethod::Cod;
        }

        if ($method === PaymentMethod::Cod && $codUnavailableReason !== null) {
            $method = $ipay88Enabled ? PaymentMethod::Ipay88 : null;
        }

        $groups = collect($storeTotals)->map(fn (array $data) => (object) [
            'store' => $data['store'],
            'lines' => $data['lines']->values(),
            'itemsSubtotalSen' => $data['items_subtotal_sen'],
            // The buyer-facing fee: a waived store ships free whoever funds it.
            'shippingFeeSen' => $data['shipping_subsidy_sen'] > 0 ? 0 : $data['shipping_fee_sen'],
            'shippingSubsidySen' => $data['shipping_subsidy_sen'],
            'taxSen' => $data['tax_sen'],
        ])->values();

        [$platformVoucherOptions, $shopVoucherOptions, $bestPlatformVoucherId] = $this->voucherOptions($buyer, $storeSubtotals, $groups);

        return new CheckoutQuote(
            purchasingEnabled: $this->generalSettings->purchasing_enabled,
            groups: $groups,
            storeSubtotals: $storeSubtotals,
            subtotalSen: $subtotalSen,
            shippingTotalSen: $shippingTotalSen,
            shippingSubsidySen: $shippingSubsidySen,
            taxTotalSen: $taxTotalSen,
            platformDiscount: $discounts['platform'],
            shopDiscount: $discounts['shop'],
            shippingDiscount: $discounts['shipping'],
            platformDiscountSen: $platformDiscountSen,
            shopDiscountSen: $shopDiscountSen,
            voucherErrors: $voucherErrors,
            platformVoucherOptions: $platformVoucherOptions,
            shopVoucherOptions: $shopVoucherOptions,
            bestPlatformVoucherId: $bestPlatformVoucherId,
            coinsEnabled: $coinsEnabled,
            coinBalance: $coinBalance,
            coinsRedeemed: $coinRedemption->coins,
            coinRedemptionSen: $coinRedemption->sen,
            grandTotalSen: $grandTotalSen,
            codUnavailableReason: $codUnavailableReason,
            ipay88Enabled: $ipay88Enabled,
            paymentMethod: $method,
            blockReason: $blockReason,
        );
    }

    /**
     * Selected cart lines grouped by store — the only lines checkout sees.
     *
     * A line the basket already marks NO LONGER AVAILABLE must not be billed
     * for here. It used to: the basket decorated its lines with availability
     * and checkout read the undecorated ones, so a withdrawn item showed as a
     * normal payable line and pushed the total to an amount nobody could pay.
     *
     * @return Collection<int, Collection<int, object>> keyed by store_id
     */
    public function selectedGroups(User $buyer): Collection
    {
        return $this->cartService->groupedByStore($buyer)
            ->map(fn (Collection $lines) => $lines
                ->filter(fn ($line) => $line->selected && $this->blockReason($line) === null)
                ->values())
            ->filter(fn (Collection $lines) => $lines->isNotEmpty());
    }

    /**
     * Selected lines that CANNOT be bought, each with the `reason` place()
     * would refuse it for, so the page can name them instead of silently
     * dropping them — a total that quietly shrinks is its own bug.
     *
     * @return Collection<int, object>
     */
    public function blockedLines(User $buyer): Collection
    {
        return $this->cartService->groupedByStore($buyer)
            ->flatten(1)
            ->filter(fn ($line) => $line->selected)
            ->each(fn ($line) => $line->reason = $this->blockReason($line))
            ->filter(fn ($line) => $line->reason !== null)
            ->values();
    }

    /**
     * Why this line cannot be bought right now, or null. The one home for the
     * refusals: place() throws it, selectedGroups() drops the line for it.
     */
    private function blockReason(object $line): ?string
    {
        $variant = $line->variant;
        $product = $variant?->product;

        if ($product === null || ! $product->isLive()) {
            return __('An item in your cart is no longer available.');
        }

        $store = $product->store;

        if ($store === null || ! $store->isApproved() || $store->holiday_mode) {
            return __(':store is not accepting orders right now.', ['store' => $store?->name]);
        }

        if ($variant->stock < $line->qty) {
            $label = trim($product->getTranslation('name', 'en').' '.($variant->options_label ?? ''));

            return __(':item just sold out — only :stock left.', ['item' => $label, 'stock' => $variant->stock]);
        }

        return null;
    }

    /**
     * Price every line and total each store, in sen. The ONE home for unit
     * price (shelf / flash / group-buy / forced), per-line tax, weight and the
     * shipping fee — place() charges from it and quote() previews from it.
     * Each line object gains listPriceSen, unitPriceSen, taxSen, taxRateBp,
     * flashItem and groupMembership for the order_items snapshot.
     *
     * @param  Collection<int, object>  $lines  variant + qty (+ optional forcedPriceSen)
     * @param  Collection<int, FlashSaleItem>  $flashItems  keyed by variant id
     * @param  Collection<int, GroupBuyMember>  $groupMemberships  keyed by variant id
     * @return array<int, array{store: Store, lines: Collection<int, object>, items_subtotal_sen: int, shipping_fee_sen: ?int, shipping_subsidy_sen: int, tax_sen: int}>
     */
    private function priceByStore(Collection $lines, User $buyer, ?Address $address, Collection $flashItems, Collection $groupMemberships): array
    {
        $byStore = $lines->groupBy(fn ($line) => $line->variant->product->store_id);
        $jurisdiction = $address === null ? null : $this->tax->jurisdictionFor($address->country);
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

                $membership = $groupMemberships->get($line->variant->id);

                if (($line->forcedPriceSen ?? null) !== null) {
                    // Programmatic forced price (M2.8 subscription) wins outright —
                    // no flash/group-buy stacking on an automated replenishment.
                    $line->groupMembership = null;
                    $line->flashItem = null;
                    $line->unitPriceSen = (int) $line->forcedPriceSen;
                } elseif ($membership !== null) {
                    // Group-buy: a redeemed (unlocked) membership prices the whole
                    // line at the deal price and supersedes any flash deal.
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
                // order_items snapshot is written. No address yet = no tax yet.
                $line->taxRateBp = $jurisdiction === null ? 0 : $this->tax->rateBpFor($class, $registered, $jurisdiction);
                $line->taxSen = $jurisdiction === null ? 0 : $this->tax->lineTaxSen($lineTotal, $class, $registered, $jurisdiction);

                $itemsSubtotal += $lineTotal;
                $taxSen += $line->taxSen;
                $weightGrams += (int) ($line->variant->product->weight_grams ?? 0) * $line->qty;
            }

            $storeTotals[$storeId] = [
                'store' => $store,
                'lines' => $storeLines,
                'items_subtotal_sen' => $itemsSubtotal,
                'shipping_fee_sen' => $address === null
                    ? null
                    : $this->shipping->feeForStore($store, $address->state, $itemsSubtotal, $address->postcode, $weightGrams),
                'shipping_subsidy_sen' => 0,
                'tax_sen' => $taxSen,
            ];
        }

        return $storeTotals;
    }

    /**
     * Order-level sums from the priced stores, after the free-shipping waiver.
     * The one home for subtotal, shipping, tax, both discounts and the
     * pre-coin total — place() charges them and quote() previews them.
     *
     * @param  array<int, array<string, mixed>>  $storeTotals  mutated by the waiver
     * @param  array<string, ?VoucherDiscount>  $discounts  keyed platform/shop/shipping
     * @return array{subtotal_sen: int, shipping_total_sen: int, tax_total_sen: int, waived_sen: array<string, int>, platform_discount_sen: int, shop_discount_sen: int, pre_coin_total_sen: int}
     */
    private function totals(array &$storeTotals, array $discounts): array
    {
        $subtotalSen = (int) array_sum(array_column($storeTotals, 'items_subtotal_sen'));
        $shippingTotalSen = (int) array_sum(array_column($storeTotals, 'shipping_fee_sen'));
        $taxTotalSen = (int) array_sum(array_column($storeTotals, 'tax_sen'));

        $waivedSen = $this->applyFreeShipping($storeTotals, $discounts);
        $shippingTotalSen -= (int) array_sum($waivedSen);

        $platformDiscountSen = $this->vouchers->platformDiscountSen($discounts['platform'], $discounts['shop'], $subtotalSen);
        $shopDiscountSen = $discounts['shop']?->totalDiscountSen ?? 0;

        return [
            'subtotal_sen' => $subtotalSen,
            'shipping_total_sen' => $shippingTotalSen,
            'tax_total_sen' => $taxTotalSen,
            'waived_sen' => $waivedSen,
            'platform_discount_sen' => $platformDiscountSen,
            'shop_discount_sen' => $shopDiscountSen,
            'pre_coin_total_sen' => $subtotalSen + $shippingTotalSen + $taxTotalSen - $platformDiscountSen - $shopDiscountSen,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $storeTotals
     * @return array<int, int> [store_id => items_subtotal_sen]
     */
    private function storeSubtotals(array $storeTotals): array
    {
        $storeSubtotals = [];

        foreach ($storeTotals as $storeId => $data) {
            $storeSubtotals[$storeId] = $data['items_subtotal_sen'];
        }

        return $storeSubtotals;
    }

    /**
     * Validate one voucher slot. A null scope is the free-shipping slot, which
     * must hold a FreeShipping voucher. Empty code = empty slot.
     *
     * @param  array<int, int>  $storeSubtotals
     *
     * @throws CheckoutException
     */
    private function voucherFor(?string $code, User $buyer, array $storeSubtotals, ?VoucherScope $scope, bool $lock): ?VoucherDiscount
    {
        if ($code === null || $code === '') {
            return null;
        }

        $discount = $this->vouchers->validate($code, $buyer, $storeSubtotals, $scope, lock: $lock);

        if ($scope === null && $discount->voucher->type !== VoucherType::FreeShipping) {
            throw new CheckoutException(__('That voucher is not a free-shipping voucher.'));
        }

        return $discount;
    }

    /**
     * Free-shipping vouchers zero the flagged stores' fees (once each). Marks
     * each waived store's shipping_subsidy_sen and, for a seller-funded
     * voucher, zeroes its shipping_fee_sen. Returns the sen each slot waived.
     *
     * @param  array<int, array<string, mixed>>  $storeTotals  mutated in place
     * @param  array<string, ?VoucherDiscount>  $discounts  keyed platform/shop/shipping
     * @return array<string, int> [slot => waived_sen]
     */
    private function applyFreeShipping(array &$storeTotals, array $discounts): array
    {
        $waivedSen = ['platform' => 0, 'shop' => 0, 'shipping' => 0];

        foreach ($discounts as $slot => $discount) {
            foreach ($discount?->freeShippingStoreIds ?? [] as $storeId) {
                $fee = (int) ($storeTotals[$storeId]['shipping_fee_sen'] ?? 0);

                // Nothing to waive, or an earlier slot already waived it —
                // never double-count. The subsidy check is what catches the
                // second slot on a platform-funded waiver, where the fee the
                // seller still earns deliberately stays on the sub-order.
                if ($fee <= 0 || $storeTotals[$storeId]['shipping_subsidy_sen'] > 0) {
                    continue;
                }

                $waivedSen[$slot] += $fee;
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

        return $waivedSen;
    }

    /** COD orders the buyer still has in flight (unpaid through shipped). */
    private function openCodOrderCount(User $buyer): int
    {
        return Order::query()
            ->where('user_id', $buyer->id)
            ->where('payment_method', PaymentMethod::Cod->value)
            ->whereHas('subOrders', fn ($query) => $query->whereIn('status', [
                SubOrderStatus::PendingPayment->value,
                SubOrderStatus::Confirmed->value,
                SubOrderStatus::Processing->value,
                SubOrderStatus::Shipped->value,
            ]))
            ->count();
    }

    /**
     * Why COD is unavailable for this order, or null when it's allowed
     * (docs/01 §3.5: global switch, item flag, order cap, open-order limit).
     * place() enforces the same rules under its locks.
     *
     * @param  Collection<int, object>  $lines
     */
    private function codUnavailableReason(User $buyer, Collection $lines, int $grandTotalSen): ?string
    {
        if (! $this->codSettings->enabled) {
            return __('COD unavailable: cash on delivery is switched off right now.');
        }

        if (! $lines->every(fn ($line) => $line->variant->product->cod_enabled)) {
            return __("COD unavailable: some selected items don't support cash on delivery.");
        }

        if ($grandTotalSen > $this->codSettings->max_order_sen) {
            return __('COD unavailable: order exceeds :max', ['max' => Money::format($this->codSettings->max_order_sen)]);
        }

        if ($this->openCodOrderCount($buyer) >= self::MAX_OPEN_COD_ORDERS) {
            return __('You have open cash-on-delivery orders; receive them before placing another.');
        }

        return null;
    }

    /**
     * Picker data: live vouchers a buyer could use on this cart — platform
     * plus shop vouchers for the cart's stores — with min-spend met/unmet
     * and the best-savings hint (docs/09 §B).
     *
     * @param  array<int, int>  $storeSubtotals
     * @param  Collection<int, object>  $groups
     * @return array{0: Collection<int, object>, 1: Collection<int, object>, 2: ?int}
     */
    private function voucherOptions(User $buyer, array $storeSubtotals, Collection $groups): array
    {
        $storeNames = $groups->mapWithKeys(fn ($group) => [$group->store->id => $group->store->name]);

        $options = Voucher::query()
            ->visibleTo($buyer)
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->where(function ($query) use ($storeSubtotals) {
                $query->where(fn ($inner) => $inner->where('scope', VoucherScope::Platform)->whereNull('store_id'))
                    ->orWhere(fn ($inner) => $inner->where('scope', VoucherScope::Shop)->whereIn('store_id', array_keys($storeSubtotals)));
            })
            ->withCount(['usages as user_usage_count' => fn ($query) => $query->where('user_id', $buyer->id)])
            ->orderBy('min_spend_sen')
            ->get()
            ->filter(fn (Voucher $voucher) => $voucher->hasQuotaRemaining() && $voucher->user_usage_count < $voucher->per_user_limit)
            ->map(function (Voucher $voucher) use ($storeSubtotals, $storeNames) {
                $basisSen = $voucher->scope === VoucherScope::Platform
                    ? (int) array_sum($storeSubtotals)
                    : (int) ($storeSubtotals[$voucher->store_id] ?? 0);

                $met = $basisSen >= $voucher->min_spend_sen;

                return (object) [
                    'voucher' => $voucher,
                    'storeName' => $voucher->store_id !== null ? $storeNames->get($voucher->store_id) : null,
                    'label' => $this->voucherLabel($voucher),
                    'minSpendMet' => $met,
                    'shortBySen' => $met ? 0 : $voucher->min_spend_sen - $basisSen,
                    'discountSen' => $met ? min($voucher->discountSenFor($basisSen), $basisSen) : 0,
                    'freeShipping' => $voucher->type === VoucherType::FreeShipping,
                ];
            })
            ->values();

        [$platformOptions, $shopOptions] = $options->partition(fn ($option) => $option->voucher->scope === VoucherScope::Platform);

        $best = $platformOptions
            ->filter(fn ($option) => $option->minSpendMet && $option->discountSen > 0)
            ->sortByDesc('discountSen')
            ->first();

        return [$platformOptions->values(), $shopOptions->values(), $best?->voucher->id];
    }

    /** Buyer-facing one-liner: "RM 5.00 off" · "10% off, up to RM 20.00". */
    private function voucherLabel(Voucher $voucher): string
    {
        return match ($voucher->type) {
            VoucherType::Fixed => __(':amount off', ['amount' => Money::format((int) $voucher->value_sen)]),
            VoucherType::Percent => $voucher->max_discount_sen !== null
                ? __(':percent% off, up to :cap', [
                    'percent' => rtrim(rtrim((string) $voucher->percent, '0'), '.'),
                    'cap' => Money::format($voucher->max_discount_sen),
                ])
                : __(':percent% off', ['percent' => rtrim(rtrim((string) $voucher->percent, '0'), '.')]),
            VoucherType::FreeShipping => __('Free shipping'),
        };
    }
}
