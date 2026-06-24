<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Buyer earned Loyalty Coins on a completed order (M2.1). */
class CoinsEarnedNotification extends Notification
{
    use Queueable;

    public function __construct(public int $coins, public string $subOrderNo) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'coins_earned',
            'message' => __('You earned :n coins from order :no.', ['n' => $this->coins, 'no' => $this->subOrderNo]),
            'url' => route('account.coins'),
        ];
    }
}
