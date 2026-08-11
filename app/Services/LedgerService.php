<?php

namespace App\Services;

use App\Enums\CommissionBasis;
use App\Enums\LedgerEntryStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\PayoutStatus;
use App\Exceptions\CheckoutException;
use App\Models\Payout;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreLedgerEntry;
use App\Models\SubOrder;
use App\Settings\CommissionSettings;
use App\Settings\OrderSettings;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Seller escrow ledger (docs/09 §A). All amounts are SIGNED integer sen.
 * Balance = SUM(amount_sen) of available entries — payout requests write a
 * negative `payout` entry referencing the payout row, so the balance math
 * stays a single SUM and rejection simply deletes that entry.
 */
class LedgerService
{
    public function __construct(
        private OrderSettings $orderSettings,
        private CommissionSettings $commissionSettings,
    ) {}

    /**
     * Completion hook: +sale, −commission, and for COD −cod_offset (the
     * seller already holds the cash — net effect is the commission owed).
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

            $basis = $this->commissionSettings->basis();
            $baseSen = $this->commissionBaseSen($subOrder, $basis);

            // round(base × rate%) with integer math: rate is decimal(5,2).
            $rateBasisPoints = (int) round((float) $subOrder->commission_rate * 100); // e.g. 5.25% → 525
            $commissionSen = intdiv($baseSen * $rateBasisPoints + 5000, 10000);

            // Snapshot the basis alongside the rate — hard rule #5. The setting
            // is flippable, so without this a historical commission cannot be
            // explained after someone changes it.
            $subOrder->forceFill([
                'commission_sen' => $commissionSen,
                'commission_basis' => $basis,
            ])->save();

            // H2 DB-level backstop: Sale/Commission are once-per-sub-order
            // settlement entries, so they carry a unique `settlement_key`
            // ("sale:{id}" / "commission:{id}") — NULL (unconstrained) for every
            // repeatable type (adjustment/payout/cod_offset). The exists() guard
            // above is not itself race-proof (two concurrent completions can both
            // pass it before either writes), so a lost race here surfaces as a
            // unique-constraint violation, which must be a clean no-op, not a 500.
            try {
                $this->write($subOrder->store_id, LedgerEntryType::Sale, $saleSen, $subOrder->id,
                    __('Sale :no', ['no' => $subOrder->sub_order_no]), "sale:{$subOrder->id}");

                $this->write($subOrder->store_id, LedgerEntryType::Commission, -$commissionSen, $subOrder->id,
                    __('Commission :rate% on :no', ['rate' => $subOrder->commission_rate, 'no' => $subOrder->sub_order_no]), "commission:{$subOrder->id}");
            } catch (QueryException $e) {
                if ($this->isUniqueViolation($e)) {
                    return; // another completion already booked this sub-order — no-op
                }

                throw $e;
            }

            if ($subOrder->order->payment_method === PaymentMethod::Cod) {
                $this->write($subOrder->store_id, LedgerEntryType::CodOffset, -$saleSen, $subOrder->id,
                    __('COD cash collected for :no', ['no' => $subOrder->sub_order_no]));
            }

            foreach ($subOrder->items as $item) {
                $item->product?->increment('sold_count', $item->qty);
            }
        });
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
        $this->write($store->id, LedgerEntryType::Adjustment, $amountSen, $subOrder?->id, $reason);
    }

    public function availableBalanceSen(Store $store): int
    {
        return (int) $store->ledgerEntries()
            ->where('status', LedgerEntryStatus::Available)
            ->sum('amount_sen');
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

    /** Rejection releases the earmark by deleting the payout entry. */
    public function rejectPayout(Payout $payout, ?string $reason = null): void
    {
        DB::transaction(function () use ($payout, $reason) {
            $payout->update(['status' => PayoutStatus::Rejected, 'reference' => $reason]);
            $payout->ledgerEntries()->delete();
        });
    }

    private function write(int $storeId, LedgerEntryType $type, int $amountSen, ?int $subOrderId, string $description, ?string $settlementKey = null): void
    {
        // forceFill: `settlement_key` is a new column (H2 backstop) not yet in
        // StoreLedgerEntry::$fillable — out of this fix's declared file surface,
        // so bypass mass-assignment here rather than edit the model.
        (new StoreLedgerEntry)->forceFill([
            'store_id' => $storeId,
            'sub_order_id' => $subOrderId,
            'type' => $type,
            'amount_sen' => $amountSen,
            'status' => LedgerEntryStatus::Available,
            'description' => $description,
            'settlement_key' => $settlementKey,
            'created_at' => now(),
        ])->save();
    }
}
