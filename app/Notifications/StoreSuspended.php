<?php

namespace App\Notifications;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Admin suspended a store (docs/08 §B) — the owner keeps account access
 * but the shop and its listings are off the storefront until reinstated.
 */
class StoreSuspended extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Store $store,
        public string $reason,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your shop has been suspended — :store', ['store' => $this->store->name]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__(':store has been suspended on :app and is no longer visible to buyers.', [
                'store' => $this->store->name,
                'app' => config('app.name'),
            ]))
            ->line(__('Reason: :reason', ['reason' => $this->reason]))
            ->line(__('Reply to this email if you believe this is a mistake.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'reason' => $this->reason,
        ];
    }
}
