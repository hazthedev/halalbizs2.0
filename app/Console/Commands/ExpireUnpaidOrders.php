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

            $payment?->update(['status' => GatewayPaymentStatus::Expired]);
            $order->update(['payment_status' => PaymentStatus::Expired]);

            foreach ($order->subOrders as $subOrder) {
                if ($subOrder->status === SubOrderStatus::PendingPayment) {
                    $orderService->cancel($subOrder, ActorType::System, null, __('Payment window closed'));
                }
            }

            $order->user?->notify(new OrderExpiredNotification($order));
            $this->info("Expired {$order->order_no} and restocked items.");
        }

        return self::SUCCESS;
    }
}
