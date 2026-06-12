<?php

namespace App\Notifications;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Admin decision on a seller application (docs/08 §B) — approved or
 * rejected, delivered by email and stored in the database for the bell.
 */
class SellerApplicationDecision extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'approved'|'rejected'  $decision
     */
    public function __construct(
        public Store $store,
        public string $decision,
        public ?string $reason = null,
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
        if ($this->decision === 'approved') {
            return (new MailMessage)
                ->subject(__('Application approved — :store', ['store' => $this->store->name]))
                ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
                ->line(__('Good news — your application to open :store on :app has been approved.', [
                    'store' => $this->store->name,
                    'app' => config('app.name'),
                ]))
                ->line(__('Your seller centre is ready. Add your first product to start selling.'))
                ->action(__('Open seller centre'), route('seller.dashboard'));
        }

        return (new MailMessage)
            ->subject(__('Application update — :store', ['store' => $this->store->name]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__('We reviewed your application to open :store on :app and can\'t approve it this time.', [
                'store' => $this->store->name,
                'app' => config('app.name'),
            ]))
            ->line(__('Reason: :reason', ['reason' => (string) $this->reason]))
            ->line(__('You can fix the issue and re-apply at any time.'))
            ->action(__('View application status'), route('seller.status'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'decision' => $this->decision,
            'reason' => $this->reason,
        ];
    }
}
