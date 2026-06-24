<?php

namespace App\Notifications;

use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** A creator earned affiliate commission on a referred order (M2.5). */
class AffiliateCommissionNotification extends Notification
{
    use Queueable;

    public function __construct(public int $commissionSen) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'affiliate_commission',
            'message' => __('You earned :amount commission from a referred order.', ['amount' => Money::format($this->commissionSen)]),
            'url' => route('account.affiliate'),
        ];
    }
}
