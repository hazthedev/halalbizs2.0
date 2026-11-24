<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** BIZ-04: coins in the buyer's wallet lapse within 30 days. Sent once per lot. */
class CoinsExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(public int $coins, public Carbon $expiresAt) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'coins_expiring',
            'message' => __(':n coins expire on :date. Spend them at checkout before then.', [
                'n' => $this->coins, 'date' => $this->expiresAt->display()->format('j M Y'),
            ]),
            'url' => route('account.coins'),
        ];
    }
}
