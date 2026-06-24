<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** A buyer's group-buy team reached its target and unlocked the deal price (M2.6). */
class GroupBuyUnlockedNotification extends Notification
{
    use Queueable;

    public function __construct(public string $teamCode, public string $productName) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'group_buy_unlocked',
            'message' => __('Your group buy for :product is unlocked — check out at the group price!', ['product' => $this->productName]),
            'url' => route('group-buy.team', $this->teamCode),
        ];
    }
}
