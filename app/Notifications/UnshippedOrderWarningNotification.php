<?php

namespace App\Notifications;

use App\Models\SubOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * PAY-14: the seller's one warning before orders:cancel-unshipped cancels a
 * sub-order they have not shipped. Same channels as SubOrderStatusNotification.
 */
class UnshippedOrderWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SubOrder $subOrder, public int $daysLeft) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Ship soon')." · {$this->subOrder->sub_order_no}")
            ->line($this->line())
            ->action(__('View order'), $this->url());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => __('Ship soon')." — {$this->subOrder->sub_order_no}",
            'detail' => $this->line(),
            'sub_order_id' => $this->subOrder->id,
            'sub_order_no' => $this->subOrder->sub_order_no,
            'url' => $this->url(),
        ];
    }

    private function line(): string
    {
        return __('Order :no has not shipped yet. Ship it within :days days or it will be cancelled automatically and the buyer told.', [
            'no' => $this->subOrder->sub_order_no,
            'days' => $this->daysLeft,
        ]);
    }

    private function url(): string
    {
        return url("/seller/orders/{$this->subOrder->id}");
    }
}
