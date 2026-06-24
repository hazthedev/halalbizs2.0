<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Events\SubOrderStatusChanged;
use App\Services\WebhookDispatcher;

/**
 * Emits outbound webhooks on order lifecycle events (M1.7). Registered
 * explicitly in AppServiceProvider (methods are deliberately NOT named
 * `handle*` so event auto-discovery never double-registers them). Handles
 * OrderPaid ("order.paid") and SubOrderStatusChanged ("sub_order.<status>").
 */
class DispatchOrderWebhooks
{
    public function __construct(private WebhookDispatcher $webhooks) {}

    public function onOrderPaid(OrderPaid $event): void
    {
        $order = $event->order;

        $this->webhooks->dispatch('order.paid', [
            'order_no' => $order->order_no,
            'user_id' => $order->user_id,
            'grand_total_sen' => (int) $order->grand_total_sen,
            'payment_method' => $order->payment_method?->value,
            'paid_at' => $order->paid_at?->toIso8601String(),
        ]);
    }

    public function onSubOrderStatusChanged(SubOrderStatusChanged $event): void
    {
        $subOrder = $event->subOrder;

        $this->webhooks->dispatch("sub_order.{$event->to->value}", [
            'sub_order_no' => $subOrder->sub_order_no,
            'order_no' => $subOrder->order?->order_no,
            'status' => $event->to->value,
            'total_sen' => (int) $subOrder->total_sen,
            'tracking_no' => $subOrder->awb_no ?? $subOrder->tracking_no,
        ], $subOrder->store_id);
    }
}
