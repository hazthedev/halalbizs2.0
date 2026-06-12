<?php

namespace App\Jobs;

use App\Enums\ActorType;
use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Models\Payment;
use App\Services\Ipay88Service;
use App\Services\SubOrderStatusService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fulfils an iPay88 payment ONLY after a successful requery (hard rule 4).
 */
class ConfirmIpay88PaymentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Payment $payment,
        public array $callbackPayload = [],
    ) {
        $this->onQueue('payments');
    }

    public function handle(Ipay88Service $ipay88, SubOrderStatusService $statusService): void
    {
        $payment = $this->payment->fresh();

        if ($payment->status === GatewayPaymentStatus::Success) {
            return; // idempotent — already confirmed
        }

        $requeryResult = $ipay88->requery($payment->ref_no, $payment->amount_sen);

        if ($requeryResult !== '00') {
            $payment->update(['requery_result' => $requeryResult]);
            Log::error('iPay88 requery mismatch — payment left pending for admin review.', [
                'payment_id' => $payment->id,
                'ref_no' => $payment->ref_no,
                'requery_result' => $requeryResult,
            ]);

            return;
        }

        DB::transaction(function () use ($payment, $statusService) {
            $payment->update([
                'status' => GatewayPaymentStatus::Success,
                'requery_result' => '00',
                'ipay88_payment_id' => $this->callbackPayload['PaymentId'] ?? $payment->ipay88_payment_id,
                'ipay88_trans_id' => $this->callbackPayload['TransId'] ?? $payment->ipay88_trans_id,
                'ipay88_auth_code' => $this->callbackPayload['AuthCode'] ?? $payment->ipay88_auth_code,
                'response_payload' => $this->callbackPayload ?: $payment->response_payload,
                'paid_at' => now(),
            ]);

            $order = $payment->order;
            $order->update([
                'payment_status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'expires_at' => null,
            ]);

            foreach ($order->subOrders as $subOrder) {
                if ($subOrder->status === SubOrderStatus::PendingPayment) {
                    $statusService->transition($subOrder, SubOrderStatus::Confirmed, ActorType::System);
                }
            }
        });
    }
}
