<?php

namespace App\Notifications;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerApplicationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Store $store) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Application received — :store', ['store' => $this->store->name]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__('We received your application to open :store on :app.', [
                'store' => $this->store->name,
                'app' => config('app.name'),
            ]))
            ->line(__('Our team reviews applications within 2–3 business days. We\'ll email you the moment a decision is made.'))
            ->action(__('View application status'), route('seller.status'))
            ->line(__('You don\'t need to do anything else for now.'));
    }
}
