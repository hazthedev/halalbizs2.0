<?php

namespace App\Services;

use App\Enums\CommissionBasis;
use App\Enums\LedgerEntryStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\PayoutStatus;
use App\Enums\ReturnStatus;
use App\Exceptions\CheckoutException;
use App\Models\Payout;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreLedgerEntry;
use App\Models\SubOrder;
use App\Settings\CommissionSettings;
use App\Settings\OrderSettings;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Seller escrow ledger (docs/09 §A). All amounts are SIGNED integer sen.
 * Balance = SUM(amount_sen) of available entries — payout requests write a
 * negative `payout` entry referencing the payout row, so the balance math
 * stays a single SUM. Rows are never deleted: rejection books a positive
 * `payout_reversal` entry against the same payout (PAY-08).
 */
class LedgerService
{
    public function __construct(
        private OrderSettings $orderSettings,
        private CommissionSettings $commissionSettings,
        private VoucherService $vouchers,
    ) {}

    /**
     * When completion money comes off hold: the same instant ReturnService
     * closes the return window: return_closes_at, fixed on delivery day
     * (HB-DEC-1123d). A sub-order delivered before that column existed (NULL)
     * uses delivered_at + the current return_window_days; one with no
     * delivered_at (defensive) holds from now.
     */
    public function releaseAt(SubOrder $subOrder): CarbonInterface
    {
        return $subOrder->return_closes_at
            ?? ($subOrder->delivered_at ?? now())->copy()->addDays($this->orderSettings->return_window_days);
    }

    /**
     * Completion hook: +sale, −commission, and for COD −cod_offset (the
     * seller already holds the cash). The offset is the cash the courier
     * actually collected, so platform-funded discounts (platform voucher,
     * coins, platform free shipping) stay credited to the seller exactly as
     * on an online order: HB-DEC-1124d (1), PAY-09 / HB-G026.
     * Also the moment sold_count becomes truth.
     */
    public function recordCompletion(SubOrder $subOrder): void
    {
        DB::transaction(function () use ($subOrder) {
            if ($subOrder->ledgerEntries()->where('type', LedgerEntryType::Sale)->exists()) {
                return; // idempotent — completion fires once, but be safe
            }

            // Tax collected belongs to the (registered) seller to remit — it is
            // part of the sale they receive, but commission is charged on the
            // pre-tax items subtotal only.
            $saleSen = $subOrder->items_subtotal_sen + $subOrder->shipping_fee_sen - $subOrder->shop_discount_sen + $subOrder->tax_sen;

            // HB-G130: a refund before completion wrote no ledger reversal (no
            // Sale yet), so book net of it: sale less what was refunded, and
            // commission scaled exactly as RefundService scales a reversal. The
            // snapshot below stays GROSS: later refunds scale from it. COD offset
            // is the FULL cash collected too, matching a post-completion refund
            // (seller debited).
            // A sub-order refunded in full before completion books nothing.
            $totalSen = (int) $subOrder->total_sen;
            $refundedSen = min((int) $subOrder->refunded_sen, $totalSen);

            $fullyRefunded = $totalSen > 0 && $refundedSen >= $totalSen;

            $basis = $this->commissionSettings->basis();
            $commissionSen = $this->commissionSen($subOrder, $basis);
            $commissionBookedSen = $refundedSen > 0
                ? $commissionSen - intdiv($commissionSen * $refundedSen + intdiv($totalSen, 2), $totalSen)
                : $commissionSen;

            // Snapshot the basis alongside the rate — hard rule #5. The setting
            // is flippable, so without this a historical commission cannot be
            // explained after someone changes it. HB-G134: stamped even when
            // fully refunded (commission 0, nothing booked), so reports read a
            // settled zero instead of null.
            $subOrder->forceFill([
                'commission_sen' => $fullyRefunded ? 0 : $commissionSen,
                'commission_basis' => $basis,
            ])->save();

            if ($fullyRefunded) {
                return;
            }

            // H2 DB-level backstop: Sale/Commission are once-per-sub-order
            // settlement entries, so they carry a unique `settlement_key`
            // ("sale:{id}" / "commission:{id}") — NULL (unconstrained) for every
            // repeatable type (adjustment/payout/cod_offset). The exists() guard
            // above is not itself race-proof (two concurrent completions can both
            // pass it before either writes), so a lost race here surfaces as a
            // unique-constraint violation, which must be a clean no-op, not a 500.
            $holdUntil = $this->releaseAt($subOrder);

            try {
                $this->write($subOrder->store_id, LedgerEntryType::Sale, $saleSen - $refundedSen, $subOrder->id,
                    __('Sale :no', ['no' => $subOrder->sub_order_no]), "sale:{$subOrder->id}", $holdUntil);

                $this->write($subOrder->store_id, LedgerEntryType::Commission, -$commissionBookedSen, $subOrder->id,
                    __('Commission :rate% on :no', ['rate' => $subOrder->commission_rate, 'no' => $subOrder->sub_order_no]), "commission:{$subOrder->id}", $holdUntil);
            } catch (QueryException $e) {
                if ($this->isUniqueViolation($e)) {
                    return; // another completion already booked this sub-order — no-op
                }

                throw $e;
            }

            if ($subOrder->order->payment_method === PaymentMethod::Cod) {
                $this->write($subOrder->store_id, LedgerEntryType::CodOffset, -$this->buyerCashShareSen($subOrder, $totalSen), $subOrder->id,
                    __('COD cash collected for :no', ['no' => $subOrder->sub_order_no]), null, $holdUntil);
            }

            foreach ($subOrder->items as $item) {
                $item->product?->increment('sold_count', $item->qty);
            }
        });
    }

