<?php

namespace App\Services;

use App\Enums\LedgerEntryStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\PayoutStatus;
use App\Exceptions\CheckoutException;
use App\Models\Payout;
use App\Models\Store;
use App\Models\StoreLedgerEntry;
use App\Models\SubOrder;
use App\Settings\OrderSettings;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Seller escrow ledger (docs/09 §A). All amounts are SIGNED integer sen.
 * Balance = SUM(amount_sen) of available entries — payout requests write a
 * negative `payout` entry referencing the payout row, so the balance math
 * stays a single SUM and rejection simply deletes that entry.
 */
class LedgerService
{
    public function __construct(private OrderSettings $orderSettings) {}

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

            $saleSen = $subOrder->items_subtotal_sen + $subOrder->shipping_fee_sen - $subOrder->shop_discount_sen;

            // round(items_subtotal × rate%) with integer math: rate is decimal(5,2).
            $rateBasisPoints = (int) round((float) $subOrder->commission_rate * 100); // e.g. 5.25% → 525
            $commissionSen = intdiv($subOrder->items_subtotal_sen * $rateBasisPoints + 5000, 10000);

            $subOrder->forceFill(['commission_sen' => $commissionSen])->save();

            $this->write($subOrder->store_id, LedgerEntryType::Sale, $saleSen, $subOrder->id,
                __('Sale :no', ['no' => $subOrder->sub_order_no]));

            $this->write($subOrder->store_id, LedgerEntryType::Commission, -$commissionSen, $subOrder->id,
                __('Commission :rate% on :no', ['rate' => $subOrder->commission_rate, 'no' => $subOrder->sub_order_no]));

            if ($subOrder->order->payment_method === PaymentMethod::Cod) {
                $this->write($subOrder->store_id, LedgerEntryType::CodOffset, -$saleSen, $subOrder->id,
                    __('COD cash collected for :no', ['no' => $subOrder->sub_order_no]));
            }

            foreach ($subOrder->items as $item) {
                $item->product?->increment('sold_count', $item->qty);
            }
        });
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
                'created_at' => now(),
            ]);

            return $payout;
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

    private function write(int $storeId, LedgerEntryType $type, int $amountSen, ?int $subOrderId, string $description): void
    {
        StoreLedgerEntry::create([
            'store_id' => $storeId,
            'sub_order_id' => $subOrderId,
            'type' => $type,
            'amount_sen' => $amountSen,
            'status' => LedgerEntryStatus::Available,
            'description' => $description,
            'created_at' => now(),
        ]);
    }
}
