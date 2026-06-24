<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Recovery nudge for a cart left idle (M1.4). */
class AbandonedCartNotification extends Notification
{
    use Queueable;

    public function __construct(public int $itemCount) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('You left something in your cart'))
            ->line(trans_choice('Your cart still has :count item waiting.|Your cart still has :count items waiting.', $this->itemCount, ['count' => $this->itemCount]))
            ->action(__('Return to cart'), route('cart'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'abandoned_cart',
            'item_count' => $this->itemCount,
            'message' => trans_choice('You left :count item in your cart.|You left :count items in your cart.', $this->itemCount, ['count' => $this->itemCount]),
            'url' => route('cart'),
        ];
    }
}
