<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email-channel 2FA code. Deliberately NOT queued — the user is sitting on
 * the challenge screen waiting for it. Mail only; the code never reaches
 * the database notifications table or the log.
 */
class TwoFactorCodeNotification extends Notification
{
    public function __construct(public string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your login code'))
            ->line(__('Your HalalBizs login code is:'))
            ->line('## '.$this->code)
            ->line(__('It expires in 10 minutes. If you didn\'t try to log in, change your password now.'));
    }
}
