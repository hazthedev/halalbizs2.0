<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** A subscribe-and-save cycle auto-placed an order (M2.8). */
class SubscriptionOrderPlacedNotification extends Notification
{
    use Queueable;

    public function __construct(public string $orderNo) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_order',
            'message' => __('Your subscription order :no has been placed.', ['no' => $this->orderNo]),
            'url' => route('account.orders'),
        ];
    }
}
