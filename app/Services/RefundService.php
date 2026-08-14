<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\SubOrder;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;

/**
 * Refund mechanics for a sub-order (docs/ROADMAP.md M0.4). Centralises what the
 * admin return flow did inline and adds: partial (line-item) amounts, a
 * PROPORTIONAL ledger reversal that is exact for a full refund, payment refund
 * tracking, and a best-effort automated gateway refund (the recorded portal
 * reference / COD ledger fact is the fallback). Integer sen throughout.
 *
 * Reversal: tax is part of the seller's booked sale (LedgerService), so a full
 * refund reverses the whole sale (−total_sen) and the full commission; for a
 * partial refund both scale by amount/total in exact integer math.
 */
class RefundService
{
    public function __construct(
        private LedgerService $ledger,
        private SubOrderStatusService $status,
        private PaymentGatewayManager $gateways,
        private CoinService $coins,
        private VoucherService $vouchers,
        private AffiliateService $affiliates,
    ) {}

    public function refund(
        SubOrder $subOrder,
        int $amountSen,
        ActorType $actor,
        ?int $actorId,
        ?string $reference = null,
        bool $markRefunded = true,
    ): void {
        DB::transaction(function () use ($subOrder, $amountSen, $actor, $actorId, $reference, $markRefunded) {
            // H1 fix: re-fetch the sub-order under lockForUpdate FIRST, and
            // recompute the cap from THIS locked row. Two concurrent refunds
            // (admin double-click, or a manual refund racing an automated one)
            // must not both read refunded_sen=0, both pass the cap, and both
            // refund/increment — the previous code read the cap before the
            // transaction even opened, so neither refund ever saw the other's
            // write. Every subsequent read below uses this locked instance.
            $subOrder = SubOrder::whereKey($subOrder->getKey())->lockForUpdate()->first();

            $total = (int) $subOrder->total_sen;

            // Idempotency + partial cap: this sub-order can only ever refund up to
            // its own total, cumulatively. A repeated (or racing) call with nothing
            // left to refund is a no-op — the previous code re-ran the whole
            // reversal every call and drove refunded_sen past what was charged.
            $alreadyRefunded = (int) $subOrder->refunded_sen;
            $sellerRefundable = max(0, $total - $alreadyRefunded);
            $amountSen = max(0, min($amountSen, $sellerRefundable));

            if ($amountSen <= 0) {
                return;
            }

            $online = $subOrder->order->payment_method === PaymentMethod::Ipay88;

            // 1. Ledger reversal — only once the sale was booked (completed). The
            //    seller was credited the full sub-order total, so this side reverses
            //    the sub-order amount (per-sub-order), commission scaled in exact
            //    integer math.
            if ($total > 0
                && $subOrder->ledgerEntries()->where('type', LedgerEntryType::Sale)->exists()) {
                $commissionReversal = intdiv((int) $subOrder->commission_sen * $amountSen + intdiv($total, 2), $total);

                $this->ledger->adjustment(
                    $subOrder->store,
                    -$amountSen + $commissionReversal,
                    __('Refund :no', ['no' => $subOrder->sub_order_no]),
                    $subOrder,
                );
            }

            // 1b. Return the buyer's proportional share of any redeemed coins
            //     (M2.1 money integrity) — bounded so it can't over-reverse.
            $order = $subOrder->order;

            if ((int) $order->coin_redemption_sen > 0) {
                $payableBasisSen = (int) $order->grand_total_sen + (int) $order->coin_redemption_sen;
                $this->coins->reverseForRefund($order, $amountSen, $payableBasisSen);
            }

            // 2. Track on the payment + best-effort gateway refund. The buyer is
            //    only ever refunded the CASH they actually paid: order-level
            //    platform vouchers + coins lowered grand_total below the sum of the
            //    sub-order totals, so cap cumulative cash at grand_total. (The seller
            //    reversal above still uses the full sub-order amount — the platform,
            //    not the seller, funded that discount.)
            $payment = $subOrder->order->payment;

            if ($payment !== null) {
                // C11: the order-level cap alone is a CUMULATIVE ceiling, not an
                // allocation — refunding the first store of a multi-store order
                // returned its full sub-order total even though the platform
                // voucher had been prorated across every store, so that buyer got
                // back cash they never paid and the last store came up short.
                // Refund this store's own share: its total less the slice of the
                // order-level discount it carried, scaled for a partial refund.
                // The order-level cap stays as the backstop.
                $cashSen = min($amountSen, $this->storeCashShareSen($subOrder, $order, $amountSen));
                $cashSen = min($cashSen, max(0, (int) $order->grand_total_sen - (int) $payment->refunded_sen));

                if ($online && $cashSen > 0) {
                    $this->gateways->driver($subOrder->order->payment_method?->value)?->refund($payment, $cashSen, $reference);
                }

                if ($cashSen > 0) {
                    $payment->forceFill([
                        'refunded_sen' => (int) $payment->refunded_sen + $cashSen,
                        'refunded_at' => now(),
                    ])->save();
                }
            }

            // 2b. Record the refund against the sub-order (idempotency source of truth).
            $subOrder->increment('refunded_sen', $amountSen);

            // 2c. Void the affiliate's matching slice while the commission is
            //     still held. This sits here rather than on a status listener
            //     because a PARTIAL refund never changes the sub-order status —
            //     a listener would only ever catch the full-refund case, and
            //     partials are exactly where pro-rata matters.
            $this->affiliates->reduceForRefund($subOrder, $amountSen);

            // 3. Only a genuinely FULL refund moves the sub-order to the terminal
            //    Refunded state — a partial must not mislabel a half-refunded order
            //    as fully refunded (and trap it in a terminal status).
            $fullyRefunded = ($alreadyRefunded + $amountSen) >= $total;

            if ($markRefunded && $fullyRefunded && $this->status->canTransition($subOrder, SubOrderStatus::Refunded)) {
                $this->status->transition(
                    $subOrder,
                    SubOrderStatus::Refunded,
                    $actor,
                    $actorId,
                    $online && $reference !== null
                        ? __('Refunded via iPay88 portal — ref :ref', ['ref' => $reference])
                        : __('COD refund recorded as a ledger adjustment'),
                );
            }

            // 4. The order stops claiming to be Paid once every sub-order has
            //    reached a settled end state.
            //
            //    This used to live INSIDE the block above, which made it
            //    unreachable for the case it matters most in (H-3): a paid
            //    sub-order cancelled by the buyer is already terminal, so
            //    canTransition(…, Refunded) is false and the status change is
            //    skipped — taking the payment_status update with it. The order
            //    then read Paid with refunded_sen == total_sen, which is the
            //    reconciliation lie the finding is about. The rule belongs to
            //    the refund, not to the status change.
            //
            //    Settled means Refunded OR Cancelled — unchanged semantics, and
            //    deliberately not "payment.refunded_sen >= grand_total_sen":
            //    coins and platform-funded shipping mean the cash returned is
            //    legitimately less than the order total, so an amount test
            //    would stop flipping orders that flip correctly today.
            if ($fullyRefunded) {
                $order = $subOrder->order;

                $allSettled = $order->subOrders()
                    ->whereNotIn('status', [SubOrderStatus::Refunded, SubOrderStatus::Cancelled])
                    ->doesntExist();

                if ($allSettled) {
                    $order->update(['payment_status' => PaymentStatus::Refunded]);
                }
            }
        });
    }

