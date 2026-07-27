<?php

namespace App\Jobs;

use App\Enums\ActorType;
use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Events\OrderPaid;
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

    /**
     * M3: iPay88 retries the BackendURL callback, and a transient requery
     * network error must recover on its own rather than strand the payment
     * in Pending awaiting manual review. failed() logs loudly once every
     * attempt is exhausted.
     */
    public int $tries = 5;

    public function backoff(): array
    {
        return [10, 30, 120, 600];
    }

    public function __construct(
        public Payment $payment,
        public array $callbackPayload = [],
    ) {
        $this->onQueue('payments');
    }

    public function handle(Ipay88Service $ipay88, SubOrderStatusService $statusService): void
    {
        // The requery HTTP round-trip happens OUTSIDE the row lock below —
        // holding a row lock across a network call would stall any concurrent
        // callback for the whole request timeout instead of just no-op'ing.
        $refNo = $this->payment->ref_no;
        $amountSen = $this->payment->amount_sen;

        // Mock/simulator mode (no merchant code configured): skip the real
        // requery and treat as settled, so preview checkouts can complete.
        $requeryResult = $ipay88->isMock()
            ? '00'
            : $ipay88->requery($refNo, $amountSen);

        // M2: re-check status AND apply the outcome inside one locked
        // transaction. iPay88 retries the BackendURL callback, so two
        // near-simultaneous callbacks can each have passed the pre-requery
        // check before either commits — the lock + re-read here makes the
        // second one a clean no-op instead of a duplicate fulfilment.
        $order = DB::transaction(function () use ($requeryResult, $statusService) {
            $payment = Payment::whereKey($this->payment->id)->lockForUpdate()->first();

            if ($payment->status === GatewayPaymentStatus::Success) {
                return null; // idempotent — already confirmed by a concurrent callback
            }

            if ($requeryResult !== '00') {
                $payment->update(['requery_result' => $requeryResult]);
                Log::error('iPay88 requery mismatch — payment left pending for admin review.', [
                    'payment_id' => $payment->id,
                    'ref_no' => $payment->ref_no,
                    'requery_result' => $requeryResult,
                ]);

                return null;
            }

            $payment->update([
                'status' => GatewayPaymentStatus::Success,
                'requery_result' => '00',
                // PaymentId is the chosen channel (FPX bank / wallet / card code).
                'channel' => $this->callbackPayload['PaymentId'] ?? $payment->channel,
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

            return $order;
        });

        // After commit: trigger e-invoicing (and any other post-payment
        // work) only for the callback that actually performed the fulfilment.
        if ($order !== null) {
            OrderPaid::dispatch($order->fresh());
        }
    }

    /** M3: every retry is exhausted — the buyer paid, but this needs a human now. */
    public function failed(?\Throwable $exception): void
    {
        Log::error('ConfirmIpay88PaymentJob permanently failed after all retries — payment needs manual admin review.', [
            'payment_id' => $this->payment->id,
            'ref_no' => $this->payment->ref_no,
            'error' => $exception?->getMessage(),
        ]);
    }
}
