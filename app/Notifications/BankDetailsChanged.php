<?php

namespace App\Notifications;

use App\Models\Store;
use App\Support\DisplayTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Security alert (PAY-20): the store's payout bank account was just changed
 * (web settings or the seller API). Payouts go to this account, so the owner
 * hears about it even if someone else holds their session. The account number
 * is masked to its last 4 digits — the mail never carries the full number.
 */
class BankDetailsChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public string $bankName;

    public string $maskedAccount;

    public function __construct(public Store $store)
    {
        $bank = $store->bank_details ?? [];
        $this->bankName = (string) ($bank['bank_name'] ?? '');
        $this->maskedAccount = Str::mask((string) ($bank['account_number'] ?? ''), '*', 0, -4);
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your payout bank account was changed — :store', ['store' => $this->store->name]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__('The payout bank account for :store was changed on :time.', [
                'store' => $this->store->name,
                'time' => DisplayTime::now()->format('j M Y, g:i a'),
            ]))
            ->line(__('New account: :bank · :account', ['bank' => $this->bankName, 'account' => $this->maskedAccount]))
            ->line(__('If you did not make this change, change your password now and contact us before your next payout.'))
            ->action(__('Review shop settings'), url('/seller/settings'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => __('Payout bank account changed — :bank :account', ['bank' => $this->bankName, 'account' => $this->maskedAccount]),
            'detail' => __('If you did not make this change, change your password now and contact us before your next payout.'),
            'store_id' => $this->store->id,
            'url' => url('/seller/settings'),
        ];
    }
}
