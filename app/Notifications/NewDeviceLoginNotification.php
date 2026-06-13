<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Security alert: a successful login came from a device this account has
 * never used before (DeviceGuard). Sent by mail + database.
 */
class NewDeviceLoginNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $deviceLabel,
        public string $ip,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New login to your HalalBizs account'))
            ->line(__('Your account was just signed in to from a device we haven\'t seen before:'))
            ->line(__(':device · IP :ip · :time', [
                'device' => $this->deviceLabel,
                'ip' => $this->ip,
                'time' => now()->format('j M Y, g:i a'),
            ]))
            ->line(__('If this was you, no action is needed. If it wasn\'t, change your password and log out your other devices now.'))
            ->action(__('Review account security'), url('/account#security'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => __('New device login — :device', ['device' => $this->deviceLabel]),
            'detail' => __('If this wasn\'t you, change your password and log out other devices.'),
            'ip' => $this->ip,
            'url' => url('/account#security'),
        ];
    }
}
