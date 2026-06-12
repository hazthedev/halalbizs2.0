<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Payment window closed').' · '.$this->order->order_no)
            ->line(__('The payment window for order :no has closed and the order was cancelled. Nothing was charged.', ['no' => $this->order->order_no]))
            ->action(__('Shop again'), url('/'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => __('Payment window closed — :no was cancelled.', ['no' => $this->order->order_no]),
            'order_no' => $this->order->order_no,
            'url' => url('/account/orders'),
        ];
    }
}
