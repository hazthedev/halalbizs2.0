<?php

namespace App\Listeners;

use App\Enums\SubOrderStatus;
use App\Events\SubOrderStatusChanged;
use App\Notifications\SubOrderStatusNotification;

/**
 * Notification matrix (docs/06 §E): every status change notifies the
 * buyer; sellers hear about new paid orders, cancellations, completions
 * and return requests.
 */
class SendSubOrderStatusNotifications
{
    private const BUYER_STATUSES = [
        SubOrderStatus::Confirmed,
        SubOrderStatus::Processing,
        SubOrderStatus::Shipped,
        SubOrderStatus::Delivered,
        SubOrderStatus::Completed,
        SubOrderStatus::Cancelled,
        SubOrderStatus::Refunded,
    ];

    private const SELLER_STATUSES = [
        SubOrderStatus::Confirmed,
        SubOrderStatus::Cancelled,
        SubOrderStatus::Completed,
        SubOrderStatus::ReturnRequested,
    ];

    public function handle(SubOrderStatusChanged $event): void
    {
        $subOrder = $event->subOrder;

        if (in_array($event->to, self::BUYER_STATUSES, true)) {
            $subOrder->order->user?->notify(
                new SubOrderStatusNotification($subOrder, $event->to, 'buyer')
            );
        }

        if (in_array($event->to, self::SELLER_STATUSES, true)) {
            $subOrder->store->user?->notify(
                new SubOrderStatusNotification($subOrder, $event->to, 'seller')
            );
        }
    }
}
