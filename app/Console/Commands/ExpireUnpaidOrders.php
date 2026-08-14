<?php

namespace App\Console\Commands;

use App\Enums\ActorType;
use App\Enums\GatewayPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Jobs\ConfirmIpay88PaymentJob;
use App\Models\Order;
use App\Notifications\OrderExpiredNotification;
use App\Services\Ipay88Service;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * docs/06 §D5 — runs every minute. A final requery can rescue a late
 * payment; otherwise the order expires and stock is released.
 */
class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = 'Expire unpaid iPay88 orders past their window (with a requery rescue)';

    public function handle(Ipay88Service $ipay88, OrderService $orderService): int
    {
        $expired = Order::where('payment_method', PaymentMethod::Ipay88)
            ->where('payment_status', PaymentStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with(['payments', 'subOrders'])
            ->get();

        foreach ($expired as $order) {
            $payment = $order->payments->where('status', GatewayPaymentStatus::Pending)->sortByDesc('id')->first();

            // Rescue: one final requery before cancelling.
            if ($payment !== null && $ipay88->requery($payment->ref_no, $payment->amount_sen) === '00') {
                ConfirmIpay88PaymentJob::dispatchSync($payment);
                $this->info("Rescued late payment for {$order->order_no}.");

                continue;
            }

            // M-10: the terminal write used to happen here against the row as
            // it was loaded BEFORE the requery — a blocking call of up to 10s.
            // A gateway callback landing in that window was simply overwritten,
            // so a paid order could be expired and restocked. Re-read under a
            // lock and re-assert, inside a transaction.
            $settled = DB::transaction(function () use ($order, $payment, $orderService) {
                $fresh = Order::whereKey($order->id)->lockForUpdate()->first();

                if ($fresh->payment_status !== PaymentStatus::Pending) {
                    return false; // paid, or already expired by an overlapping run
                }

                $payment?->refresh();

                if ($payment !== null && $payment->status !== GatewayPaymentStatus::Pending) {
                    return false;
                }

                $payment?->update(['status' => GatewayPaymentStatus::Expired]);
                $fresh->update(['payment_status' => PaymentStatus::Expired]);

                foreach ($fresh->subOrders as $subOrder) {
                    if ($subOrder->status === SubOrderStatus::PendingPayment) {
                        $orderService->cancel($subOrder, ActorType::System, null, __('Payment window closed'));
                    }
                }

                return true;
            });

            if (! $settled) {
                $this->info("Skipped {$order->order_no} — it settled during the requery.");

                continue;
            }

            $order->user?->notify(new OrderExpiredNotification($order));
            $this->info("Expired {$order->order_no} and restocked items.");
        }

        return self::SUCCESS;
    }
}
