<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
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
    ) {}

    public function refund(
        SubOrder $subOrder,
        int $amountSen,
        ActorType $actor,
        ?int $actorId,
        ?string $reference = null,
        bool $markRefunded = true,
    ): void {
        $total = (int) $subOrder->total_sen;
        $amountSen = max(0, min($amountSen, $total));
        $online = $subOrder->order->payment_method === PaymentMethod::Ipay88;

        DB::transaction(function () use ($subOrder, $amountSen, $actor, $actorId, $reference, $markRefunded, $online, $total) {
            // 1. Ledger reversal — only once the sale was booked (completed).
            if ($amountSen > 0 && $total > 0
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

            if ($amountSen > 0 && (int) $order->coin_redemption_sen > 0) {
                $payableBasisSen = (int) $order->grand_total_sen + (int) $order->coin_redemption_sen;
                $this->coins->reverseForRefund($order, $amountSen, $payableBasisSen);
            }

            // 2. Track on the payment + best-effort gateway refund (manual portal is the fallback).
            $payment = $subOrder->order->payment;

            if ($payment !== null) {
                if ($online && $amountSen > 0) {
                    $this->gateways->driver($subOrder->order->payment_method?->value)?->refund($payment, $amountSen, $reference);
                }

                $payment->forceFill([
                    'refunded_sen' => (int) $payment->refunded_sen + $amountSen,
                    'refunded_at' => now(),
                ])->save();
            }

            // 3. A full refund moves the sub-order to Refunded; the order follows once all settled.
            if ($markRefunded && $this->status->canTransition($subOrder, SubOrderStatus::Refunded)) {
                $this->status->transition(
                    $subOrder,
                    SubOrderStatus::Refunded,
                    $actor,
                    $actorId,
                    $online && $reference !== null
                        ? __('Refunded via iPay88 portal — ref :ref', ['ref' => $reference])
                        : __('COD refund recorded as a ledger adjustment'),
                );

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
}