    /**
     * The commission completion books for this sub-order under [basis]
     * (default: the current setting). Before completion it is a preview: the
     * basis can still change, so the seller API flags it as not settled.
     */
    public function commissionSen(SubOrder $subOrder, ?CommissionBasis $basis = null): int
    {
        $baseSen = $this->commissionBaseSen($subOrder, $basis ?? $this->commissionSettings->basis());

        // round(base × rate%) with integer math: rate is decimal(5,2).
        $rateBasisPoints = (int) round((float) $subOrder->commission_rate * 100); // e.g. 5.25% → 525

        return intdiv($baseSen * $rateBasisPoints + 5000, 10000);
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
     * RefundService's order-level `grand_total - refunded` check is a
     * cumulative CEILING, not an allocation — on a multi-store order the first
     * store refunded fits under it easily and the last store comes up short.
     *
     * Also the COD offset: what the courier collected for this sub-order is
     * this share of its full total (PAY-09).
     *
     * Prorated through the same VoucherService::prorate() the checkout used, so
     * the shares add back up exactly — re-deriving the split here rather than
     * storing it keeps one implementation of the rule (the C5 lesson: a
     * duplicated formula is a formula that drifts).
     */
    public function buyerCashShareSen(SubOrder $subOrder, int $amountSen): int
    {
        $order = $subOrder->order;
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

    private function isUniqueViolation(QueryException $e): bool
    {
        // PDO normalizes both MySQL's duplicate-key error and SQLite's UNIQUE
        // constraint failure to SQLSTATE 23000 (integrity constraint violation).
        return $e->getCode() === '23000'
            || str_contains(strtolower($e->getMessage()), 'unique');
    }

    /**
     * What the rate is charged on, per the configurable basis (docs/09 §A).
     *
     * Only SELLER-funded discounts move this. Platform vouchers never do —
     * `total_sen` does not subtract them, so the seller is paid in full and
     * charging on the full price is right under either basis.
     *
     * - NET   → what the seller was actually paid for the goods:
     *           items_subtotal (already net of any flash/group-buy price) minus
     *           their own shop voucher.
     * - GROSS → the shelf price the goods carried before ANY seller-funded
     *           discount, from the per-line list_price_sen snapshot. Falls back
     *           to items_subtotal for pre-snapshot orders, which is the closest
     *           honest answer available for them.
     *
     * Note under GROSS: a deep shop voucher can make the commission exceed what
     * the sale credits, pushing the seller's balance negative. That is inherent
     * to "your discount is your own marketing spend", not a bug — it is the
     * reason the basis is a deliberate choice rather than a default.
     */
    private function commissionBaseSen(SubOrder $subOrder, CommissionBasis $basis): int
    {
        if ($basis === CommissionBasis::Net) {
            return max(0, $subOrder->items_subtotal_sen - $subOrder->shop_discount_sen);
        }

        $listTotalSen = 0;
        $anySnapshot = false;

        foreach ($subOrder->items as $item) {
            if ($item->list_price_sen !== null) {
                $anySnapshot = true;
                $listTotalSen += $item->list_price_sen * $item->qty;

                continue;
            }

            // Legacy line with no snapshot — the paid price is the best truth
            // there is for it. Never invent a pre-campaign price.
            $listTotalSen += $item->unit_price_sen * $item->qty;
        }

        // No items at all (defensive) → fall back rather than charge zero.
        return $anySnapshot || $subOrder->items->isNotEmpty()
            ? $listTotalSen
            : $subOrder->items_subtotal_sen;
    }

    /**
     * Post-completion refunds: signed admin adjustment reversing sale and
     * commission proportionally (docs/09 §D).
     */
    public function adjustment(Store $store, int $amountSen, string $reason, ?SubOrder $subOrder = null): void
    {
        // M-8: the store lock was asymmetric. requestPayout and chargeBoost both
        // take it before reading the derived SUM(amount_sen) balance; this
        // writer — the one that REDUCES a balance, reached from
        // RefundService::refund — took neither a lock nor a transaction. A
        // refund landing between a payout's balance read and its write let the
        // payout pay out money the refund had already clawed back.
        //
        // Locking here rather than at the two call sites covers both of them,
        // and anything that reaches this method later.
        DB::transaction(function () use ($store, $amountSen, $subOrder, $reason) {
            Store::whereKey($store->id)->lockForUpdate()->first();

            $this->write($store->id, LedgerEntryType::Adjustment, $amountSen, $subOrder?->id, $reason);
        });
    }

    /**
     * Full COD refund before completion (RefundService): the platform paid the
     * buyer back, the seller keeps the cash the courier collected and owes it
     * (PAY-12). Completion of a fully refunded sub-order books nothing, so this
     * is the sub-order's only entry. Same store lock as adjustment().
     */
    public function recordCodRefundDebt(SubOrder $subOrder): void
    {
        DB::transaction(function () use ($subOrder) {
            Store::whereKey($subOrder->store_id)->lockForUpdate()->first();

            $this->write($subOrder->store_id, LedgerEntryType::CodOffset, -$this->buyerCashShareSen($subOrder, (int) $subOrder->total_sen), $subOrder->id,
                __('COD refund paid to the buyer by the platform for :no', ['no' => $subOrder->sub_order_no]));
        });
    }

    /** Spendable / withdrawable money. Pending (on hold) rows never count. */
    public function availableBalanceSen(Store $store): int
    {
        return (int) $store->ledgerEntries()
            ->where('status', LedgerEntryStatus::Available)
            ->sum('amount_sen');
    }

    /** Completion money still inside the return window. */
    public function pendingBalanceSen(Store $store): int
    {
        return $store->pendingBalanceSen();
    }

    /**
     * Cron: move pending rows whose hold has lapsed to available, unless the
     * sub-order has an open return (the money stays held until it resolves).
     * Idempotent — only touches rows still `pending`. Returns rows released.
     */
    public function releaseDue(): int
    {
        $open = [ReturnStatus::Requested, ReturnStatus::Accepted, ReturnStatus::Disputed, ReturnStatus::Escalated];

        return StoreLedgerEntry::query()
            ->where('status', LedgerEntryStatus::Pending)
            ->where('available_at', '<=', now())
            ->whereDoesntHave('subOrder.returnRequest', fn ($q) => $q->whereIn('status', $open))
            ->update(['status' => LedgerEntryStatus::Available]);
    }

    /**
     * Admin records a bank transfer from a seller who owes commission (COD).
     * Capped at what is owed: the available balance is negative by exactly
     * that amount.
     *
     * @throws CheckoutException when the amount is not positive or exceeds the debt
     */
    public function recordSettlement(Store $store, int $amountSen, string $reference, ?string $note = null): void
    {
        DB::transaction(function () use ($store, $amountSen, $reference, $note) {
            Store::whereKey($store->id)->lockForUpdate()->first();

            $owed = $this->owedSen($store);

            if ($amountSen <= 0) {
                throw new CheckoutException(__('Enter an amount above zero.'));
            }

            if ($amountSen > $owed) {
                throw new CheckoutException(__('This store only owes :amount.', ['amount' => Money::format($owed)]));
            }

            $this->write($store->id, LedgerEntryType::Settlement, $amountSen, null,
                trim(__('Settlement received, ref :ref', ['ref' => $reference]).($note ? " — {$note}" : '')));
        });
    }

    /**
     * Sen the store owes the platform for COD purposes: available + pending,
     * negated, floored at zero. Positive = debt.
     */
    public function owedSen(Store $store): int
    {
        return max(0, -($this->availableBalanceSen($store) + $this->pendingBalanceSen($store)));
    }

    /**
     * Seller payout request (docs/09 §A): min threshold, ≤ available, one
     * open request at a time. Earmarks the amount as a negative entry.
     *
     * @throws CheckoutException with a seller-facing reason
     */
    public function requestPayout(Store $store, int $amountSen): Payout
    {
        return DB::transaction(function () use ($store, $amountSen) {
            // Concurrent-request guard: serialize on the store row.
            Store::whereKey($store->id)->lockForUpdate()->first();

            if ($store->payouts()->whereIn('status', [PayoutStatus::Requested, PayoutStatus::Approved])->exists()) {
                throw new CheckoutException(__('You already have a payout in progress.'));
            }

            $minSen = $this->orderSettings->payout_min_sen;

            if ($amountSen < $minSen) {
                throw new CheckoutException(__('Minimum payout is :min.', ['min' => Money::format($minSen)]));
            }

            $available = $this->availableBalanceSen($store);

            if ($amountSen > $available) {
                throw new CheckoutException(__('Only :amount is available for payout.', ['amount' => Money::format(max(0, $available))]));
            }

            $payout = Payout::create([
                'payout_no' => Payout::generatePayoutNo(),
                'store_id' => $store->id,
                'amount_sen' => $amountSen,
                'status' => PayoutStatus::Requested,
                'bank_snapshot' => $store->bank_details ?? [],
                'requested_at' => now(),
            ]);

            $store->ledgerEntries()->create([
                'payout_id' => $payout->id,
                'type' => LedgerEntryType::Payout,
                'amount_sen' => -$amountSen,
                'status' => LedgerEntryStatus::Available,
                'description' => __('Payout :no requested', ['no' => $payout->payout_no]),
            ]);

            return $payout;
        });
    }

    /**
     * Paid product boost (Phase-4): debits the flat fee from the store's
     * available balance under a store-row lock so concurrent boosts can't
     * overdraw. Boost revenue is platform income — the negative entry simply
     * reduces the seller's balance; nothing is owed back on cancel (v1).
     *
     * @throws CheckoutException when the available balance can't cover the fee
     */
    public function chargeBoost(Store $store, int $amountSen, Product $product): void
    {
        DB::transaction(function () use ($store, $amountSen, $product) {
            // Serialize concurrent boost charges on the store row.
            Store::whereKey($store->id)->lockForUpdate()->first();

            if ($this->availableBalanceSen($store) < $amountSen) {
                throw new CheckoutException(__('Top up your balance first — boosts are paid from your available earnings.'));
            }

            $this->write($store->id, LedgerEntryType::Boost, -$amountSen, null,
                __('Boost: :name', ['name' => $product->getTranslation('name', 'en')]));
        });
    }

    /**
     * Rejection releases the earmark with a reversal entry (PAY-08): the
     * original negative `payout` row stays for the audit trail and an equal
     * positive `payout_reversal` row, linked to the same payout, cancels it —
     * the available balance ends exactly where deleting the row used to leave
     * it. Locked + status-checked so a repeat reject never credits twice.
     * Returns false (nothing written) when the payout is no longer requested
     * or approved.
     */
    public function rejectPayout(Payout $payout, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($payout, $reason) {
            Store::whereKey($payout->store_id)->lockForUpdate()->first();
            $locked = Payout::whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [PayoutStatus::Requested, PayoutStatus::Approved], true)) {
                return false;
            }

            $locked->update(['status' => PayoutStatus::Rejected, 'reference' => $reason]);

            $earmarkSen = (int) $locked->ledgerEntries()->sum('amount_sen');

            if ($earmarkSen !== 0) {
                $locked->ledgerEntries()->create([
                    'store_id' => $locked->store_id,
                    'type' => LedgerEntryType::PayoutReversal,
                    'amount_sen' => -$earmarkSen,
                    'status' => LedgerEntryStatus::Available,
                    'description' => __('Payout :no rejected — amount returned to your balance', ['no' => $locked->payout_no]),
                ]);
            }

            $payout->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    /**
     * Approve / mark paid (HB-G224): status bookkeeping only, the ledger is
     * untouched (the earmark written at request time stays). Locked and
     * re-checked like rejectPayout, so an approve or mark-paid racing a reject
     * can never leave a payout approved or paid after its money was returned.
     * Returns false (nothing written) when the payout is no longer at $from.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function advancePayout(Payout $payout, PayoutStatus $from, array $attributes): bool
    {
        return DB::transaction(function () use ($payout, $from, $attributes) {
            $locked = Payout::whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== $from) {
                return false;
            }

            $locked->update($attributes);
            $payout->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    private function write(int $storeId, LedgerEntryType $type, int $amountSen, ?int $subOrderId, string $description, ?string $settlementKey = null, ?CarbonInterface $holdUntil = null): void
    {
        // A hold that has already lapsed (auto-complete fires exactly at the end
        // of the window) books straight to available — no hourly lag.
        if ($holdUntil !== null && ! $holdUntil->isFuture()) {
            $holdUntil = null;
        }

        // forceFill: `settlement_key` is a new column (H2 backstop) not yet in
        // StoreLedgerEntry::$fillable — out of this fix's declared file surface,
        // so bypass mass-assignment here rather than edit the model.
        (new StoreLedgerEntry)->forceFill([
            'store_id' => $storeId,
            'sub_order_id' => $subOrderId,
            'type' => $type,
            'amount_sen' => $amountSen,
            'status' => $holdUntil === null ? LedgerEntryStatus::Available : LedgerEntryStatus::Pending,
            'available_at' => $holdUntil,
            'description' => $description,
            'settlement_key' => $settlementKey,
            'created_at' => now(),
        ])->save();
    }
}
