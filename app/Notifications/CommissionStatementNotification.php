<?php

namespace App\Notifications;

use App\Models\Store;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Monthly statement to a seller who owes commission (COD). */
class CommissionStatementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Store $store,
        public string $month,
        public int $monthCommissionSen,
        public int $totalOwedSen,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Commission statement :month — :store', ['month' => $this->month, 'store' => $this->store->name]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__('Commission on your cash-on-delivery orders in :month: :amount.', ['month' => $this->month, 'amount' => Money::format($this->monthCommissionSen)]))
            ->line(__('Total you owe :app: :amount.', ['app' => config('app.name'), 'amount' => Money::format($this->totalOwedSen)]))
            ->line(__('Please bank-transfer it and reply with your reference. Stores owing more than the COD limit cannot take cash on delivery until settled.'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'store_id' => $this->store->id,
            'month' => $this->month,
            'month_commission_sen' => $this->monthCommissionSen,
            'total_owed_sen' => $this->totalOwedSen,
        ];
    }
}