    /**
     * The cash THIS store's buyer actually paid toward $amountSen of this
     * sub-order, scaled by the portion being refunded.
     *
     * THREE order-level reductions sit between SUM(sub_orders.total_sen) and the
     * cash that reached the gateway. C11 handled the first; H-4 found the other
     * two still missing, so a store was refunded money the buyer never paid:
     *   1. the platform voucher (`discount_total_sen`)
     *   2. coins (`coin_redemption_sen`) — grand_total is computed AFTER them
     *   3. platform-funded free shipping, which deliberately stays in the
     *      sub-order total because the seller still earns it
     * The order-level `grand_total - refunded` check at the call site is a
     * cumulative CEILING, not an allocation — on a multi-store order the first
     * store refunded fits under it easily and the last store comes up short.
     *
     * Prorated through the same VoucherService::prorate() the checkout used, so
     * the shares add back up exactly — re-deriving the split here rather than
     * storing it keeps one implementation of the rule (the C5 lesson: a
     * duplicated formula is a formula that drifts).
     */
    private function storeCashShareSen(SubOrder $subOrder, Order $order, int $amountSen): int
    {
        $subOrderTotalSen = (int) $subOrder->total_sen;

        if ($subOrderTotalSen <= 0) {
            return 0;
        }

        $storeId = (int) $subOrder->store_id;

        // 1. Platform voucher, on items_subtotal_sen — the weights checkout used.
        $discountShares = [];

        if ((int) $order->discount_total_sen > 0) {
            $discountShares = $this->vouchers->prorate(
                (int) $order->discount_total_sen,
                $order->subOrders->mapWithKeys(fn (SubOrder $row) => [(int) $row->store_id => (int) $row->items_subtotal_sen])->all(),
            );
        }

        // 2. What each store's buyer owed after the voucher and after removing
        //    shipping that was never charged.
        $bases = $order->subOrders->mapWithKeys(fn (SubOrder $row) => [
            (int) $row->store_id => max(0, (int) $row->total_sen
                - ($discountShares[(int) $row->store_id] ?? 0)
                - $this->unpaidShippingSen($row)),
        ])->all();

        // 3. Coins came off the whole post-voucher bill (CheckoutService's
        //    $preCoinTotalSen), so they prorate on those bases, not on subtotals.
        $coinShareSen = (int) $order->coin_redemption_sen > 0
            ? ($this->vouchers->prorate((int) $order->coin_redemption_sen, $bases)[$storeId] ?? 0)
            : 0;

        $storeCashSen = max(0, ($bases[$storeId] ?? 0) - $coinShareSen);

        // Scale for a partial refund; exact for a full one.
        return intdiv($amountSen * $storeCashSen, $subOrderTotalSen);
    }

    /**
     * Shipping sitting in this sub-order's total that the buyer was not charged.
     *
     * A PLATFORM-funded waiver leaves `shipping_fee_sen` on the sub-order (the
     * seller still earns it) while the ORDER's shipping_total drops — so the
     * buyer paid nothing and this must come out. A SELLER-funded waiver zeroes
     * `shipping_fee_sen` instead, so it was never in `total_sen` and removing it
     * again would under-refund. min() separates the two without re-reading the
     * voucher: platform-funded leaves fee == subsidy, seller-funded leaves 0.
     */
    private function unpaidShippingSen(SubOrder $subOrder): int
    {
        return min((int) $subOrder->shipping_fee_sen, (int) $subOrder->shipping_subsidy_sen);
    }
}
