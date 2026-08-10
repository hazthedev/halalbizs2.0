<?php

namespace App\Notifications;

use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** A creator earned affiliate commission on a referred order (M2.5). */
class AffiliateCommissionNotification extends Notification
{
    use Queueable;

    public function __construct(public int $commissionSen, public ?\Illuminate\Support\Carbon $unlocksAt = null) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        // Say "pending, unlocks <date>" rather than "earned". The number is real
        // but not yet spendable, and a creator who reads "earned" and then sees
        // the balance drop after a refund has been misled by us, not by the
        // refund. Naming the date up front is what makes a reduction a non-event.
        return [
            'type' => 'affiliate_commission',
            'message' => $this->unlocksAt === null
                ? __('You earned :amount commission from a referred order.', ['amount' => Money::format($this->commissionSen)])
                : __(':amount commission is pending from a referred order — available :date once the return window closes.', [
                    'amount' => Money::format($this->commissionSen),
                    'date' => $this->unlocksAt->translatedFormat('j M Y'),
                ]),
            'url' => route('account.affiliate'),
        ];
    }
}
